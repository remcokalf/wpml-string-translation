<?php

namespace WPML\ST\TranslationFile;

use WP_Filesystem_Base;
use WPML\Collect\Support\Collection;
use WPML\StringTranslation\Infrastructure\Core\Command\SaveFileCommand;
use WPML\ST\MO\File\makeDir;
use WPML\ST\Storage\StoragePerLanguageInterface;
use WPML\ST\Storage\WpTransientPerLanguage;
use WPML_Language_Records;
use WPML_ST_Translations_File_Dictionary;
use function wpml_collect;
use WPML_ST_Translations_File_Entry;

abstract class Manager {

	use makeDir;

	const SUB_DIRECTORY = 'wpml';
	const INDEX_CACHE_ID = 'wpml_st_translation_file_index';

	protected $strings;
	protected $language_records;
	protected $builder;
	protected $file_dictionary;
	protected $domains;
	protected $save_file_command;

	private $index = [];
	private $index_learned = [];
	private $index_written = [];
	private $index_storage;
	private $index_persist_hooked = false;

	public function __construct(
		StringsRetrieve $strings,
		Builder $builder,
		WP_Filesystem_Base $filesystem,
		WPML_Language_Records $language_records,
		Domains $domains,
		?SaveFileCommand $save_file_command = null,
		?StoragePerLanguageInterface $index_storage = null
	) {
		$this->strings           = $strings;
		$this->builder           = $builder;
		$this->filesystem        = $filesystem;
		$this->language_records  = $language_records;
		$this->domains           = $domains;
		$this->save_file_command = $save_file_command ?: new SaveFileCommand( $filesystem );
		$this->index_storage     = $index_storage;
	}

	public function remove( $domain, $locale ) {
		$filepath = $this->getFilepath( $domain, $locale );
		$this->with_file_lock(
			$filepath,
			function () use ( $filepath ) {
				$this->filesystem->delete( $filepath );

				if ( 'mo' === $this->getFileExtension() ) {
					$php_filepath = substr( $filepath, 0, -3 ) . '.l10n.php';
					if ( $this->filesystem->is_file( $php_filepath ) && $this->filesystem->is_readable( $php_filepath ) ) {
						$this->filesystem->delete( $php_filepath );
					}
				}
			}
		);

		$this->recordFile( $locale, $filepath, false );

		do_action(
			'wpml_st_translation_file_removed',
			$filepath,
			$domain,
			$locale
		);
	}

	public function write( $domain, $locale, $content ) {
		$filepath = $this->getFilepath( $domain, $locale );
		$written  = $this->with_file_lock(
			$filepath,
			function () use ( $filepath, $content ) {
				if ( ! $this->save_file_command->run( $filepath, $content ) ) {
					return false;
				}

				return $this->write_php_file_from_mo( $filepath );
			}
		);

		if ( ! $written ) {
			return false;
		}

		$this->recordFile( $locale, $filepath, true );

		do_action(
			'wpml_st_translation_file_written',
			$filepath,
			$domain,
			$locale
		);

		return $filepath;
	}

	private function with_file_lock( $filepath, callable $operation ) {
		$temp_dir  = function_exists( 'get_temp_dir' ) ? \get_temp_dir() : sys_get_temp_dir();
		$lock_path = rtrim( $temp_dir, '/\\' ) . DIRECTORY_SEPARATOR . 'wpml-st-' . md5( $filepath ) . '.lock';
		$lock      = @fopen( $lock_path, 'c' );

		if ( false === $lock ) {
			return $operation();
		}

		flock( $lock, LOCK_EX );
		try {
			return $operation();
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	private function write_php_file_from_mo( $mo_filepath ) {
		if (
			'mo' !== $this->getFileExtension()
			|| ! class_exists( '\WP_Translation_File' )
			|| ! method_exists( 'WP_Translation_File', 'transform' )
		) {
			return true;
		}

		$content = \WP_Translation_File::transform( $mo_filepath, 'php' );
		if ( ! $content ) {
			return false;
		}

		$filepath = str_replace( '.mo', '.l10n.php', $mo_filepath );

		return $this->save_file_command->run( $filepath, $content );
	}

	public function add( $domain, $locale ) {
		if ( ! $this->maybeCreateSubdir() ) {
			return false;
		}

		$lang_code = $this->language_records->get_language_code( $locale );
		$strings   = $this->strings->get( $domain, $lang_code, $this->isPartialFile() );

		if ( ! $strings && $this->isPartialFile() ) {
			$this->remove( $domain, $locale );
			return false;
		}

		$file_content = $this->builder
			->set_language( $locale )
			->get_content( $strings );

		return $this->write( $domain, $locale, $file_content );
	}

	public function get( $domain, $locale ) {
		$filepath = $this->getFilepath( $domain, $locale );

		if ( $this->fileExists( $locale, $filepath ) && $this->filesystem->is_readable( $filepath ) ) {
			return $filepath;
		}

		return null;
	}

	private function fileExists( $locale, $filepath ) {
		$this->loadIndex( $locale );

		if ( ! isset( $this->index[ $locale ][ $filepath ] ) ) {
			$exists = (bool) $this->filesystem->is_file( $filepath );

			$this->index[ $locale ][ $filepath ]         = $exists;
			$this->index_learned[ $locale ][ $filepath ] = $exists;
			$this->persistIndexOnShutdown();
		}

		return $this->index[ $locale ][ $filepath ];
	}

	private function recordFile( $locale, $filepath, $exists ) {
		$this->loadIndex( $locale );

		$this->index[ $locale ][ $filepath ]         = $exists;
		$this->index_written[ $locale ][ $filepath ] = $exists;

		$this->persistIndex();
	}

	private function loadIndex( $locale ) {
		if ( ! isset( $this->index[ $locale ] ) ) {
			$stored                 = $this->indexStorage()->get( $locale );
			$this->index[ $locale ] = is_array( $stored ) ? $stored : [];
		}
	}

	private function persistIndexOnShutdown() {
		if ( $this->index_persist_hooked ) {
			return;
		}

		$this->index_persist_hooked = true;
		add_action( 'shutdown', [ $this, 'persistIndex' ], PHP_INT_MAX );
	}

	public function persistIndex() {
		foreach ( array_keys( $this->index_learned + $this->index_written ) as $locale ) {
			$stored = $this->indexStorage()->get( $locale );
			$stored = is_array( $stored ) ? $stored : [];

			$merged = $stored + ( $this->index_learned[ $locale ] ?? [] );
			$merged = array_merge( $merged, $this->index_written[ $locale ] ?? [] );

			$this->indexStorage()->save( $locale, $merged );
			$this->index[ $locale ] = $merged;
		}

		$this->index_learned = [];
		$this->index_written = [];
	}

	private function indexStorage() {
		if ( ! $this->index_storage ) {
			$this->index_storage = new WpTransientPerLanguage( self::INDEX_CACHE_ID . '_' . $this->getFileExtension() );
		}

		return $this->index_storage;
	}

	public function getFilepath( $domain, $locale ) {
		$domain = str_replace( [ '/', '\\' ], '-', $domain );
		$locale = str_replace( [ '/', '\\' ], '-', $locale );
		return $this->getSubdir() . '/' . strtolower( $domain ) . '-' . $locale . '.' . $this->getFileExtension();
	}

	public function handles( $domain ) {
		return $this->getDomains()->contains( $domain );
	}

	public static function getSubdir() {
		$subdir = WP_LANG_DIR . '/' . self::SUB_DIRECTORY;

		$site_id = get_current_blog_id();
		if ( $site_id > 1 ) {
			$subdir .= '/' . $site_id;
		}

		return $subdir;
	}

	abstract protected function getFileExtension();

	abstract public function isPartialFile();

	abstract protected function getDomains();
}

<?php

namespace WPML\ST\BackgroundTask;

use WPML\Auryn\Injector;
use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForLocaleCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use function WPML\Container\make;

class LoadExistingTranslationsTask extends AbstractTaskEndpoint {

	const LOCK_TIME   = 60;
	const MAX_RETRIES = 3;
	const BATCH_SIZE  = 1000;

	const HARVESTED_OPTION = 'wpml_st_harvested_locales';

	private static $ownsFollowUpStatusRecompute = false;

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload       = $task->getPayload();
		$languages     = isset( $payload['languages'] ) ? $payload['languages'] : [];
		$languageIndex = isset( $payload['languageIndex'] ) ? (int) $payload['languageIndex'] : 0;
		$offset        = isset( $payload['offset'] ) ? (int) $payload['offset'] : 0;

		if ( isset( $languages[ $languageIndex ] ) ) {
			$strings = $this->getStringsBatch( $offset );

			if ( count( $strings ) > 0 ) {
				$language = $languages[ $languageIndex ];
				$this->createLoadCommand()->run( $strings, $language['locale'], $language['code'] );
				$task->addCompletedCount( count( $strings ) );
				$payload['offset'] = $offset + self::BATCH_SIZE;
			} else {
				$payload['languageIndex'] = $languageIndex + 1;
				$payload['offset']        = 0;
			}

			$task->setPayload( $payload );
			$task->setRetryCount( 0 );
		}

		if ( ! isset( $languages[ $payload['languageIndex'] ] ) ) {
			$activeLocales = isset( $payload['activeLocales'] ) ? (array) $payload['activeLocales'] : [];
			update_option( self::HARVESTED_OPTION, array_values( $activeLocales ), false );

			$task->finish();
			UpdateStringsStatusTask::enqueue();
		}

		return $task;
	}

	public function getDescription( Collection $data ) {
		return __( 'Importing existing string translations for the new languages', 'wpml-string-translation' );
	}

	public function getTotalRecords( Collection $data ) {
		global $wpdb;

		$languages = $data->get( 'languages', [] );

		return count( $languages ) * (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings" );
	}

	public static function canBeEnqueued() {
		return class_exists( AbstractTaskEndpoint::class )
			&& class_exists( BackgroundTaskService::class )
			&& function_exists( 'WPML\Container\make' );
	}

	public static function ownsFollowUpStatusRecompute() {
		return self::$ownsFollowUpStatusRecompute;
	}

	public static function resetFollowUpStatusRecomputeOwnership() {
		self::$ownsFollowUpStatusRecompute = false;
	}

	public static function enqueue() {
		global $sitepress;

		$defaultCode   = $sitepress->get_default_language();
		$harvested     = array_values( array_map( 'strval', (array) get_option( self::HARVESTED_OPTION, [] ) ) );
		$languages     = [];
		$activeLocales = [];

		$skipCode = LanguageCode::isEnglish( $defaultCode ) ? $defaultCode : null;

		foreach ( $sitepress->get_active_languages() as $code => $language ) {
			if ( $code === $skipCode ) {
				continue;
			}
			$locale          = isset( $language['default_locale'] ) ? (string) $language['default_locale'] : (string) $code;
			$activeLocales[] = $locale;
			if ( ! in_array( $locale, $harvested, true ) ) {
				$languages[] = [
					'code'   => $code,
					'locale' => $locale,
				];
			}
		}
		$activeLocales = array_values( array_unique( $activeLocales ) );

		if ( ! $languages ) {
			return false;
		}

		$service = make( BackgroundTaskService::class );
		$task    = $service->add(
			make( static::class ),
			wpml_collect(
				[
					'languages'     => $languages,
					'languageIndex' => 0,
					'offset'        => 0,
					'activeLocales' => $activeLocales,
				]
			)
		);

		if ( null === $task ) {
			return false;
		}

		self::$ownsFollowUpStatusRecompute = true;

		return true;
	}

	private function getStringsBatch( $offset ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, context, gettext_context, value
				 FROM {$wpdb->prefix}icl_strings
				 ORDER BY id ASC
				 LIMIT %d OFFSET %d",
				self::BATCH_SIZE,
				$offset
			)
		);

		return array_map(
			function ( $row ) {
				$string = new StringItem( 'en', (string) $row->context, $row->gettext_context, (string) $row->value );
				$string->setId( (int) $row->id );

				return $string;
			},
			$rows
		);
	}

	private function createLoadCommand() {
		global $sitepress, $wpdb;

		$filesystem = wpml_get_filesystem();

		$injector = new Injector();
		$injector->defineParam( 'sitepress', $sitepress );
		$injector->defineParam( 'wpdb', $wpdb );
		$injector->defineParam( 'filesystem', $filesystem );
		$injector->delegate(
			\WP_Filesystem_Base::class,
			function () use ( $filesystem ) {
				return $filesystem;
			}
		);

		$mappings = include WPML_ST_PATH . '/StringTranslation/config-interface-mappings.php';
		foreach ( (array) $mappings as $interfaceClass => $implementationClass ) {
			$injector->alias( $interfaceClass, $implementationClass );
		}

		return $injector->make( LoadExistingStringTranslationsForLocaleCommandInterface::class );
	}
}

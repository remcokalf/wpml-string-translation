<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\MO\File\Manager;
use WPML\ST\MO\File\ManagerFactory;

class RegenerateDefaultMoForWpRouting implements \IWPML_St_Upgrade_Command {

	const DOMAIN = 'default';

	const PROGRESS_OPTION = 'wpml_st_4738_default_mo_progress';

	const TIME_BUDGET = 5;

	private $manager;

	private $getActiveLocales;

	private $wpdb;

	private $time;

	private $hasFiles;

	public function __construct(
		?Manager $manager = null,
		?callable $getActiveLocales = null,
		?\wpdb $wpdb = null,
		?callable $time = null,
		?callable $hasFiles = null
	) {
		$this->manager          = $manager;
		$this->getActiveLocales = $getActiveLocales ?: [ self::class, 'getDefaultActiveLocales' ];
		$this->wpdb             = $wpdb;
		$this->time             = $time ?: 'time';
		$this->hasFiles         = $hasFiles ?: [ Manager::class, 'hasFiles' ];
	}

	public function run() {
		if ( ! call_user_func( $this->hasFiles ) ) {
			return true;
		}

		if ( ! $this->hasWordPressDomainStrings() ) {
			return true;
		}

		$done    = $this->getProgress();
		$started = call_user_func( $this->time );
		$manager = $this->manager ?: ManagerFactory::create();

		foreach ( call_user_func( $this->getActiveLocales ) as $locale ) {
			if ( in_array( $locale, $done, true ) ) {
				continue;
			}

			if ( call_user_func( $this->time ) - $started >= self::TIME_BUDGET ) {
				$this->saveProgress( $done );

				return false;
			}

			try {
				$manager->add( self::DOMAIN, $locale );
			} catch ( \RuntimeException $e ) {
				$this->saveProgress( $done );

				return false;
			}

			$done[] = $locale;
			$this->saveProgress( $done );
		}

		delete_option( self::PROGRESS_OPTION );

		return true;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}

	public static function getDefaultActiveLocales() {
		$locales = \WPML\FP\Lst::pluck( 'default_locale', \WPML\Element\API\Languages::getActive() );

		return array_values( array_filter( is_array( $locales ) ? $locales : [] ) );
	}

	private function hasWordPressDomainStrings() {
		$wpdb = $this->wpdb ?: $GLOBALS['wpdb'];

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT EXISTS( SELECT 1 FROM {$wpdb->prefix}icl_strings WHERE context = %s )",
				\WPML\ST\DB\Mappers\StringsRetrieve::CONTEXT_WORDPRESS
			)
		);
	}

	private function getProgress() {
		$done = get_option( self::PROGRESS_OPTION, [] );

		return is_array( $done ) ? $done : [];
	}

	private function saveProgress( array $done ) {
		update_option( self::PROGRESS_OPTION, $done, false );
	}
}

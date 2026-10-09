<?php

namespace WPML\ST\Upgrade\Deferred;

class Runner {

	const OPTION_KEY = 'wpml_st_deferred_upgrade';
	const LOCK_NAME  = 'st_deferred_upgrade';

	const STEP_WP_SETTINGS_STRINGS = 'upgrade-wp-settings-strings';

	const STEP_SETTINGS_ROWS_MIGRATION = 'settings-rows-migration';

	const STEP_RECOMPUTE_STRING_STATUSES = 'recompute-string-statuses';

	private static $st_upgrade = null;

	public static function register( \WPML_ST_Upgrade $st_upgrade ) {
		self::$st_upgrade = $st_upgrade;
		add_action( 'admin_init', array( self::class, 'runOneStep' ), 30 );
	}

	public static function queue( $step_id ) {
		$pending = self::pendingSteps();
		if ( in_array( $step_id, $pending, true ) ) {
			return;
		}

		$pending[] = $step_id;
		update_option( self::OPTION_KEY, $pending, true );
	}

	public static function pendingSteps() {
		$pending = get_option( self::OPTION_KEY, array() );

		return is_array( $pending ) ? array_values( $pending ) : array();
	}

	public static function runOneStep() {
		if ( wp_doing_ajax() ) {
			return;
		}

		self::runOneStepNow();
	}

	public static function queueDeferredSteps() {
		if ( self::$st_upgrade ) {
			self::$st_upgrade->queue_deferred_steps();
		}
	}

	public static function runOneStepNow( $ignore_upgrade_in_progress = false ) {
		$pending = self::pendingSteps();
		if ( ! $pending ) {
			self::removeNotice();

			return false;
		}

		$runnable = array_values( array_filter( $pending, array( self::class, 'isRunnable' ) ) );

		if ( $runnable ) {
			self::addNotice( count( $runnable ) );
		}

		if ( ! $ignore_upgrade_in_progress
			&& get_transient( \WPML_ST_Upgrade::TRANSIENT_UPGRADE_IN_PROGRESS )
		) {
			return false;
		}

		$lock = \WPML\Container\make( \WPML\Utilities\AdvisoryLockFactory::class )->create( self::LOCK_NAME );
		if ( ! $lock->acquire( 0 ) ) {
			return false;
		}

		try {
			if ( count( $runnable ) < count( $pending ) ) {
				self::store( $runnable );
				if ( ! $runnable ) {
					return false;
				}
			}

			$step = (string) reset( $runnable );
			if ( ! self::execute( $step ) ) {
				return true;
			}

			self::store( array_values( array_diff( $runnable, array( $step ) ) ) );

			return true;
		} finally {
			$lock->release();
		}
	}

	private static function isRunnable( $step ) {
		if ( self::STEP_SETTINGS_ROWS_MIGRATION !== $step ) {
			return true;
		}

		$owner = '\\WPML\\Infrastructure\\WordPress\\Component\\SettingsStorage\\SettingsRowsMigration';

		return class_exists( $owner ) && $owner::canRunNow();
	}

	private static function store( array $pending ) {
		if ( $pending ) {
			update_option( self::OPTION_KEY, $pending, true );
		} else {
			delete_option( self::OPTION_KEY );
			self::removeNotice();
		}
	}

	private static function execute( $step ) {
		switch ( $step ) {
			case self::STEP_WP_SETTINGS_STRINGS:
				return self::$st_upgrade
					? self::$st_upgrade->run_deferred_command( \WPML\ST\Upgrade\Command\UpgradeWpSettingsStrings::class )
					: false;

			case self::STEP_RECOMPUTE_STRING_STATUSES:
				return self::$st_upgrade
					? self::$st_upgrade->run_deferred_command( \WPML\ST\Upgrade\Command\RecomputeStatusesZeroedByOutdatedHandler::class )
					: false;

			case self::STEP_SETTINGS_ROWS_MIGRATION:
				\WPML\Infrastructure\WordPress\Component\SettingsStorage\SettingsRowsMigration::runNow();

				return \WPML\Infrastructure\WordPress\Component\SettingsStorage\SettingsRowsMigration::isSettled();
		}

		return true;
	}

	private static function addNotice( $steps_left ) {
		wpml_get_admin_notices()->add_notice( new DeferredUpgradeNotice( $steps_left ) );
	}

	private static function removeNotice() {
		wpml_get_admin_notices()->remove_notice( DeferredUpgradeNotice::GROUP, DeferredUpgradeNotice::ID );
	}
}

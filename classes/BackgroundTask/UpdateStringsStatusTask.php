<?php

namespace WPML\ST\BackgroundTask;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use function WPML\Container\make;

class UpdateStringsStatusTask extends AbstractTaskEndpoint {

	const LOCK_TIME   = 300;
	const MAX_RETRIES = 3;

	public function runBackgroundTask( BackgroundTask $task ) {
		global $wpdb, $sitepress;

		$updater = new \WPML_ST_Bulk_Update_Strings_Status( $wpdb, array_keys( $sitepress->get_active_languages() ) );
		$updater->run();

		$task->addCompletedCount( 1 );
		$task->finish();

		return $task;
	}

	public function getDescription( Collection $data ) {
		return __( 'Updating string translation statuses', 'wpml-string-translation' );
	}

	public function getTotalRecords( Collection $data ) {
		return 1;
	}

	public static function canBeEnqueued() {
		return class_exists( AbstractTaskEndpoint::class )
			&& class_exists( BackgroundTaskService::class )
			&& function_exists( 'WPML\Container\make' );
	}

	public static function enqueue() {
		$service = make( BackgroundTaskService::class );
		$service->addOnce( make( static::class ), wpml_collect( [] ) );
	}
}

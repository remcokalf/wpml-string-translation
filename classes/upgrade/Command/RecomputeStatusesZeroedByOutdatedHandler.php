<?php

namespace WPML\ST\Upgrade\Command;

class RecomputeStatusesZeroedByOutdatedHandler implements \IWPML_St_Upgrade_Command {

	const OPTION_CHECKPOINT = 'wpml_st_status_recompute_checkpoint';

	const BUDGET_SECONDS = 10;

	const CHUNK_SIZE = 2000;

	private $wpdb;

	private $sitepress;

	private $budget_seconds;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress, $budget_seconds = null ) {
		$this->wpdb           = $wpdb;
		$this->sitepress      = $sitepress;
		$this->budget_seconds = null === $budget_seconds ? self::BUDGET_SECONDS : (int) $budget_seconds;
	}

	public function run() {
		$checkpoint = $this->read_checkpoint();

		if ( ! $checkpoint['max_id'] ) {
			$this->finish();

			return true;
		}

		$updater = $this->create_updater();
		$started = microtime( true );

		do {
			$from_id = $checkpoint['last_id'];
			$to_id = $updater->get_page_end_id( $from_id, self::CHUNK_SIZE );
			$to_id = $to_id > 0 ? min( $to_id, $checkpoint['max_id'] ) : $checkpoint['max_id'];

			$chunk = $updater->run_for_zeroed_range( $from_id, $to_id );

			$checkpoint['last_id'] = $to_id;
			$checkpoint['scanned'] += (int) $chunk['scanned'];
			$checkpoint['changed'] += (int) $chunk['changed'];

			if ( (int) $chunk['scanned'] > 0 ) {
				$this->log(
					'st_status_recompute',
					array(
						'scanned' => (int) $chunk['scanned'],
						'changed' => (int) $chunk['changed'],
						'last_id' => $to_id,
					)
				);
			}

			if ( $checkpoint['last_id'] >= $checkpoint['max_id'] ) {
				$this->log(
					'st_status_recompute_done',
					array(
						'scanned' => $checkpoint['scanned'],
						'changed' => $checkpoint['changed'],
						'last_id' => $checkpoint['last_id'],
					)
				);
				$this->finish();

				return true;
			}
		} while ( microtime( true ) - $started < $this->budget_seconds );

		update_option( self::OPTION_CHECKPOINT, $checkpoint, false );

		return false;
	}

	public function run_ajax() {
		return false;
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}

	private function read_checkpoint() {
		$stored = get_option( self::OPTION_CHECKPOINT, array() );

		if ( is_array( $stored ) && isset( $stored['max_id'], $stored['last_id'] ) ) {
			return array(
				'last_id' => (int) $stored['last_id'],
				'max_id'  => (int) $stored['max_id'],
				'scanned' => isset( $stored['scanned'] ) ? (int) $stored['scanned'] : 0,
				'changed' => isset( $stored['changed'] ) ? (int) $stored['changed'] : 0,
			);
		}

		return array(
			'last_id' => 0,
			'max_id'  => $this->create_updater()->get_max_string_id(),
			'scanned' => 0,
			'changed' => 0,
		);
	}

	private function finish() {
		delete_option( self::OPTION_CHECKPOINT );
	}

	private function create_updater() {
		return new \WPML_ST_Bulk_Update_Strings_Status(
			$this->wpdb,
			array_keys( $this->sitepress->get_active_languages() )
		);
	}

	protected function log( $id, array $data ) {
		do_action( 'wpml_st_status_recompute_progress', $id, $data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPML String Translation: ' . $id . ' ' . wp_json_encode( $data ) );
		}
	}
}

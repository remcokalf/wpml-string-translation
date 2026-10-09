<?php

class WPML_ST_Repair_Strings_Schema {

	const OPTION_HAS_RUN = 'wpml_st_repair_string_schema_has_run';

	private $upgrade_command;

	private $notices;

	private $args;

	private $db_error;

	private $has_run = array();

	public function __construct( WPML_Notices $notices, array $args, $db_error ) {
		$this->notices  = $notices;
		$this->args     = $args;
		$this->db_error = $db_error;
	}

	public function set_command( IWPML_St_Upgrade_Command $upgrade_command ) {
		$this->upgrade_command = $upgrade_command;
	}

	public function run() {
		$this->has_run = get_option( self::OPTION_HAS_RUN, array() );

		if ( ! $this->upgrade_command || array_key_exists( $this->get_command_id(), $this->has_run ) ) {
			$this->add_notice();
			return false;
		}

		if ( $this->run_upgrade_command() ) {
			return true;
		}

		$this->add_notice();
		return false;
	}

	private function run_upgrade_command() {
		if ( ! $this->acquire_lock() ) {
			return false;
		}

		$success                                  = $this->upgrade_command->run();
		$this->has_run[ $this->get_command_id() ] = true;
		update_option( self::OPTION_HAS_RUN, $this->has_run, false );

		$this->release_lock();

		return (bool) $success;
	}

	private function get_command_id() {
		return get_class( $this->upgrade_command );
	}

	private function acquire_lock() {
		if ( get_transient( WPML_ST_Upgrade::TRANSIENT_UPGRADE_IN_PROGRESS ) ) {
			return false;
		}

		set_transient( WPML_ST_Upgrade::TRANSIENT_UPGRADE_IN_PROGRESS, true, MINUTE_IN_SECONDS );
		return true;
	}

	private function release_lock() {
		delete_transient( WPML_ST_Upgrade::TRANSIENT_UPGRADE_IN_PROGRESS );
	}

	private function add_notice() {
		$support_url = \WPML\ST\OutboundLinks\OutboundLinks::to(
			'https://app.wpml.org/support',
			array(
				'medium'   => 'support',
				'campaign' => 'support',
			)
		);
		$text = '<p>' . sprintf(
			/* translators: Notice shown when WPML finds a problem in its database tables. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to WPML support. */
			esc_html__( 'We have detected a problem with some tables in the database. Please contact %1$sWPML support%2$s to get this fixed.', 'wpml-string-translation' ),
			'<a href="' . esc_url( $support_url ) . '" class="otgs-external-link" rel="noopener" target="_blank">',
			'</a>'
		) . '</p>';

		$text .= '<pre>' . $this->db_error . '</pre>';

		if ( $this->upgrade_command ) {
			$notice_id = $this->get_command_id();
		} else {
			$notice_id = 'default';
			$text     .= '<pre>' . print_r( $this->args, true ) . '</pre>';
		}

		$notice = $this->notices->create_notice( $notice_id, $text, __CLASS__ );
		$notice->set_hideable( true );
		$notice->set_css_class_types( array( 'notice-error' ) );
		$this->notices->add_notice( $notice );
	}
}

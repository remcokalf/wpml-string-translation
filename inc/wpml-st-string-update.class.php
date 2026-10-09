<?php

use WPML\ST\StringValue;

class WPML_ST_String_Update {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function update_string( $domain, $name, $old_value, $new_value, $force_complete = false ) {
		if ( $new_value != $old_value ) {
			$string = $this->get_initial_string( $name, $domain, $old_value, $new_value );
			$update_data = array( 'value' => $new_value );

			if ( StringValue::isReady() ) {
				$update_data['has_text'] = StringValue::hasTextFlag( $new_value );
			}

			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_strings',
				$update_data,
				array( 'id' => $string->id )
			);
			$is_widget = $domain === WPML_ST_WIDGET_STRING_DOMAIN;
			if ( $is_widget && $new_value ) {
				$this->update_widget_name( $string->name, $old_value, $new_value );
			}
			$this->handle_status_change( $string, $force_complete || $is_widget );

			do_action( 'wpml_st_update_string', $domain, $name, $old_value, $new_value, $force_complete, $string );
		}

		return isset( $string ) && isset( $string->id ) ? $string->id : null;
	}

	function sanitize_string( $string ) {
		return html_entity_decode( $string, ENT_QUOTES );
	}

	private function handle_status_change( $string, $force_complete ) {
		if ( $string->status == ICL_TM_COMPLETE || $string->status == ICL_STRING_TRANSLATION_PARTIAL ) {
			$new_status = $force_complete ? ICL_TM_COMPLETE : ICL_TM_NEEDS_UPDATE;
			foreach (
				array(
					'icl_string_translations' => 'string_id',
					'icl_strings'             => 'id',
				) as $table_name => $id_col
			) {
				$this->wpdb->update(
					$this->wpdb->prefix . $table_name,
					array( 'status' => $new_status ),
					array( $id_col => $string->id )
				);
			}
		}
	}

	private function get_initial_string( $name, $context, $old_value, $new_value ) {
		$string = $this->read_string_from_db( $name, $context );
		if ( ! $string ) {
			if ( $context !== WPML_ST_WIDGET_STRING_DOMAIN ) {
				icl_register_string( $context, $name, $new_value );
			} else {
				list( $res, $name ) = $this->update_widget_name( $name, $old_value, $new_value );
				if ( ! $res ) {
					icl_register_string( $context, $name, $new_value );
				}
			}
		}
		$string = $this->read_string_from_db( $name, $context );

		return $string;
	}

	private function read_string_from_db( $name, $context ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT id, value, status, name
				FROM {$wpdb->prefix}icl_strings
				WHERE context = %s
					AND name = %s
				LIMIT 1",
				$context,
				$name
			)
		);
	}

	private function update_widget_name( $name, $old_value, $new_value ) {
		$res = 0;
		if ( 0 === strpos( $name, 'widget title - ' ) ) {
			$name     = 'widget title - ' . md5( $new_value );
			$old_name = 'widget title - ' . md5( $old_value );

			if ( $this->read_string_from_db( $name, WPML_ST_WIDGET_STRING_DOMAIN ) ) {
				$old_string = $this->read_string_from_db( $old_name, WPML_ST_WIDGET_STRING_DOMAIN );
				if ( $old_string ) {
					$this->delete_old_widget_title_string_if_new_already_exists( $old_string );
				}
			} else {
				$res = $this->write_widget_update_to_db( WPML_ST_WIDGET_STRING_DOMAIN, $old_name, $name );
			}
		} elseif ( 0 === strpos( $name, 'widget body - ' ) ) {
			$name = 'widget body - ' . md5( $new_value );
			$res  = $this->write_widget_update_to_db(
				WPML_ST_WIDGET_STRING_DOMAIN,
				'widget body - ' . md5( $old_value ),
				$name
			);
		}

		return array( $res, $name );
	}

	private function write_widget_update_to_db( $context, $old_name, $new_name ) {

		return $this->wpdb->update(
			$this->wpdb->prefix . 'icl_strings',
			array(
				'name'                    => $new_name,
				'domain_name_context_md5' => md5( WPML_ST_WIDGET_STRING_DOMAIN . $new_name ),
			),
			array(
				'context' => $context,
				'name'    => $old_name,
			)
		);
	}

	private function delete_old_widget_title_string_if_new_already_exists( $string ) {
		$this->wpdb->delete( $this->wpdb->prefix . 'icl_string_translations', array( 'string_id' => $string->id ) );
		$this->wpdb->delete( $this->wpdb->prefix . 'icl_strings', array( 'id' => $string->id ) );
	}
}

<?php

namespace WPML\ST\TranslationJob;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML\ST\Batch\Translation\StringTranslations;
use WPML\FP\Str;

use function WPML\FP\spreadArgs;

class AdminTextHooks implements \IWPML_REST_Action {

	private $optionNames = null;

	public function add_hooks() {
		Hooks::onFilter( 'wpml_tm_adjust_translation_fields' )
			->then( spreadArgs( [ $this, 'setGroupsAndLabels' ] ) );
	}

	private function isAdminText( $slug ) {
		if ( null === $this->optionNames ) {
			$this->optionNames = array_keys( get_option( \WPML_Admin_Text_Functionality::TRANSLATABLE_NAMES_SETTING, [] ) );
		}

		return in_array( $slug, $this->optionNames, true );
	}

	public function setGroupsAndLabels( $fields ) {
		foreach ( $fields as $key => $field ) {
			if ( StringTranslations::isBatchField( $field ) ) {
				$fields[ $key ] = $this->processField( $field );
			}
		}

		return $fields;
	}

	private function processField( $field ) {
		$label = Obj::prop( 'title', $field );

		if ( $this->isAdminText( $label ) ) {
			$group = Obj::prop( 'title', $field );
		} else {
			$matches = Str::match( '/^\[(.*?)\](.*)$/', $label );
			if ( ! $matches ) {
				return $field;
			}
			list( , $group, $label ) = $matches;
			if ( ! $this->isAdminText( $group ) ) {
				return $field;
			}
		}

		$groups = $this->getTopLevelGroup( $group );
		$prefix = key( $groups );

		if ( $group === $label ) {
			$label = str_replace( $prefix, '', $label );
		} else {
			$group            = str_replace( $prefix, '', $group );
			$groups[ $group ] = apply_filters( 'wpml_labelize_string', $group, 'TranslationJob' );
		}

		$field['title'] = apply_filters( 'wpml_labelize_string', $label, 'TranslationJob' );
		$field['group'] = $groups;

		return $field;
	}

	private function getTopLevelGroup( $group ) {
		$prefixes = apply_filters( 'wpml_st_translation_job_admin_text_prefixes_to_groups', [] );

		foreach ( $prefixes as $prefix => $topLevelGroup ) {
			if ( Str::startsWith( $prefix, $group ) ) {
				return [ $prefix => $topLevelGroup ];
			}
		}

		return [ 'admin_texts' => 'Admin Texts' ];
	}

}

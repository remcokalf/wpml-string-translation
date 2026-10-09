<?php

namespace WPML\Ajax\ST\AdminText;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use function WPML\FP\partial;
use function WPML\FP\pipe;

class Register implements IHandler {

	private $adminTexts;

	public function __construct( \WPML_Admin_Texts $adminTexts ) {
		$this->adminTexts = $adminTexts;
	}

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( 'not allowed' );
		}

		$state      = $data->get( 'state' ) ? 'on' : '';
		$applyState = partial( [ self::class, 'flatToHierarchical' ], $state );

		$register = pipe(
			'wpml_collect',
			Fns::map( $applyState ),
			Fns::reduce( 'array_replace_recursive', [] ),
			[ $this->adminTexts, 'icl_register_admin_options' ]
		);

		$register( $data->get( 'selected', [] ) );

		return Either::right( true );
	}


	public static function flatToHierarchical( $state, $option ) {

		$makeArrayWithStringKey = function ( $value, $key ) {
			return [ (string) $key => $value ];
		};

		return \WPML_Admin_Texts::getKeysParts( $option )
								->reverse()
								->reduce( $makeArrayWithStringKey, $state );
	}
}

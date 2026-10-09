<?php


class WPML_ST_Upgrade_Command_Not_Found_Exception extends InvalidArgumentException {
	public function __construct( $class_name, $code = 0, $previous = null ) {
		$msg = sprintf( 'Class %s is not valid String Translation upgrade strategy', $class_name );
		parent::__construct( $msg, $code, $previous );
	}
}

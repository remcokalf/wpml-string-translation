<?php

namespace WPML\ST\Upgrade\Deferred;

class DeferredUpgradeNotice extends \WPML_Notice {

	const ID    = 'deferred-upgrade';
	const GROUP = 'st-upgrade';

	public function __construct( $steps_left ) {
		$text = sprintf(
			/* translators: %d is the number of database-update steps still pending; WPML runs one per admin page-load. */
			_n(
				'WPML is finalizing the database update — it completes automatically as you use the admin (%d step left).',
				'WPML is finalizing the database update — it completes automatically as you use the admin (%d steps left).',
				(int) $steps_left,
				'wpml-string-translation'
			),
			(int) $steps_left
		);

		parent::__construct( self::ID, $text, self::GROUP );

		$this->set_dismissible( false );
		$this->set_css_classes( 'warning' );
	}
}

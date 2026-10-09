<?php

namespace WPML\ST\WpSettings;

use IWPML_Action;
use function WPML\Container\make;

class Factory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return [
			make( DateTimeFormatsDefaultLocaleValues::class ),
		];
	}
}

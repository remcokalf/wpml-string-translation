<?php

namespace WPML\StringTranslation\Application\StringHtml\Command;

interface QueueGettextStringsToBeSetAsFrontendCommandInterface {
	public function run( array $gettextStrings );
}
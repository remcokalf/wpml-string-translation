<?php

namespace WPML\StringTranslation\Application\StringHtml\Command;

interface ProcessFrontendStringsObserverInterface {

	public function newFrontendStringsRegistered( array $stringIds );

}

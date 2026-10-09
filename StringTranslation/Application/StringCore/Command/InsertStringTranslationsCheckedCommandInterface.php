<?php

namespace WPML\StringTranslation\Application\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;

interface InsertStringTranslationsCheckedCommandInterface {
	public function runChecked( array $translations ): int;
}

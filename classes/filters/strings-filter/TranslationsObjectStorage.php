<?php

namespace WPML\ST\StringsFilter;

use WPML\ST\StringsFilter\StringEntity;

class TranslationsObjectStorage extends \SplObjectStorage {
	#[\ReturnTypeWillChange]
	public function getHash( $o ) {
		return implode(
			'_',
			[
				$o->getValue(),
				$o->getName(),
				$o->getDomain(),
				$o->getContext(),
			]
		);
	}
}

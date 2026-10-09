<?php

namespace WPML\ST\Gettext\Filters;

interface IFilter {

	public function filter( $translation, $text, $domain, $name = false );
}

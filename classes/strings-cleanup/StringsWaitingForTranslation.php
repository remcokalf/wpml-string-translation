<?php

namespace WPML\ST\StringsCleanup;

use WPML\Collect\Support\Collection;

class StringsWaitingForTranslation {

	const REQUEST_PARAM = 'include_waiting_for_translation';

	public static function statuses() {
		return [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_ATE_NEEDS_RETRY ];
	}

	public static function isRequested( Collection $data ) {
		return (bool) filter_var( $data->get( self::REQUEST_PARAM, false ), FILTER_VALIDATE_BOOLEAN );
	}
}

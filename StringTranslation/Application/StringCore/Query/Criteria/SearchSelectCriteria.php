<?php

namespace WPML\StringTranslation\Application\StringCore\Query\Criteria;

class SearchSelectCriteria {

	private $selectColumns;

	public function __construct(
		array $selectColumns = [
			'count',
			'id',
			'language',
			'context',
			'gettext_context',
			'name',
			'value',
			'status',
			'translation_priority',
			'component_type',
			'translation_statuses',
			'sources',
			'word_count',
		]
	) {
		$this->selectColumns = $selectColumns;
	}

	public function shouldSelect( string $column ): bool {
		return in_array( $column, $this->selectColumns );
	}
}
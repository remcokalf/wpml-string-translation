<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringPosition;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\DomainValueAndContextCriteria;
use WPML\StringTranslation\Application\StringCore\Query\FindByIdQueryInterface;

class FindByIdQuery implements FindByIdQueryInterface {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function execute( array $ids ): array {
		if ( count( $ids ) === 0 ) {
			return [];
		}

		$wpdb = $this->wpdb;
		$res  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, context, gettext_context, value, name FROM {$wpdb->prefix}icl_strings WHERE id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				array_map( 'intval', $ids )
			),
			ARRAY_A
		);

		$strings = [];

		foreach ( $res as $row ) {
			$string = new StringItem();

			$string->setId( $row['id'] );
			$string->setValue( $row['value'] );
			$string->setContext( $row['gettext_context'] );
			$string->setDomain( $row['context'] );
			$string->setName( $row['name'] );

			$strings[] = $string;
		}

		return $strings;
	}
}

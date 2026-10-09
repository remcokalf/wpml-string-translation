<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringsCommandInterface;
use WPML\ST\StringValue;

class InsertStringsCommand extends BulkActionBaseCommand implements InsertStringsCommandInterface {

	public function __construct(
		$wpdb
	) {
		$this->wpdb = $wpdb;
	}

	public function run( array $strings ) {
		foreach ( array_chunk( $strings, $this->chunk_size ) as $chunk ) {
			$changedOriginalStringIds = $this->findChangedOriginalStringIds( $chunk );
			$has_text_column = StringValue::isReady();

			$query                    = "INSERT IGNORE INTO {$this->wpdb->prefix}icl_strings "
				. '(`language`, `context`, `gettext_context`, `domain_name_context_md5`, `name`, '
				. '`value`, `status`, `string_type`, `component_id`, `component_type`'
				. ( $has_text_column ? ', `has_text`' : '' ) . ') VALUES ';

			$query .= implode( ',', array_map( array( $this, 'build_string_row' ), $chunk ) );
			$query .= ' ON DUPLICATE KEY UPDATE `value` = IF('
				. '`language` = VALUES(`language`), VALUES(`value`), `value`)';

			if ( $has_text_column ) {
				$query .= ', `has_text` = IF('
					. '`language` = VALUES(`language`), VALUES(`has_text`), `has_text`)';
			}

			$this->runCheckedBulkQuery(
				$query,
				'String Translation could not persist a strings batch.'
			);

			$this->markTranslationsAsNeedingUpdate( $changedOriginalStringIds );
		}
	}

	private function findChangedOriginalStringIds( array $strings ) : array {
		$stringsByHash = [];
		foreach ( $strings as $string ) {
			$stringsByHash[ $string->getDomainNameContextMd5() ][] = $string;
		}

		$hashes       = array_keys( $stringsByHash );
		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
		$hashes_sql = $this->wpdb->prepare( $placeholders, $hashes );
		if ( ! is_string( $hashes_sql ) || '' === $hashes_sql ) {
			throw new \RuntimeException( 'String Translation could not prepare an existing strings lookup.' );
		}

		$query = 'SELECT id, language, context, gettext_context, name, value, domain_name_context_md5 '
			. "FROM {$this->wpdb->prefix}icl_strings "
			. "WHERE domain_name_context_md5 IN ({$hashes_sql})";
		$rows = $this->wpdb->get_results( $query, ARRAY_A );
		if (
			! is_array( $rows )
			|| (
				isset( $this->wpdb->last_error )
				&& is_string( $this->wpdb->last_error )
				&& '' !== $this->wpdb->last_error
			)
		) {
			throw new \RuntimeException( 'String Translation could not load existing strings before saving a batch.' );
		}

		$changedIds = [];
		foreach ( $rows as $row ) {
			$hash = isset( $row['domain_name_context_md5'] )
				? (string) $row['domain_name_context_md5']
				: '';
			if ( '' === $hash || ! isset( $stringsByHash[ $hash ] ) ) {
				continue;
			}

			foreach ( $stringsByHash[ $hash ] as $string ) {
				$isSameIdentity = $string->getDomain() === (string) $row['context']
					&& (string) $string->getName() === (string) $row['name']
					&& (string) ( $string->getContext() ?? '' ) === (string) $row['gettext_context'];
				if (
					$isSameIdentity
					&& $string->getLanguage() === (string) $row['language']
					&& $string->getValue() !== (string) $row['value']
				) {
					$changedIds[] = (int) $row['id'];
				}
			}
		}

		return array_values( array_unique( $changedIds ) );
	}

	private function markTranslationsAsNeedingUpdate( array $stringIds ) {
		if ( count( $stringIds ) === 0 ) {
			return;
		}

		$query = "UPDATE {$this->wpdb->prefix}icl_string_translations SET status = "
			. (int) ICL_TM_NEEDS_UPDATE
			. ' WHERE string_id IN (' . implode( ',', array_map( 'intval', $stringIds ) ) . ')';

		$this->runCheckedBulkQuery(
			$query,
			'String Translation could not mark changed translations as needing update.'
		);
	}

	private function build_string_row( StringItem $string ) {
		$values = [
			$string->getLanguage(),
			$string->getDomain(),
			$string->getContext(),
			$string->getDomainNameContextMd5(),
			$string->getName(),
			$string->getValue(),
			$string->getStatus(),
			$string->getStringType(),
			$string->getComponentId(),
			$string->getComponentType(),
		];

		if ( ! StringValue::isReady() ) {
			return $this->wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %d, %d, %s, %d)', $values );
		}

		$values[] = StringValue::hasTextFlag( $string->getValue() );

		return $this->wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %d, %d, %s, %d, %d)', $values );
	}
}

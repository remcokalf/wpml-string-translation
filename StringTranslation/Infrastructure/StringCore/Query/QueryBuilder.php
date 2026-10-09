<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\FetchFiltersCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchSelectCriteria;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;
use WPML\StringTranslation\Infrastructure\Translation\TranslationStatusesParser;

abstract class QueryBuilder {

	protected function getPrefix(): string {
		global $wpdb;
		return $wpdb->prefix;
	}

	protected function prepareLike( $value ): string {
		global $wpdb;
		return $wpdb->esc_like( $value );
	}

	protected function buildPagination( SearchCriteria $criteria ): string {
		global $wpdb;

		return $wpdb->prepare( ' LIMIT %d OFFSET %d', $criteria->getLimit(), $criteria->getOffset() );
	}

	protected function buildWhereSql( $criteria ): string {
		$sqlParts = $this->getWhereSqlParts( $criteria );

		if ( count( $sqlParts ) === 0 ) {
			return '';
		}

		return ' WHERE ' . implode( ' AND ', $sqlParts );
	}

	protected function getSelectColumns( SearchSelectCriteria $dto ): string {
		$sql = [];

		if ( $dto->shouldSelect( 'id' ) ) {
			$sql[] = 'strings.id AS string_id';
		}
		if ( $dto->shouldSelect( 'language' ) ) {
			$sql[] = 'strings.language AS string_language';
		}
		if ( $dto->shouldSelect( 'context' ) ) {
			$sql[] = 'strings.context as domain';
		}
		if ( $dto->shouldSelect( 'gettext_context' ) ) {
			$sql[] = 'strings.gettext_context as context';
		}
		if ( $dto->shouldSelect( 'name' ) ) {
			$sql[] = 'strings.name';
		}
		if ( $dto->shouldSelect( 'value' ) ) {
			$sql[] = 'strings.value';
		}
		if ( $dto->shouldSelect( 'status' ) ) {
			$sql[] = 'strings.status';
		}
		if ( $dto->shouldSelect( 'translation_priority' ) ) {
			$sql[] = 'strings.translation_priority';
		}
		if ( $dto->shouldSelect( 'string_type' ) ) {
			$sql[] = 'strings.string_type';
		}
		if ( $dto->shouldSelect( 'component_id' ) ) {
			$sql[] = 'strings.component_id';
		}
		if ( $dto->shouldSelect( 'component_type' ) ) {
			$sql[] = 'strings.component_type';
		}
		if ( $dto->shouldSelect( 'sources' ) ) {
			$sql[] = 'string_positions.sources';
		}
		if ( $dto->shouldSelect( 'word_count' ) ) {
			$sql[] = 'word_count';
		}

		return implode( ',', $sql );
	}

	protected function shouldSelectOnlyAutoregistered( $criteria ) {
		$hasSource = in_array(
			$criteria->getSource(),
			[
				ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND,
				ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_BACKEND,
			],
			true
		);

		return $hasSource && ! $this->shouldSelectOnlyNotAutoregistered( $criteria );
	}

	protected function shouldSelectOnlyNotAutoregistered( $criteria ): bool {
		$kind                     = $criteria->getKind();
		$hasNotAutoregisteredKind = is_int( $kind ) && $kind === StringItem::STRING_TYPE_DEFAULT;

		return $hasNotAutoregisteredKind;
	}

	protected function shouldCheckForInProgressStatusInStringTranslations( $criteria ): bool {
		$statuses = $criteria->getTranslationStatuses();

		return (
			in_array( ICL_TM_WAITING_FOR_TRANSLATOR, $statuses ) &&
			in_array( ICL_TM_IN_PROGRESS, $statuses )
		);
	}

	protected function filterOutTranslationPartialStatusFromStrings( $criteria ): array {
		return array_filter(
			$criteria->getTranslationStatuses(),
			function( $status ) {
				return $status !== ICL_TM_IN_PROGRESS;
			}
		);
	}

	protected function getStringTranslationsSql( $criteria ): string {
		if ( ! $this->shouldCheckForInProgressStatusInStringTranslations( $criteria ) ) {
			return '';
		}

		return "
            LEFT JOIN {$this->getPrefix()}icl_string_translations string_translations
                ON strings.id = string_translations.string_id
		";
	}

	protected function getStringPositionsSql( $criteria ): string {
		return "
            LEFT JOIN (
                SELECT string_id,
                GROUP_CONCAT(kind SEPARATOR ', ') AS sources
                FROM {$this->getPrefix()}icl_string_positions
                GROUP BY string_id
            ) AS string_positions
            ON strings.id = string_positions.string_id
		";
	}

	protected function getGroupByColumns(): string {
		return '
            strings.id
        ';
	}

	protected function buildLanguagesCrossJoin( $criteria ): string {
		$buildLanguageSelect = function ( string $code ): string {
			global $wpdb;

			return $wpdb->prepare(
				'SELECT %s AS language_code',
				$code
			);
		};

		$codes = $this->settingsRepository->getAllTargetLanguagesBySource( $criteria->getSourceLanguageCode() );

		$languages = implode(
			' UNION ALL ',
			array_map(
				function ( $languageCode ) use ( $buildLanguageSelect ) {
					return $buildLanguageSelect( $languageCode );
				},
				$codes
			)
		);

		return "
            CROSS JOIN (
                {$languages}
            ) AS langs
        ";
	}

	private function getSourcesSql( $source ): array {
		if ( $source === ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND ) {
			return [ $source ];
		}

		return [
			ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_BACKEND,
			ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_AJAX,
			ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_REST,
		];
	}

	protected function getWhereSqlParts( $criteria ): array {
		global $wpdb;

		$selectOnlyAutoregistered    = $this->shouldSelectOnlyAutoregistered( $criteria );
		$selectOnlyNotAutoregistered = $this->shouldSelectOnlyNotAutoregistered( $criteria );

		$sqlParts   = [];
		$sqlParts[] = 'strings.string_package_id IS NULL';
		$sqlParts[] = 'strings.value != ""';

		if ( $selectOnlyAutoregistered || $selectOnlyNotAutoregistered ) {
			$kind       = $selectOnlyAutoregistered ? StringItem::STRING_TYPE_AUTOREGISTER : StringItem::STRING_TYPE_DEFAULT;
			$sqlParts[] = $wpdb->prepare( 'strings.string_type = %d', $kind );
		}

		if ( $criteria->getType() && $selectOnlyAutoregistered ) {
			$sqlParts[] = $wpdb->prepare( 'strings.component_type = %d', $criteria->getType() );
		}

		if ( $criteria->getSource() && $selectOnlyAutoregistered ) {
			$like = '%' . $this->prepareLike( ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND ) . '%';
			if ( $criteria->getSource() === ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND ) {
				$sqlParts[] = '(' . $wpdb->prepare( 'string_positions.sources IS NOT NULL AND string_positions.sources LIKE %s', $like ) . ')';
			} else {
				$sqlParts[] = '(' . $wpdb->prepare( 'string_positions.sources IS NULL OR string_positions.sources NOT LIKE %s', $like ) . ')';
			}
		}
		if ( $criteria->getDomain() ) {
			$domain = $criteria->getDomain();
			$escDomain = esc_html( $criteria->getDomain() );

			if ( $domain === $escDomain ) {
				$sqlParts[] = $wpdb->prepare('strings.context = %s', $domain );
			} else {
				$sqlParts[] = '(' . $wpdb->prepare('strings.context = %s OR strings.context = %s', $domain, $escDomain ) . ')';
			}
		}
		if ( $criteria->getTitle() ) {
			$title = '%' . $this->prepareLike( $criteria->getTitle() ) . '%';
			$escTitle = '%' . $this->prepareLike( esc_html( $criteria->getTitle() ) ) . '%';

			if ( $title === $escTitle ) {
				$sqlParts[] = '(' . $wpdb->prepare(
					'strings.value LIKE %s OR strings.name LIKE %s',
					$title,
					$title
				) . ')';
			} else {
				$sqlParts[] = '(' . $wpdb->prepare(
					'strings.value LIKE %s OR strings.value LIKE %s OR strings.name LIKE %s OR strings.name LIKE %s',
					$title,
					$escTitle,
					$title,
					$escTitle
				) . ')';
			}
		}
		if ( $criteria->getTranslationPriority() ) {
			$sqlParts[] = $wpdb->prepare( 'strings.translation_priority = %s', $criteria->getTranslationPriority() );
		}

		$defaultLanguageCode      = $this->settingsRepository->getDefaultLanguageCode();
		$englishSourceLanguage    = EnglishSourceLanguage::resolve(
			$this->settingsRepository->getActiveLanguageCodes(),
			$defaultLanguageCode
		);
		$isDefaultLanguageEnglish = $defaultLanguageCode === $englishSourceLanguage;
		$sourceLanguageCode       = $criteria->getSourceLanguageCode();
		$langCodesToShow          = $sourceLanguageCode ? [ $sourceLanguageCode ] : [];
		if ( ! $isDefaultLanguageEnglish && $sourceLanguageCode === $defaultLanguageCode ) {
			$langCodesToShow[] = $englishSourceLanguage;
		}

		if ( $langCodesToShow ) {
			$sqlParts[] = 'strings.language IN (' . wpml_prepare_in( $langCodesToShow ) . ')';
		}

		$stringStatuses = $this->shouldCheckForInProgressStatusInStringTranslations( $criteria ) && count( $criteria->getTranslationStatuses() ) === 2
			? $this->filterOutTranslationPartialStatusFromStrings( $criteria )
			: $criteria->getTranslationStatuses();
		$stringTranslationStatuses    = $this->shouldCheckForInProgressStatusInStringTranslations( $criteria ) ? [ ICL_TM_IN_PROGRESS ] : [];
		$hasStringStatuses            = count( $stringStatuses ) > 0;
		$hasStringTranslationStatuses = count( $stringTranslationStatuses ) > 0;
		if ( $hasStringStatuses || $hasStringTranslationStatuses ) {
			$stringsSql            = 'strings.status IN (' . wpml_prepare_in( $stringStatuses ) . ')';
			$stringTranslationsSql = 'string_translations.status IN (' . wpml_prepare_in( $stringTranslationStatuses ) . ')';

			if ( $hasStringStatuses && $hasStringTranslationStatuses ) {
				$sqlParts[] = '(' . $stringsSql . ' OR ' . $stringTranslationsSql . ')';
			} else if ( $hasStringTranslationStatuses ) {
				$sqlParts[] = $stringTranslationsSql;
			} else {
				$sqlParts[] = $stringsSql;
			}
		}

		if ( $criteria instanceof SearchCriteria && count( $criteria->getIds() ) > 0 ) {
			$sqlParts[] = 'strings.id IN (' . wpml_prepare_in( $criteria->getIds() ) . ')';
		}

		return $sqlParts;
	}
}

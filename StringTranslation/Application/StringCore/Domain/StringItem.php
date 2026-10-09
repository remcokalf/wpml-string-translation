<?php

namespace WPML\StringTranslation\Application\StringCore\Domain;

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;

class StringItem {

	const STRING_TYPE_DEFAULT = 0;
	const STRING_TYPE_AUTOREGISTER = 1;

	const COMPONENT_TYPE_UNKNOWN = 0;
	const COMPONENT_TYPE_PLUGIN  = 1;
	const COMPONENT_TYPE_THEME   = 2;
	const COMPONENT_TYPE_CORE    = 3;

	const EOT_CHARACTER = '\4';

	private $id;

	private $language;

	private $domain;

	private $context;

	private $value;

	private $status;

	private $name;

	private $domainNameContextMd5;

	private $componentId;

	private $componentType;

	private $stringType;

	private $positions = [];

	private $translations = [];

	public function __construct(
		string $language = '',
		string $domain = '',
		?string $context = null,
		string $value = '',
		int $status = ICL_TM_NOT_TRANSLATED,
		?string $name = null,
		?string $componentId = null,
		int $componentType = self::COMPONENT_TYPE_UNKNOWN,
		int $stringType = self::STRING_TYPE_DEFAULT
	) {
		$this->language      = $language;
		$this->domain        = $domain;
		$this->context       = $context;
		$this->value         = $value;
		$this->status        = $status;
		$this->componentId   = $componentId;
		$this->componentType = $componentType;
		$this->stringType    = $stringType;

		if ( ! $name ) {
			$name = md5( $value );
		}
		$this->name = (string) $name;

		$this->domainNameContextMd5 = md5( $domain . $name . $context );
	}

	public static function parseTextAndContextKey( string $textAndContext ): array {
		$res = explode( self::EOT_CHARACTER, $textAndContext );
		return count( $res ) > 1 ? $res : [ $res[0], null ];
	}

	public static function createTextAndContextKey( string $text, ?string $context = null ): string {
		return is_string( $context ) && strlen( $context ) > 0
			? $text . self::EOT_CHARACTER . $context
			: $text;
	}

	public function setId( int $id ) {
		$this->id = $id;
	}

	public function getId() {
		return $this->id;
	}

	public function hasId(): bool {
		return ! is_null( $this->id );
	}

	public function getLanguage(): string {
		return $this->language;
	}

	public function setLanguage( string $language ) {
		$this->language = $language;
	}

	public function getDomain(): string {
		return $this->domain;
	}

	public function setDomain( string $domain ) {
		$this->domain = $domain;
	}

	public function getContext() {
		return $this->context;
	}

	public function setContext( string $context ) {
		$this->context = $context;
	}

	public function getValue(): string {
		return $this->value;
	}

	public function setValue( string $value ) {
		$this->value = $value;
	}

	public static function filterOnlyTextFromValue( string $value ): string {
		return (string) trim( preg_replace( '/\s+/', ' ', strip_tags( html_entity_decode( $value ) ) ) );
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function setStatus( int $status ) {
		$this->status = $status;
	}

	public function refreshStatus( string $defaultLanguageCode, array $allLanguageCodes ): bool {
		if ( count( $allLanguageCodes ) === 0 || count( $this->translations ) === 0 ) {
			return false;
		}

		if ( ! LanguageCode::isEnglish( $defaultLanguageCode ) && $defaultLanguageCode !== $this->language ) {
			$allLanguageCodes[] = $defaultLanguageCode;
		}

		if ( in_array( $this->language, $allLanguageCodes ) ) {
			$allLanguageCodes = array_filter(
				$allLanguageCodes,
				function( $value ) {
					return $value !== $this->language;
				}
			);
		}

		$translatedLanguageCodes = array_map(
			function( StringTranslation $translation ) {
				return $translation->getLanguage();
			},
			$this->translations
		);

		$translatedLanguagesCount = count( array_intersect( $allLanguageCodes, $translatedLanguageCodes ) );

		if ( $translatedLanguagesCount === 0 ) {
			$this->status = count( $translatedLanguageCodes ) > 0
				? ICL_STRING_TRANSLATION_PARTIAL
				: ICL_STRING_TRANSLATION_NOT_TRANSLATED;
			return true;
		}

		if ( $translatedLanguagesCount < count( $allLanguageCodes ) ) {
			$this->status = ICL_STRING_TRANSLATION_PARTIAL;
			return true;
		}

		$this->status = ICL_STRING_TRANSLATION_COMPLETE;
		return true;
	}

	public function getName() {
		return $this->name;
	}

	public function setName( string $name ) {
		$this->name = $name;
	}

	public function getDomainNameContextMd5(): string {
		return $this->domainNameContextMd5;
	}

	public function setComponentId( ?string $componentId = null ) {
		$this->componentId = $componentId;
	}

	public function getComponentId() {
		return $this->componentId;
	}

	public function setComponentType( int $componentType ) {
		$this->componentType = $componentType;

		$types = [
			self::COMPONENT_TYPE_PLUGIN,
			self::COMPONENT_TYPE_CORE,
			self::COMPONENT_TYPE_THEME,
		];

		if ( ! in_array( $this->componentType, $types ) ) {
			$this->componentType = self::COMPONENT_TYPE_UNKNOWN;
		}
	}

	public function getComponentType(): int {
		return $this->componentType;
	}

	public function setStringType( int $stringType ) {
		$this->stringType = $stringType;
	}

	public function getStringType(): int {
		return $this->stringType;
	}

	public function addPosition( StringPosition $position ) {
		$this->positions[] = $position;
	}

	public function getPositions(): array {
		return $this->positions;
	}

	public function unsetPositions() {
		$this->positions = [];
	}

	public function getNewPositions(): array {
		return array_filter(
			$this->getPositions(),
			function( $position ) {
				return ! $position->hasId();
			}
		);
	}

	public function getDomainValueAndContextKey(): string {
		return $this->getDomain() . $this->getValue() . ( $this->getContext() ?? '' );
	}

	public function addTranslation( StringTranslation $translation ) {
		$this->translations[] = $translation;
	}

	public function getTranslations(): array {
		return $this->translations;
	}
}

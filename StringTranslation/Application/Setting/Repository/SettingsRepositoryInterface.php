<?php

namespace WPML\StringTranslation\Application\Setting\Repository;

interface SettingsRepositoryInterface {
	const AUTOREGISTER_STRINGS_TYPE_ONLY_VIEWED_BY_ADMIN = 0;
	const AUTOREGISTER_STRINGS_TYPE_VIEWED_BY_ALL_USERS = 1;
	const AUTOREGISTER_STRINGS_TYPE_DISABLED = 2;

	const WAS_FRONTEND_VISITED_KEY = 'was_frontend_visited_key';

	public function canProcessQueueInCurrentRequest(): bool;
	public function isAutoregisterStringsTypeOnlyViewedByAdmin(): bool;
	public function isAutoregisterStringsTypeViewedByAllUsers(): bool;
	public function isAutoregisterStringsTypeDisabled(): bool;
	public function setAutoregisterStringsTypeSetting( $value );
	public function getAutoregisterStringsTypeSetting(): int;

	public function getVisibleColumns(): array;
	public function setShouldRegisterBackendStringsSetting( bool $shouldRegisterBackendStrings );
	public function getShouldRegisterBackendStringsSetting(): bool;
	public function setNewTranslationsWereLoadedSetting();
	public function unsetNewTranslationsWereLoadedSetting();
	public function wereNewTranslationsLoaded(): bool;
	public function saveKeyToSettings( string $keyName, $value = 1 );
	public function removeKeyFromSettings( string $keyName );
	public function hasKeyInSettings( string $keyName ): bool;
	public function getKeyValueFromSettings( string $keyName ): string;
	public function getIsCurrentUserAdmin(): bool;
	public function isAdminViewingFrontendPage(): bool;
	public function setIsAutoregistrationEnabled( bool $isAutoregistrationEnabled );

	public function setVisibleColumns( array $columns );

	public function getIsAutoregistrationEnabled(): bool;
	public function getCurrentLanguage(): string;
	public function getActiveLanguageCodes(): array;
	public function getDefaultLanguageCode(): string;
	public function getDefaultLanguageLocaleCode(): string;

	public function getEnglishSourceLanguageCode(): string;
	public function getActiveSecondaryLanguageCodes(): array;
	public function getActiveSecondaryLanguageLocales(): array;
	public function getActiveSecondaryLanguageLocalePairs(): array;
	public function getStringHarvestLanguageLocalePairs(): array;
	public function getLanguageDetails( string $languageCode ): array;
	public function isCronRequest(): bool;
	public function shouldNotAutoregisterStringsFromCurrentUrl(): bool;
	public function deleteCache();
	public function updateIfIsCurrentUserAdminCache();
	public function switchToLocale( string $locale, array $domainsToAllowReloadTranslations = [], $languageCode = null );
	public function restorePreviousLocale();

	public function resolveLanguageCodeFromLocale( string $locale ): string;

	public function getAllTargetLanguagesBySource( $sourceLanguageCode ): array;
	public function getLanguageForDomain( string $domain ): string;
	public function setLanguageForDomain( string $domain, string $language );
	public function setMaxQueuedFrontendStringsCount( int $maxQueuedFrontendStringsCount );
	public function getMaxQueuedFrontendStringsCount(): int;
	public function isStringTrackingEnabled(): bool;
	public function enableStringTracking();
	public function disableStringTracking();
	public function setDetectStringsInJS( int $detectStringsInJS );
}

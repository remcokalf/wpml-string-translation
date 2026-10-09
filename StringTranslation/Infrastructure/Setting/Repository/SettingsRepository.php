<?php

namespace WPML\StringTranslation\Infrastructure\Setting\Repository;

use WPML\FP\Str;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\UrlRepositoryInterface;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;
use WPML\ST\MO\Hooks\PreloadThemeMoFile;

class SettingsRepository implements SettingsRepositoryInterface {

	const STRING_TRACKING_SETTINGS_KEY = 'track_strings';

	const DETECT_JS_STRINGS = 'detect_js_strings';

	const AUTOREGISTER_STRINGS_ENABLED_TIME_KEY = 'autoregister_strings_enabled_time';

	const AUTOREGISTER_STRINGS_TIMEOUT = 2 * HOUR_IN_SECONDS;

	const MAX_QUEUED_FRONTEND_STRINGS_COUNT = 2500;

	private $sitepress;

	private $wpLocaleSwitches = [];

	private $urlRepository;

	private $settings;

	private $isAdmin;

	private $isAutoregistrationEnabled = false;

	private $shouldSkipAutoregistrationForCurrentLanguage;

	private $englishSourceLanguageCode = [];

	private $shouldNotAutoregisterStringsFromCurrentUrl;

	private $maxQueuedFrontendStringsCount = self::MAX_QUEUED_FRONTEND_STRINGS_COUNT;

	public function __construct(
		$sitepress,
		UrlRepositoryInterface $urlRepository
	) {
		$this->sitepress     = $sitepress;
		$this->urlRepository = $urlRepository;
	}

	public function canProcessQueueInCurrentRequest(): bool {
		$shouldProcessOnCurrentUrl = (
			$this->urlRepository->isCurrentPageWpmlDashboard() ||
			$this->urlRepository->isCurrentPageStDashboard()
		);

		return $shouldProcessOnCurrentUrl && is_admin() && $this->getIsCurrentUserAdmin();
	}

	private function getIsAdminFromCapabilities(): bool {
		return function_exists('current_user_can') ? current_user_can( 'manage_options' ) : false;
	}

	public function isAutoregisterStringsTypeOnlyViewedByAdmin(): bool {
		return $this->getAutoregisterStringsTypeSetting() === SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_ONLY_VIEWED_BY_ADMIN;
	}

	public function isAutoregisterStringsTypeViewedByAllUsers(): bool {
		return $this->getAutoregisterStringsTypeSetting() === SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_VIEWED_BY_ALL_USERS;
	}

	public function isAutoregisterStringsTypeDisabled(): bool {
		return $this->getAutoregisterStringsTypeSetting() === SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED;
	}

	public function setAutoregisterStringsTypeSetting( $value ) {
		$allowedValues = [
			SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_ONLY_VIEWED_BY_ADMIN,
			SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_VIEWED_BY_ALL_USERS,
			SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED,
		];

		$value = (int)$value;

		if ( ! in_array( $value, $allowedValues ) ) {
			return;
		}

		$isEnabled = $value !== SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED;

		$settings = $this->getSettings();
		$settings['autoregister_strings'] = $value;

		if ( $isEnabled ) {
			$settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ] = time();
		} else {
			unset( $settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ] );
		}

		$this->saveSettings( $settings );
		$this->updateWpmlSettingToPreloadThemeMoFilesAutomatically( $isEnabled );
	}

	private function updateWpmlSettingToPreloadThemeMoFilesAutomatically( bool $isEnabled ) {
		$this->sitepress->set_setting(
			PreloadThemeMoFile::SETTING_KEY,
			$isEnabled ? PreloadThemeMoFile::SETTING_ENABLED_FOR_LOAD_TEXT_DOMAIN : PreloadThemeMoFile::SETTING_DISABLED
		);
		$this->sitepress->save_settings();
	}

	public function getAutoregisterStringsTypeSetting(): int {
		$settings = $this->getSettings();
		if ( ! isset( $settings['autoregister_strings'] ) ) {
			$settings['autoregister_strings'] = SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED;
			$this->saveSettings( $settings );
		}

		$type = (int) $settings['autoregister_strings'];

		if ( $type === SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED ) {
			return $type;
		}

		return $this->endAutoregisterStringsModeIfHorizonPassed( $type );
	}

	private function endAutoregisterStringsModeIfHorizonPassed( int $type ): int {
		$settings  = $this->getSettings();
		$enabledAt = isset( $settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ] )
			? (int) $settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ]
			: 0;

		if ( $enabledAt <= 0 ) {
			$settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ] = time();
			$this->saveSettings( $settings );

			return $type;
		}

		if ( time() - $enabledAt < self::AUTOREGISTER_STRINGS_TIMEOUT ) {
			return $type;
		}

		$settings['autoregister_strings'] = SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED;
		unset( $settings[ self::AUTOREGISTER_STRINGS_ENABLED_TIME_KEY ] );
		$this->saveSettings( $settings );
		$this->updateWpmlSettingToPreloadThemeMoFilesAutomatically( false );

		return SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_DISABLED;
	}

	public function getVisibleColumns(): array {
		$settings = $this->getSettings();
		if ( ! isset( $settings['visible_columns'] ) || ! is_array( $settings['visible_columns'] ) ) {
			$settings['visible_columns'] = array('title' => true, 'name' => true, 'domain' => true, 'source' => true, 'type' => false);
			$this->saveSettings( $settings );
		}

		return $settings['visible_columns'];
	}


	public function setShouldRegisterBackendStringsSetting( bool $shouldRegisterBackendStrings ) {
		$settings = $this->getSettings();
		$settings['autoregister_strings_should_register_backend_strings'] = $shouldRegisterBackendStrings;
		$this->saveSettings( $settings );
	}

	public function getShouldRegisterBackendStringsSetting(): bool {
		$settings = $this->getSettings();
		if ( ! isset( $settings['autoregister_strings_should_register_backend_strings'] ) ) {
			$settings['autoregister_strings_should_register_backend_strings'] = false;
			$this->saveSettings( $settings );
		}

		return $settings['autoregister_strings_should_register_backend_strings'];
	}

	public function setNewTranslationsWereLoadedSetting() {
		$settings = $this->getSettings();
		$settings['autoregister_strings_were_new_translations_loaded'] = true;
		$this->saveSettings( $settings );
	}

	public function unsetNewTranslationsWereLoadedSetting() {
		$settings = $this->getSettings();
		unset( $settings['autoregister_strings_were_new_translations_loaded'] );
		$this->saveSettings( $settings );
	}

	public function wereNewTranslationsLoaded(): bool {
		$settings = $this->getSettings();
		if ( ! isset( $settings['autoregister_strings_were_new_translations_loaded'] ) ) {
			return false;
		}

		return $settings['autoregister_strings_were_new_translations_loaded'];
	}

	public function saveKeyToSettings( string $keyName, $value = 1 ) {
		$settings = $this->getSettings();
		$settings[ $keyName ] = $value;
		$this->saveSettings( $settings );
	}

	public function removeKeyFromSettings( string $keyName ) {
		$settings = $this->getSettings();
		unset( $settings[ $keyName ] );
		$this->saveSettings( $settings );
	}

	public function hasKeyInSettings( string $keyName ): bool {
		$settings = $this->getSettings();
		return array_key_exists( $keyName, $settings );
	}

	public function getKeyValueFromSettings( string $keyName ): string {
		$settings = $this->getSettings();
		return isset( $settings[ $keyName ] ) ? (string) $settings[ $keyName ] : '';
	}

	private function getSettings() {
		if ( ! is_null( $this->settings ) ) {
			return $this->settings;
		}

		$this->settings = $this->sitepress->get_setting( 'st' );
		if ( ! is_array( $this->settings ) ) {
			$this->settings = [];
		}

		return $this->settings;
	}

	private function saveSettings( $settings ) {
		$this->sitepress->set_setting( 'st', $settings );
		$this->sitepress->save_settings();

		$this->settings = $settings;
	}

	public function getIsCurrentUserAdmin(): bool {
		if ( is_null( $this->isAdmin ) ) {
			$this->updateIfIsCurrentUserAdminCache();
		}

		return $this->isAdmin;
	}

	public function isAdminViewingFrontendPage(): bool {
		return $this->getIsCurrentUserAdmin() && ! is_admin();
	}

	public function setIsAutoregistrationEnabled( bool $isAutoregistrationEnabled ) {
		$this->isAutoregistrationEnabled = $isAutoregistrationEnabled;
	}

	public function getIsAutoregistrationEnabled(): bool {
		return $this->isAutoregistrationEnabled;
	}

	private function shouldSkipAutoregistrationForCurrentLanguage(): bool {
		if ( ! is_null( $this->shouldSkipAutoregistrationForCurrentLanguage ) ) {
			return $this->shouldSkipAutoregistrationForCurrentLanguage;
		}

		$this->shouldSkipAutoregistrationForCurrentLanguage = $this->getEnglishSourceLanguageCode() === $this->sitepress->get_current_language();

		return $this->shouldSkipAutoregistrationForCurrentLanguage;
	}

	public function getCurrentLanguage(): string {
		return $this->sitepress->get_current_language();
	}

	private function getActiveLanguages(): array {
		return $this->sitepress->get_active_languages();
	}

	public function getActiveLanguageCodes(): array {
		return array_keys( $this->getActiveLanguages() );
	}

	public function getDefaultLanguageCode(): string {
		return $this->sitepress->get_default_language() ?: '';
	}

	public function getDefaultLanguageLocaleCode(): string {
		return (string) $this->sitepress->get_locale_from_language_code( $this->getDefaultLanguageCode() );
	}

	public function getEnglishSourceLanguageCode(): string {
		$activeCodes = $this->getActiveLanguageCodes();
		$defaultCode = $this->getDefaultLanguageCode();
		$key         = implode( ',', $activeCodes ) . '|' . $defaultCode;

		if ( ! isset( $this->englishSourceLanguageCode[ $key ] ) ) {
			$this->englishSourceLanguageCode = [ $key => EnglishSourceLanguage::resolve( $activeCodes, $defaultCode ) ];
		}

		return $this->englishSourceLanguageCode[ $key ];
	}

	public function getActiveSecondaryLanguageCodes(): array {
		$activeLanguageCodes = $this->getActiveLanguageCodes();
		$defaultLanguageCode = $this->getDefaultLanguageCode();

		$languageCodes = [];
		foreach ( $activeLanguageCodes as $activeLanguageCode ) {
			if ( $activeLanguageCode === $defaultLanguageCode ) {
				continue;
			}

			$languageCodes[] = $activeLanguageCode;
		}

		return $languageCodes;
	}

	public function getActiveSecondaryLanguageLocales(): array {
		$activeLanguages       = $this->getActiveLanguages();
		$defaultLanguageLocale = $this->getDefaultLanguageLocaleCode();

		$locales = [];
		foreach ( $activeLanguages as $activeLanguageName => $activeLanguageData ) {
			if ( $activeLanguageData['default_locale'] === $defaultLanguageLocale ) {
				continue;
			}

			$locales[] = $activeLanguageData['default_locale'];
		}

		return $locales;
	}

	public function getActiveSecondaryLanguageLocalePairs(): array {
		$defaultLanguageCode = $this->getDefaultLanguageCode();
		$pairs               = [];

		foreach ( $this->getActiveLanguages() as $languageCode => $languageData ) {
			if (
				$languageCode === $defaultLanguageCode
				|| ! isset( $languageData['default_locale'] )
				|| ! is_string( $languageData['default_locale'] )
				|| '' === $languageData['default_locale']
			) {
				continue;
			}

			$pairs[] = [
				'languageCode' => (string) $languageCode,
				'locale'       => $languageData['default_locale'],
			];
		}

		return $pairs;
	}

	public function getStringHarvestLanguageLocalePairs(): array {
		$pairs       = $this->getActiveSecondaryLanguageLocalePairs();
		$defaultCode = $this->getDefaultLanguageCode();

		if ( '' === $defaultCode || \WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode::isEnglish( $defaultCode ) ) {
			return $pairs;
		}

		$defaultLocale = $this->getDefaultLanguageLocaleCode();
		if ( '' === $defaultLocale ) {
			return $pairs;
		}

		array_unshift(
			$pairs,
			[
				'languageCode' => $defaultCode,
				'locale'       => $defaultLocale,
			]
		);

		return $pairs;
	}

	public function getLanguageDetails( string $languageCode ): array {
		$details = $this->sitepress->get_language_details( $languageCode );
		$flagUrl = $this->sitepress->get_flag_url( $languageCode );

		return [
			'languageCode'     => $languageCode,
			'languageFullName' => $details['display_name'],
			'languageFlagUrl'  => $flagUrl,
		];
	}

	public function isCronRequest(): bool {
		return defined('DOING_CRON') && DOING_CRON;
	}

	private function isCliRequest(): bool {
		return defined('WP_CLI' ) && WP_CLI;
	}

	public function shouldNotAutoregisterStringsFromCurrentUrl(): bool {
		if ( ! is_null( $this->shouldNotAutoregisterStringsFromCurrentUrl ) ) {
			return $this->shouldNotAutoregisterStringsFromCurrentUrl;
		}

		if ( $this->shouldSkipAutoregistrationForCurrentLanguage() ) {
			return $this->shouldNotAutoregisterStringsFromCurrentUrl = true;
		}

		if ( $this->isCronRequest() || $this->isCliRequest() ) {
			return $this->shouldNotAutoregisterStringsFromCurrentUrl = false;
		}

		$url      = $_SERVER['REQUEST_URI'];
		$fullUrl  = urldecode( $url );
		$urlParts = explode( '?', $url );
		$url      = $urlParts[0];
		$maybeExt = pathinfo( $url, PATHINFO_EXTENSION );

		$isLoadingStaticFile = false;
		if ( is_string( $maybeExt ) && Str::len( $maybeExt ) > 0 ) {
			if ( $maybeExt !== 'php' && $maybeExt !== 'html' ) {
				$isLoadingStaticFile = true;
			}
		}

		if ( $isLoadingStaticFile ) {
			return $this->shouldNotAutoregisterStringsFromCurrentUrl = true;
		}

		if ( ! $this->urlRepository->isFrontendRequest() && ! $this->getShouldRegisterBackendStringsSetting() ) {
			return $this->shouldNotAutoregisterStringsFromCurrentUrl = true;
		}

		$restPrefix = trim( function_exists( 'rest_get_url_prefix' ) ? (string) rest_get_url_prefix() : '', '/' );
		$restPrefix = '' === $restPrefix ? 'wp-json' : $restPrefix;

		$this->shouldNotAutoregisterStringsFromCurrentUrl = (
			$url === '/' . $restPrefix . '/' ||
			Str::startsWith( '/wp-cron', $url )
		);

		return $this->shouldNotAutoregisterStringsFromCurrentUrl;
	}

	public function deleteCache() {
		$this->settings = null;
		$this->shouldSkipAutoregistrationForCurrentLanguage = null;
		$this->shouldNotAutoregisterStringsFromCurrentUrl = null;
	}

	public function updateIfIsCurrentUserAdminCache() {
		$this->isAdmin = $this->getIsAdminFromCapabilities();
	}

	public function switchToLocale( string $locale, array $domainsToAllowReloadTranslations = [], $languageCode = null ) {
		if ( ! is_string( $languageCode ) || '' === $languageCode ) {
			$languageCode = $this->getActiveLanguageCodeForLocale( $locale );
		}

		if ( '' !== $languageCode ) {
			$this->sitepress->switch_lang( $languageCode );
		}

		$this->wpLocaleSwitches[] = (bool) switch_to_locale( $locale );

		global $l10n_unloaded;
		foreach ( array_unique( $domainsToAllowReloadTranslations ) as $domain ) {
			unset( $l10n_unloaded[ $domain ] );
		}
	}

	public function restorePreviousLocale() {
		if ( array_pop( $this->wpLocaleSwitches ) ) {
			restore_previous_locale();
		}
		$this->sitepress->switch_lang();
	}

	public function resolveLanguageCodeFromLocale( string $locale ): string {
		$code = '';
		if ( method_exists( $this->sitepress, 'get_language_code_from_locale' ) ) {
			$code = (string) $this->sitepress->get_language_code_from_locale( $locale );
		}

		return '' !== $code ? $code : $this->getDefaultLanguageCode();
	}

	private function getActiveLanguageCodeForLocale( string $locale ): string {
		foreach ( $this->getActiveLanguages() as $languageCode => $languageData ) {
			if ( isset( $languageData['default_locale'] ) && $languageData['default_locale'] === $locale ) {
				return (string) $languageCode;
			}
		}

		return '';
	}

	public function getAllTargetLanguagesBySource( $sourceLanguageCode ): array {
		$activeLanguageCodes = $this->getActiveLanguageCodes();

		if ( $sourceLanguageCode === $this->getEnglishSourceLanguageCode() ) {
			return array_filter(
				$activeLanguageCodes,
				function ( $languageCode ) use ( $sourceLanguageCode ) {
					return $languageCode !== $sourceLanguageCode;
				}
			);
		}

		return $activeLanguageCodes;
	}

	public function getLanguageForDomain( string $domain ): string {
		$settings = $this->getSettings();
		$key      = 'lang_of_domain';

		return ( isset( $settings[ $key ] ) && isset( $settings[ $key ][ $domain ] ) )
			? $settings[ $key ][ $domain ]
			: $this->getEnglishSourceLanguageCode();
	}

	public function setLanguageForDomain( string $domain, string $language ) {
		$settings = $this->getSettings();
		$key      = 'lang_of_domain';

		if ( ! isset( $settings[ $key ] ) ) {
			$settings[ $key ] = [];
		}

		$settings[ $key ][ $domain ] = $language;
		$this->saveSettings( $settings );
	}

	public function setMaxQueuedFrontendStringsCount( int $maxQueuedFrontendStringsCount ) {
		$this->maxQueuedFrontendStringsCount = $maxQueuedFrontendStringsCount;
	}

	public function getMaxQueuedFrontendStringsCount(): int {
		return $this->maxQueuedFrontendStringsCount;
	}

	public function isStringTrackingEnabled(): bool {
		$settings = $this->getSettings();
		$key      = self::STRING_TRACKING_SETTINGS_KEY;

		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return false;
		}

		return (bool) $settings[ $key ];
	}

	public function enableStringTracking() {
		$settings = $this->getSettings();
		$settings[self::STRING_TRACKING_SETTINGS_KEY] = 1;
		$this->saveSettings( $settings );
	}

	public function disableStringTracking() {
		$settings = $this->getSettings();
		$settings[self::STRING_TRACKING_SETTINGS_KEY] = 0;
		$this->saveSettings( $settings );
	}

	public function setVisibleColumns( array $columns ) {

		$filteredColumns = array_filter(
			$columns,
			function ( $value,$column ) {
				return in_array( $column, [ 'title', 'name', 'domain','source','type' ], true ) && is_bool( $value );
			},
			ARRAY_FILTER_USE_BOTH
		);

		$settings = $this->getSettings();

		$settings['visible_columns'] = $filteredColumns;
		$this->saveSettings( $settings );
	}

	public function setDetectStringsInJS( int $detectStringsInJS ) {
		$settings = $this->getSettings();
		$settings[ self::DETECT_JS_STRINGS ] = $detectStringsInJS;
		$this->saveSettings( $settings );
	}

	public function getDetectStringsInJS(): bool {
		$settings = $this->getSettings();

		return (bool) ( $settings[ self::DETECT_JS_STRINGS ] ?? false );
	}
}

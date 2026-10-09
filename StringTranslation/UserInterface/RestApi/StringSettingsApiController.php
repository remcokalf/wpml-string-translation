<?php

namespace WPML\StringTranslation\UserInterface\RestApi;

use WP_REST_Request;
use WPML\Rest\Adaptor;
use WPML\StringTranslation\Application\Setting\Repository\PluginRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;

class StringSettingsApiController extends AbstractController {

	const ROUTE = 'strings/settings';

	private $settingsRepository;

	private $pluginRepository;

	public function __construct(
		Adaptor $adaptor,
		SettingsRepositoryInterface $settingsRepository,
		PluginRepositoryInterface $pluginRepository
	) {
		parent::__construct( $adaptor );
		$this->settingsRepository = $settingsRepository;
		$this->pluginRepository   = $pluginRepository;
	}

	public function get_routes() {
		return [
			[
				'route' => self::ROUTE,
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'post' ],
					'args'     => [
						'autoregisterType'             => [
							'type'    => 'integer',
							'default' => 0,
						],
						'shouldRegisterBackendStrings' => [
							'type'    => 'integer',
							'default' => 0,
						],
						'setNoticeThatCachePluginCanBlockAutoregisterAsDismissed' => [
							'type'    => 'integer',
							'default' => 0,
						],
						'setDetectStringsInJS'         => [
							'type'    => 'integer',
							'default' => 0,
						],
					],
				],
			],
			[
				'route' => self::ROUTE,
				'args'  => [
					'methods'  => 'GET',
					'callback' => [ $this, 'get' ],
				],
			],
		];
	}

	public function post( WP_REST_Request $request ) {
		$visibleColumns                                      = $request->get_param( 'visibleColumns' );
		$autoregisterType                                    = $request->get_param( 'autoregisterType' );
		$shouldRegisterBackendStrings                        = $request->get_param( 'shouldRegisterBackendStrings' );
		$shouldShowNoticeThatCachePluginCanBlockAutoregister = $request->get_param( 'shouldShowNoticeThatCachePluginCanBlockAutoregister' );
		$detectStringsInJS                                   = $request->get_param( 'detectStringsInJS' );

		if ( ! empty( $visibleColumns ) && is_array( $visibleColumns ) ) {
			$this->settingsRepository->setVisibleColumns( $visibleColumns );
		}

		if ( null !== $autoregisterType ) {
			$this->settingsRepository->setAutoregisterStringsTypeSetting( $autoregisterType );
		}

		if ( null !== $shouldRegisterBackendStrings ) {
			$this->settingsRepository->setShouldRegisterBackendStringsSetting( (bool) $shouldRegisterBackendStrings );
		}

		if ( null !== $shouldShowNoticeThatCachePluginCanBlockAutoregister && ! $shouldShowNoticeThatCachePluginCanBlockAutoregister ) {
			$this->pluginRepository->setNoticeThatCachePluginCanBlockAutoregisterAsDismissed();
		}

		if ( null !== $detectStringsInJS ) {
			$this->settingsRepository->setDetectStringsInJS( (int) $detectStringsInJS );
		}

		return [];
	}

	public function get( WP_REST_Request $request ) {
		global $sitepress;
		$activeLanguages       = (array) $sitepress->get_active_languages();
		$englishSourceLanguage = EnglishSourceLanguage::resolve(
			array_keys( $activeLanguages ),
			(string) $sitepress->get_default_language()
		);

		$autoregisterAllowedLanguages = array_values(
			array_map(
				function ( $data ) use ( $sitepress ) {
					return [
						'name' => $data['display_name'],
						'url'  => $sitepress->language_url( $data['code'] ),
					];
				},
				array_filter(
					$activeLanguages,
					function ( $data ) use ( $englishSourceLanguage ) {
						return $englishSourceLanguage !== $data['code'];
					}
				)
			)
		);

		return [
			'autoregisterType'             => $this->settingsRepository->getAutoregisterStringsTypeSetting(),
			'shouldRegisterBackendStrings' => $this->settingsRepository->getShouldRegisterBackendStringsSetting() ? 1 : 0,
			'autoregisterAllowedLanguages' => $autoregisterAllowedLanguages,
			'activeCachePluginName'        => $this->pluginRepository->shouldShowNoticeThatCachePluginCanBlockAutoregister()
				? $this->pluginRepository->getActiveCachePluginName()
				: '',
			'visibleColumns'               => $this->settingsRepository->getVisibleColumns(),
			'shouldShowNoticeThatCachePluginCanBlockAutoregister' => $this->pluginRepository->shouldShowNoticeThatCachePluginCanBlockAutoregister(),
		];
	}
}

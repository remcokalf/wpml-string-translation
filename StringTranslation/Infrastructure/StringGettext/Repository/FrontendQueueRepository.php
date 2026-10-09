<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\StringGettext\Repository\FrontendQueueRepositoryInterface;
use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Dto\GettextStringsByUrl;
use WPML\StringTranslation\Application\StringCore\Domain\Factory\StringItemFactory;
use WPML\StringTranslation\Infrastructure\Factory;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Util\FilterOnlyNewFrontendStrings;
use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Util\LimitMaxQueuedFrontendStrings;

class FrontendQueueRepository implements FrontendQueueRepositoryInterface {

	private $stringItemFactory;

	private $factory;

	private $settingsRepository;

	private $limitMaxQueuedFrontendStrings;

	private $filterOnlyNewFrontendStrings;

	public function __construct(
		StringItemFactory             $stringItemFactory,
		Factory                       $factory,
		SettingsRepositoryInterface   $settingsRepository,
		LimitMaxQueuedFrontendStrings $limitMaxQueuedFrontendStrings,
		FilterOnlyNewFrontendStrings  $filterOnlyNewFrontendStrings
	) {
		$this->stringItemFactory             = $stringItemFactory;
		$this->factory                       = $factory;
		$this->settingsRepository            = $settingsRepository;
		$this->limitMaxQueuedFrontendStrings = $limitMaxQueuedFrontendStrings;
		$this->filterOnlyNewFrontendStrings  = $filterOnlyNewFrontendStrings;
	}

	public function save( array $newData ) {
		$existingData = $this->factory->getFrontendQueueRepository()->get();
		if ( count( $existingData ) > 0 ) {
			$newData = $this->filterOnlyNewFrontendStrings->run( $newData, $existingData );
			if ( count( $newData ) === 0 ) {
				return;
			}
		}

		$allData = array_merge(
			$existingData,
			$this->limitMaxQueuedFrontendStrings->run(
				$newData,
				$existingData,
				$this->settingsRepository->getMaxQueuedFrontendStringsCount()
			)
		);

		$this->factory->getFrontendQueueRepository()->save( $allData );
	}

	public function get(): array {
		$data = $this->factory->getFrontendQueueRepository()->get();

		$items = [];

		foreach ( $data as $entry ) {
			$gettextStrings = array_map(
				function ($item) {
					return $this->stringItemFactory->create(
						$item['domain'] ?? '',
						$item['value'] ?? '',
						$item['context'] ?? ''
					);
				},
				$entry['gettextStrings']
			);
			$items[] = new GettextStringsByUrl(
				$gettextStrings,
				$entry['requestUrl']
			);
		}

		return $items;
	}

	public function count(): int {
		return $this->factory->getFrontendQueueRepository()->count();
	}

	public function removeProcessed( int $processed_count ) {
		$this->factory->getFrontendQueueRepository()->removeProcessed( $processed_count );
	}

	public function remove() {
		$this->factory->getFrontendQueueRepository()->remove();
	}
}

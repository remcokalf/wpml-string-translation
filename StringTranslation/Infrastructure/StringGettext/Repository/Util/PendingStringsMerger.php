<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository\Util;

class PendingStringsMerger {

	public function merge( array $existingStrings, array $newStrings ): array {
		$mergedStrings = $existingStrings;

		foreach ( $newStrings as $key => $newString ) {
			if ( ! isset( $mergedStrings[ $key ] ) || ! is_array( $mergedStrings[ $key ] ) ) {
				$mergedStrings[ $key ] = $newString;
				continue;
			}

			$mergedStrings[ $key ] = $this->mergePendingString( $mergedStrings[ $key ], $newString );
		}

		return $mergedStrings;
	}

	private function mergePendingString( array $existingString, array $newString ): array {
		$mergedString = $existingString;

		foreach ( $newString as $prop => $value ) {
			if ( 'names' === $prop ) {
				$mergedString['names'] = $this->mergeUniqueList(
					isset( $mergedString['names'] ) && is_array( $mergedString['names'] ) ? $mergedString['names'] : [],
					is_array( $value ) ? $value : []
				);
			} elseif ( 'urls' === $prop ) {
				$mergedString['urls'] = $this->mergeUniqueUrls(
					isset( $mergedString['urls'] ) && is_array( $mergedString['urls'] ) ? $mergedString['urls'] : [],
					is_array( $value ) ? $value : []
				);
			} elseif ( 'saveStringInDb' === $prop ) {
				$mergedString['saveStringInDb'] = ! empty( $mergedString['saveStringInDb'] ) || ! empty( $value );
			} elseif ( 'cmp' === $prop ) {
				if ( ! isset( $mergedString['cmp'] ) ) {
					$mergedString['cmp'] = $value;
				}
			} elseif ( ! array_key_exists( $prop, $mergedString ) ) {
				$mergedString[ $prop ] = $value;
			}
		}

		return $mergedString;
	}

	private function mergeUniqueList( array $existingItems, array $newItems ): array {
		foreach ( $newItems as $item ) {
			if ( ! in_array( $item, $existingItems, true ) ) {
				$existingItems[] = $item;
			}
		}

		return $existingItems;
	}

	private function mergeUniqueUrls( array $existingUrls, array $newUrls ): array {
		$keys = [];
		foreach ( $existingUrls as $url ) {
			if ( is_array( $url ) && isset( $url['kind'], $url['url'] ) ) {
				$keys[ $url['kind'] . '|' . $url['url'] ] = true;
			}
		}

		foreach ( $newUrls as $url ) {
			if ( ! is_array( $url ) || ! isset( $url['kind'], $url['url'] ) ) {
				continue;
			}

			$key = $url['kind'] . '|' . $url['url'];
			if ( isset( $keys[ $key ] ) ) {
				continue;
			}

			$existingUrls[] = $url;
			$keys[ $key ]  = true;
		}

		return $existingUrls;
	}
}

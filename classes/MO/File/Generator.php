<?php

namespace WPML\ST\MO\File;


use WPML\Collect\Support\Collection;
use WPML\ST\TranslateWpmlString;
use WPML\ST\TranslationFile\StringEntity;
use function wpml_collect;

class Generator {
	private $moFactory;

	public function __construct( MOFactory $moFactory ) {
		$this->moFactory = $moFactory;
	}

	public function getContent( array $entries ) {
		$mo = $this->moFactory->createNewInstance();
		wpml_collect( $entries )
			->reduce( [ $this, 'createMOFormatEntities' ], wpml_collect( [] ) )
			->filter( function( array $entry ) { return ! empty($entry['singular']); } )
			->each( [ $mo, 'add_entry' ] );

		$mem_file = fopen( 'php://memory', 'r+' );
		if ( $mem_file === false ) {
			return '';
		}
		$mo->export_to_file_handle( $mem_file );

		rewind( $mem_file );
		$mo_content = stream_get_contents( $mem_file );

		fclose( $mem_file );

		return $mo_content;
	}

	public function createMOFormatEntities( $carry, StringEntity $entry ) {
		$carry->push( $this->mapStringEntityToMOFormatUsing( $entry, 'original' ) );

		if ( TranslateWpmlString::canTranslateWithMO( $entry->get_original(), $entry->get_name() ) ) {
			$carry->push( $this->mapStringEntityToMOFormatUsing( $entry, 'name' ) );
		}

		return $carry;
	}

	private function mapStringEntityToMOFormatUsing( StringEntity $entry, $singularField ) {
		return [
			'singular'     => $entry->{'get_' . $singularField}(),
			'translations' => $entry->get_translations(),
			'context'      => $entry->get_context(),
			'plural'       => $entry->get_original_plural(),
		];
	}
}

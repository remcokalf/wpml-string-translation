<?php

namespace WPML\ST\Rest\MO;

use WPML\ST\TranslationFile\QueueFilter;
use WPML_ST_Translations_File_Queue;

class Import extends \WPML\ST\Rest\Base {

	function get_routes() {
		return [
			[
				'route' => 'import_mo_strings',
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'import' ],
					'args'     => [
						'plugins' => [
							'type' => 'array',
						],
						'themes'  => [
							'type' => 'array',
						],
						'other'   => [
							'type' => 'array',
						],
					]
				]
			]
		];
	}

	function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [ 'manage_options' ];
	}

	public function import( \WP_REST_Request $request ) {

		$queue       = \WPML\Container\make( \WPML_ST_Translations_File_Scan_Factory::class )->create_queue();
		$queueFilter = new QueueFilter(
			$request->get_param( 'plugins' ),
			$request->get_param( 'themes' ),
			$request->get_param( 'other' )
		);

		$totalPending = $queue->getPendingByFilter( $queueFilter );
		$queue->import( $queueFilter );

		return [
			'total'        => $totalPending,
			'remaining'    => $queue->get_pending(),
			/* translators: Message on the Theme and plugins localization page after a scan. %s: the number of translation files found. "Their" refers to those files. Keep the word "WPML" and both full stops: the page starts the message at "WPML" and puts the first sentence in bold. */
			'scan_message' => __( 'WPML found %s new or updated .mo files. Their texts were added to the translations table.', 'wpml-string-translation' ),
		];
	}
}

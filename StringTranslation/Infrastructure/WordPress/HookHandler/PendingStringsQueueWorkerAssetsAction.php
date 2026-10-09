<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler;

use WPML\StringTranslation\Application\Setting\Repository\UrlRepositoryInterface;

class PendingStringsQueueWorkerAssetsAction extends AbstractActionHookHandler {

	const ACTION_NAME = 'admin_enqueue_scripts';
	const ACTION_ARGS = 1;

	private $urlRepository;

	public function __construct( UrlRepositoryInterface $urlRepository ) {
		$this->urlRepository = $urlRepository;
	}

	protected function onAction( ...$args ) {
		if (
			! $this->urlRepository->isCurrentPageWpmlDashboard()
			&& ! $this->urlRepository->isCurrentPageStDashboard()
		) {
			return;
		}

		if (
			! current_user_can( 'wpml_manage_string_translation' )
			&& ! current_user_can( 'manage_translations' )
		) {
			return;
		}

		$handle = 'wpml-st-pending-strings-queue-worker';
		wp_enqueue_script(
			$handle,
			WPML_ST_URL . '/res/js/pending-strings-queue-worker.js',
			[],
			WPML_ST_VERSION,
			true
		);
		wp_localize_script(
			$handle,
			'wpmlStPendingStringsQueueWorker',
			[
				'url'                    => rest_url( 'wpml/st/v1/strings/processstringsqueue' ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'retryDelayMs'           => 250,
				'busyRetryDelayMs'       => 1000,
				'maxRequestsPerPage'     => 120,
				'maxBusyRequestsPerPage' => 600,
				'maxErrors'              => 5,
				'texts'                  => [
					'processing'    => __( 'Registering new strings found on your site…', 'wpml-string-translation' ),
					'completed'     => __( 'New strings were registered.', 'wpml-string-translation' ),
					'refresh'       => __( 'Refresh the page to see them', 'wpml-string-translation' ),
					'stalledMemory' => __( 'String registration is paused: there is not enough PHP memory to continue. Increase the memory limit or retry.', 'wpml-string-translation' ),
					'stalledCap'    => __( 'String registration is paused after many background requests.', 'wpml-string-translation' ),
					'stalledError'  => __( 'String registration was interrupted by a server error.', 'wpml-string-translation' ),
					/* translators: Button label in the string registration notice: it starts the interrupted registration again. Verb, imperative. */
					'retry'         => __( 'Retry', 'wpml-string-translation' ),
				],
			]
		);
	}
}

<?php

namespace WPML\ST\MO\File;

use WP_Filesystem_Base;
use WPML\ST\MO\Generate\Process\Status;
use WPML\ST\MO\Generate\Process\SingleSiteProcess;
use WPML\ST\MO\Notice\RegenerationInProgressNotice;
use function wpml_get_admin_notices;

class FailureHooks implements \IWPML_Backend_Action {
	use makeDir;

	const NOTICE_GROUP             = 'mo-failure';
	const NOTICE_ID_MISSING_FOLDER = 'missing-folder';

	private $status;

	private $singleProcess;

	public function __construct(
		WP_Filesystem_Base $filesystem,
		Status $status,
		SingleSiteProcess $singleProcess
	) {
		$this->filesystem    = $filesystem;
		$this->status        = $status;
		$this->singleProcess = $singleProcess;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'checkDirectories' ] );
	}

	public function checkDirectories() {
		if ( $this->isDirectoryMissing( WP_LANG_DIR ) ) {
			$this->resetRegenerateStatus();
			$this->displayMissingFolderNotice( WP_LANG_DIR );

			return;
		}

		if ( $this->isDirectoryMissing( self::getSubdir() ) ) {
			$this->resetRegenerateStatus();

			if ( ! $this->maybeCreateSubdir() ) {
				$this->displayMissingFolderNotice( self::getSubdir() );
				return;
			}
		}

		$notices = wpml_get_admin_notices();
		$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID_MISSING_FOLDER );

		if ( ! $this->status->isComplete() ) {
			$this->displayRegenerateInProgressNotice();
			$this->singleProcess->runPage();
		}

		if ( $this->status->isComplete() ) {
			wpml_get_admin_notices()->remove_notice( RegenerationInProgressNotice::GROUP, RegenerationInProgressNotice::ID );
		}
	}

	public function displayMissingFolderNotice( $dir ) {
		$notices = wpml_get_admin_notices();
		$notice = $notices->get_new_notice(
			self::NOTICE_ID_MISSING_FOLDER, self::missingFolderNoticeContent( $dir ),
			self::NOTICE_GROUP
		);
		$notice->set_css_classes( 'error' );
		$notices->add_notice( $notice );
	}

	public static function missingFolderNoticeContent( $dir ) {
		$text = '<p>' .
		        \wpml_bold_names( __( 'WPML String Translation is attempting to write .mo files with translations to folder:',
			        'wpml-string-translation' )
		        ) . '<br/>' .
		        str_replace( '\\', '/', $dir ) .
		        '</p>' . '<br/>' ;

		$text .= '<p>' . esc_html__( 'This folder appears to be not writable. This is blocking translation for strings from appearing on the site.',
				'wpml-string-translation' ) . '</p>';

		$text .= '<ul>' .
			'<li>' . sprintf(
				/* translators: Item in the list of things to check when WPML cannot write the translation files. "this" is the site the user is working on. %1$s: opening bold tag, %2$s: closing bold tag. */
				esc_html__( 'If this is a %1$slocal development site%2$s, make sure that your local server can write to this folder.',
				'wpml-string-translation' ),
				'<strong>', '</strong>'
			) . '</li>' .
			'<li>' . sprintf(
				/* translators: Item in the list of things to check when WPML cannot write the translation files. "it" is the site the user is working on. %1$s: opening bold tag, %2$s: closing bold tag. */
				esc_html__( 'If it\'s an %1$sonline site%2$s, contact your hosting company and request that they make that folder writable.',
				'wpml-string-translation' ),
				'<strong>', '</strong>'
			) . '</li>' .
			'<li>' . sprintf(
				/* translators: Item in the list of things to check when WPML cannot write the translation files. %1$s: a line of PHP code, %2$s: the name of the WordPress configuration file, %3$s: the name of the folder WPML writes its files into. */
				esc_html__( 'If your hosting company cannot make that folder writable, you can point WordPress at a different one. Add %1$s to your %2$s file, then create that folder and a %3$s folder inside it.',
				'wpml-string-translation' ),
				'<code>' . esc_html( "define( 'WP_LANG_DIR', '/path/to/a/writable/folder' );" ) . '</code>',
				'<code>wp-config.php</code>',
				'<code>' . esc_html( \WPML\ST\TranslationFile\Manager::SUB_DIRECTORY ) . '</code>'
			) . '</li>' .
			'</ul>';

		return $text;
	}

	private function displayRegenerateInProgressNotice() {
		$notices = wpml_get_admin_notices();
		$notices->add_notice( new RegenerationInProgressNotice() );
	}

	public static function getSubdir() {
		return WP_LANG_DIR . '/' . \WPML\ST\TranslationFile\Manager::SUB_DIRECTORY;
	}

	private function isDirectoryMissing( $dir ) {
		return ! $this->filesystem->is_writable( $dir );
	}

	private function resetRegenerateStatus() {
		$this->status->markIncomplete();
	}
}

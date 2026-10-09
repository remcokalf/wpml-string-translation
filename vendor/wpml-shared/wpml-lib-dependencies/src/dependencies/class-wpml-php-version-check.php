<?php

if ( ! class_exists( 'WPML_PHP_Version_Check' ) ) {

	class WPML_PHP_Version_Check {

		private $required_php_version;

		private $plugin_name;

		private $plugin_file;

		private $text_domain;

		public function __construct( $required_version, $plugin_name, $plugin_file, $text_domain ) {
			$this->required_php_version = $required_version;
			$this->plugin_name          = $plugin_name;
			$this->plugin_file          = $plugin_file;
			$this->text_domain          = $text_domain;
		}

		public function is_ok() {
			if ( version_compare( $this->required_php_version, phpversion(), '>' ) ) {
				add_action( 'admin_notices', array( $this, 'php_requirement_message' ) );

				return false;
			}

			return true;
		}

		public function php_requirement_message() {
			load_plugin_textdomain( $this->text_domain, false, dirname( plugin_basename( $this->plugin_file ) ) . '/locale' );

			/* translators: 1: Current PHP version number, 2: Plugin version, 3: Minimum required PHP version number */
			$message = sprintf( __( 'Your server is running PHP version %1$s but %2$s requires at least %3$s.', $this->text_domain ), phpversion(), $this->plugin_name, $this->required_php_version );
			?>
			<div class="message error">
				<p>
					<?php echo wp_kses_post( $message ); ?>
				</p>
			</div>
			<?php
		}
	}
}

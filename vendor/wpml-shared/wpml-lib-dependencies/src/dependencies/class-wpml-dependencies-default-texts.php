<?php
class WPML_Dependencies_Default_Texts implements WPML_Dependencies_Texts {

	public static function keys() {
		return array(
			'title',
			'header_one',
			'header_many',
			'header_none',
			'item_installed',
			'item_needs',
			'stays_off_one',
			'stays_off_many',
			'stays_off_list_intro',
			'stays_off_list_item',
			'footer_intro',
			'footer_update',
			'footer_update_link_text',
			'footer_register',
			'account_link_text',
			'footer_no_installer_intro',
			'footer_no_installer_update',
			'footer_no_installer_register',
		);
	}

	public function get( $key ) {
		switch ( $key ) {
			case 'title':
				return __( 'WPML Update is Incomplete', 'sitepress' );

			case 'header_one':
				return __( 'You are running updated %s, but the following component is not updated:', 'sitepress' );
			case 'header_many':
				return __( 'You are running updated %s and %s, but the following components are not updated:', 'sitepress' );
			case 'header_none':
				return __( 'The following components are not updated:', 'sitepress' );

			case 'item_installed':
				return __( '%1$s: %2$s is installed', 'sitepress' );
			case 'item_needs':
				return __( '%1$s needs %2$s or newer', 'sitepress' );
			case 'stays_off_one':
				return __( '%1$s stays off until %2$s is updated.', 'sitepress' );
			case 'stays_off_many':
				return __( '%1$s and %2$s stay off until %3$s is updated.', 'sitepress' );
			case 'stays_off_list_intro':
				return __( 'These plugins need a newer version and stay off until it is updated:', 'sitepress' );
			case 'stays_off_list_item':
				return __( '%1$s (needs %2$s or newer)', 'sitepress' );

			case 'footer_intro':
				return __( 'Your site will not work as it should in this configuration.', 'sitepress' );
			case 'footer_update':
				return __( 'Update all the components you use from %s.', 'sitepress' );
			case 'footer_update_link_text':
				return __( 'WPML → Activate & Update', 'sitepress' );
			case 'footer_register':
				return __( 'You get updates from your %s, or automatically once you register WPML.', 'sitepress' );
			case 'account_link_text':
				return __( 'WPML.org account', 'sitepress' );

			case 'footer_no_installer_intro':
				return __( 'Your site will not work as it should in this configuration', 'sitepress' );
			case 'footer_no_installer_update':
				return __( 'Please update all components which you are using.', 'sitepress' );
			case 'footer_no_installer_register':
				return __( 'For WPML components you can receive updates from your %s or automatically, after you register WPML.', 'sitepress' );
		}

		return '';
	}
}

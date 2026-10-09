<?php

class WPML_Change_String_Domain_Language_Dialog extends WPML_WPDB_And_SP_User {

	private $language_of_domain;

	private $string_factory;

	public function __construct(
		\wpdb $wpdb,
		\SitePress $sitepress,
		\WPML_ST_String_Factory $string_factory
	) {
		parent::__construct( $wpdb, $sitepress );

		$this->string_factory     = &$string_factory;
		$this->language_of_domain = new WPML_Language_Of_Domain( $sitepress );
	}

	public function render( $domains ) {
		$wpdb          = $this->wpdb;
		$all_languages = $this->sitepress->get_languages( $this->sitepress->get_admin_language() );

		?>
			<div id="wpml-change-domain-language-dialog"
				 class="wpml-change-language-dialog no-bottom-spacer"
				 title="<?php _e( 'Language of domains', 'wpml-string-translation' ); ?>"
				 style="display:none"
				 <?php /* translators: Button label that saves the settings on the String Translation page and in its dialogs. Verb, imperative. */ ?>
				 data-button-text="<?php _e( 'Apply', 'wpml-string-translation' ); ?>"
				 <?php /* translators: Button label that closes a dialog or leaves a form on the String Translation page without saving. Verb, imperative, not the noun "a cancellation". */ ?>
				 data-cancel-text="<?php _e( 'Cancel', 'wpml-string-translation' ); ?>" >
				<div class="wpml-domain-select-wrap">
					<div class="select-row clear">
						<div class="select-row-label">
							<label for="wpml-domain-select">
								<?php /* translators: Label of the dropdown in the "Language of domains" dialog on the String Translation page, above the list of domains. */ ?>
								<?php _e( 'Select for which domain to set the language: ', 'wpml-string-translation' ); ?>
							</label>
						</div>
						<div class="select-row-select">
							<select id="wpml-domain-select">
								<?php /* translators: First option in a dropdown, shown until the user picks something. The dashes are decoration and can be dropped if they read badly. */ ?>
								<option value="" selected="selected"><?php _e( '-- Please select --', 'wpml-string-translation' ); ?></option>
							<?php
							$language_codes = array_keys( $all_languages );
							foreach ( $domains as $domain ) {
								$results = $wpdb->get_results(
									$wpdb->prepare(
											"
											SELECT language, COUNT(language) AS count
											FROM {$wpdb->prefix}icl_strings s
											WHERE context = %s
											AND language IN (" . implode( ', ', array_fill( 0, count( $language_codes ), '%s' ) ) . ')
										GROUP BY language
										',
										array_merge( [ $domain->context ], $language_codes )
										),
										ARRAY_A
									);
									foreach ( $results as &$result ) {
										$result['display_name'] = $all_languages[ $result['language'] ]['display_name'];
									}
									$domain_lang = $this->language_of_domain->get_language( $domain->context );
									echo '<option value="' . esc_attr( $domain->context ) .
												'" data-langs="' . esc_attr( (string) wp_json_encode( $results ) ) .
												'" data-domain_lang="' . esc_attr( $domain_lang ? $domain_lang : '' ) . '">' . esc_html( $domain->context ) . '</option>';
								}
								?>
							</select>
						</div>
					</div>
				</div>
				<div class="js-summary wpml-cdl-summary" style="display:none" >
					<div class="separator separator-no-padding-top"></div>
					<p class="wpml-cdl-info no-margin-bottom no-horizontal-spacer">
						<b><?php _e( 'This domain currently has the following strings:', 'wpml-string-translation' ); ?></b>
					</p>
					<br/>
					<table class="widefat striped wpml-cdl-table modal-checkboxes-table no-horizontal-spacer">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column"><input class="wpml-checkbox-native js-all-check" type="checkbox" value="all" /></td>
								<th><b><?php _e( 'Current source language', 'wpml-string-translation' ); ?></b></th>
								<th class="num"><b><?php _e( 'Number of strings', 'wpml-string-translation' ); ?></b></th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
					<div class="separator separator-no-padding-top"></div>
					<div class="js-lang-select-area wpml-cdl-info top-spacer no-horizontal-spacer">
						<div class="select-row clear">
							<div class="select-row-label">
								<?php /* translators: Label of the language dropdown in the "Language of domains" dialog on the String Translation page. */ ?>
								<label for="wpml-source-domain-language-change"><?php _e( 'Set the source language of these strings to:', 'wpml-string-translation' ); ?></label>
							</div>
							<div class="select-row-select">
								<?php
									$selector_options = array(
										'id'        => 'wpml-source-domain-language-change',
										'languages' => \WPML\ST\StringTranslationUI\ConfiguredLanguages::rows( $this->sitepress ),
										'echo'      => true,
									);

									$lang_selector = new WPML_Simple_Language_Selector( $this->sitepress );
									$lang_selector->render( $selector_options );
								?>
							</div>
						</div>
						<div class="top-spacer">
							<label for="wpml-cdl-set-default">
								<input id="wpml-cdl-set-default" type="checkbox" class="wpml-checkbox-native js-default" value="use-as-default" checked="checked" />
								<?php _e( 'Use this language as the default language for new strings in this domain', 'wpml-string-translation' ); ?>
							</label>
						</div>
					</div>
				</div>
				<span class="spinner"></span>
				<?php wp_nonce_field( 'wpml_change_string_domain_language_nonce', 'wpml_change_string_domain_language_nonce' ); ?>
			</div>
		<?php
	}

	public function changeLanguageOfStringsInPackages( $domain, $langs, $to_lang ) {
		$package_translation = new WPML_Package_Helper();
		$package_translation->change_language_of_strings_in_domain( $domain, $langs, $to_lang );
	}

	public function setLanguageOfDomain( $domain, $to_lang ) {
		$lang_of_domain = new WPML_Language_Of_Domain( $this->sitepress );
		$lang_of_domain->set_language( $domain, $to_lang );
	}

	public function changeLanguageOfStrings( $stringIds, $to_lang ) {
		foreach ( $stringIds as $id ) {
			$string = $this->string_factory->find_by_id( (int) $id );
			$string->set_language( $to_lang );
			$string->update_status();
		}

		do_action( 'wpml_st_language_of_strings_changed', $stringIds );
	}

	public function change_language_of_strings( $domain, $langs, $to_lang, $set_as_default ) {
		$wpdb = $this->wpdb;

		$this->changeLanguageOfStringsInPackages( $domain, $langs, $to_lang );

		if ( ! empty( $langs ) ) {
			$string_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND language IN (" . implode( ', ', array_fill( 0, count( $langs ), '%s' ) ) . ')',
					array_merge( [ $domain ], $langs )
				)
			);
			foreach ( $string_ids as $str_id ) {
				$this->string_factory->find_by_id( $str_id )->set_language( $to_lang );
			}
		}
		if ( $set_as_default ) {
			$this->setLanguageOfDomain( $domain, $to_lang );
		}

		$string_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s", $domain )
		);
		foreach ( $string_ids as $strid ) {
			$this->string_factory->find_by_id( $strid )->update_status();
		}

		do_action( 'wpml_st_language_of_strings_changed', $string_ids );

		return array( 'success' => true );
	}
}

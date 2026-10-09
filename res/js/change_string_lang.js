/*jshint devel:true */
/*global jQuery, ajaxurl, get_checked_cbs */
var WPML_String_Translation = WPML_String_Translation || {};

WPML_String_Translation.ChangeLanguage = function () {
	"use strict";
	var privateData = {};

    var init = function () {
        jQuery(function () {

            privateData.language_select = jQuery('#icl-st-change-lang-selected');
            privateData.language_select.on('change', applyChanges);

            privateData.spinner = jQuery('.icl-st-change-spinner');
            privateData.spinner.detach().insertAfter(privateData.language_select);
        });
    };

	var applyChanges = function () {
        if(WPML_String_Translation.ExecBatchAction.isApplyBulkActionSelected()) {
            WPML_String_Translation.ExecBatchAction.run(
                wpml_st_exec_batch_action_data.initChangeStringLangOfDomain,
                wpml_st_exec_batch_action_data.changeLanguageOfStringsInDomain,
                {
                    domain: jQuery('select[name="icl_st_filter_context"] option:selected').val(),
                    targetLanguage: privateData.language_select.val(),
                },
                {
                    beforeStart: function() {
                        jQuery('#icl-st-change-lang-selected').attr('disabled', 'disabled');
                    },
                    onStop: function() {
                        jQuery('#icl-st-change-lang-selected').removeAttr('disabled');
                    },
                    onComplete: function(data) {
                        jQuery('#icl-st-change-lang-selected').removeAttr('disabled');
                        window.location.reload();
                    },
                }
            );
            return;
        }

		var checkBoxValue;
		var data;
		var i;
		var checkboxes;
		var strings;

        privateData.spinner.addClass('is-active');

		strings = [];
		checkboxes = get_checked_cbs();
		for (i = 0; i < checkboxes.length; i++) {
			checkBoxValue = jQuery(checkboxes[i]).val();
			strings.push(checkBoxValue);
		}

		// Nothing checked: there is no request to make. jQuery drops an empty
		// array from the request body, so firing anyway sent a request with no
		// `strings` field at all, and the manager got WordPress's
		// critical-error page instead of the JSON this handler reads
		// (DEV0905-7). The screen disables these two selects while nothing is
		// checked (icl_st_update_checked_elements in scripts.js), but that runs
		// from the checkbox click handler, so a freshly loaded screen still has
		// them live - which is the route a manager takes.
		if (0 === strings.length) {
			showFailure(wpml_st_change_lang_data.noSelectionText);
			return;
		}

		data = {
			action:   'wpml_change_string_lang',
			wpnonce:  wpml_st_change_lang_data.nonce,
			strings:  strings,
			language: privateData.language_select.val()
		};

		jQuery.ajax({
			url:      ajaxurl,
			type:     'post',
			data:     data,
			dataType: 'json',
			success:  function (response) {
				if (response && response.success) {
					window.location.reload(true);
					return;
				}
				// wp_send_json_error() answers {success:false,data:'...'}; a 2xx
				// carrying one is a refusal and used to match neither branch.
				if (response && false === response.success) {
					showFailure(response.data);
					return;
				}
				if (response && response.error) {
					showFailure(response.error);
				}
			},
			// A refused language is answered with 400 (and a caller without the
			// capability with 403), which jQuery routes here and never to
			// success:. Without this the spinner just kept spinning.
			error:    function (xhr) {
				showFailure(xhr && xhr.responseJSON ? xhr.responseJSON.data : null);
			}
		});
	};

	/**
	 * Stop the spinner, give the control back, and say what happened. The
	 * fallback sentence is localized in PHP and shipped with the nonce.
	 *
	 * A refusal from the request seam (WPML\Request\Payload) and from the
	 * request gate carries {code, message, params} rather than a bare string,
	 * so the sentence is picked out of it; anything else is shown as it came.
	 */
	var showFailure = function (message) {
		privateData.spinner.removeClass('is-active');
		privateData.language_select.removeAttr('disabled');
		if (privateData.apply_button) {
			privateData.apply_button.prop('disabled', false);
		}
		if (message && 'object' === typeof message) {
			message = message.message;
		}
		alert(message ? message : wpml_st_change_lang_data.errorText);
	};

	init();
};

WPML_String_Translation.change_language = new WPML_String_Translation.ChangeLanguage();

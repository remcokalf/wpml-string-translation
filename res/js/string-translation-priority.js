/*jshint devel:true */
/*global jQuery, ajaxurl, get_checked_cbs, wpml_st_translation_priority_data */
var WPML_String_Translation = WPML_String_Translation || {};

WPML_String_Translation.ChangeTranslationPriority = function () {
    "use strict";

    var privateData = {};

    var init = function () {
        jQuery(function () {
            privateData.translation_priority_select = jQuery('#icl-st-change-translation-priority-selected');
            privateData.translation_priority_select.on('change', applyChanges);

            privateData.spinner = jQuery('.icl-st-change-spinner');
            privateData.spinner.detach().insertAfter(privateData.translation_priority_select);

            initializeSelect2();
        });
    };

    var applyChanges = function () {
        if(WPML_String_Translation.ExecBatchAction.isApplyBulkActionSelected()) {
            WPML_String_Translation.ExecBatchAction.run(
                wpml_st_exec_batch_action_data.countStringsInDomainWithDifferentPriority,
                wpml_st_exec_batch_action_data.changeTranslationPriorityBatchOfStringsInDomain,
                {
                    domain: jQuery('select[name="icl_st_filter_context"] option:selected').val(),
                    priority: privateData.translation_priority_select.val(),
                },
                {
                    beforeStart: function() {
                        jQuery('#icl-st-change-translation-priority-selected').attr('disabled', 'disabled');
                    },
                    onStop: function() {
                        jQuery('#icl-st-change-translation-priority-selected').removeAttr('disabled');
                    },
                    onComplete: function(data) {
                        jQuery('#icl-st-change-translation-priority-selected').removeAttr('disabled');
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
        // critical-error page (DEV0905-7, the same defect as the
        // change-language control beside this one).
        if (0 === strings.length) {
            showFailure(wpml_st_translation_priority_data.noSelectionText);
            return;
        }

        data = {
            action: 'wpml_change_string_translation_priority',
            wpnonce: jQuery('#wpml_change_string_translation_priority_nonce').val(),
            strings: strings,
            priority: privateData.translation_priority_select.val()
        };

        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response && response.success) {
                    window.location.reload(true);
                    return;
                }
                // wp_send_json_error() answers {success:false,data:'...'}; a 2xx
                // carrying one is a refusal and used to match no branch at all.
                if (response && false === response.success) {
                    showFailure(response.data);
                }
            },
            // A refused request is answered with 400 (and a caller without the
            // capability with 403), which jQuery routes here and never to
            // success:. Without this the spinner just kept spinning.
            error: function (xhr) {
                showFailure(xhr && xhr.responseJSON ? xhr.responseJSON.data : null);
            }
        });
    };

    /**
     * Stop the spinner, give the control back, and say what happened. The
     * fallback sentence is localized in PHP and shipped with the screen.
     *
     * A refusal from the request seam (WPML\Request\Payload) and from the
     * request gate carries {code, message, params} rather than a bare string,
     * so the sentence is picked out of it; anything else is shown as it came.
     */
    var showFailure = function (message) {
        privateData.spinner.removeClass('is-active');
        privateData.translation_priority_select.removeAttr('disabled');
        if (message && 'object' === typeof message) {
            message = message.message;
        }
        alert(message ? message : wpml_st_translation_priority_data.errorText);
    };

    var initializeSelect2 = function () {
        privateData.translation_priority_select.wpml_select2({

            width:              'auto',
            dropdownCss:        {'z-index': parseInt(jQuery('.ui-dialog').css('z-index'), 10) + 100},
            dropdownAutoWidth:  true
        });
        jQuery('.js-change-translation-priority .wpml_select2-choice').addClass('button button-secondary').attr('disabled', 'true');
    };

    init();
};

WPML_String_Translation.change_translation_priority = new WPML_String_Translation.ChangeTranslationPriority();

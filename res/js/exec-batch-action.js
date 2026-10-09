jQuery(function () {
    WPML_String_Translation.ExecBatchAction.init(jQuery('#wpml-icl-string-translations-batch-loader'));
});

var WPML_String_Translation = WPML_String_Translation || {};

WPML_String_Translation.ExecBatchAction = {
    BATCH_SIZE: 50,
    // A batch request that does not come back is sent again with fewer strings: a
    // server that could not finish one batch in its time budget can still finish a
    // smaller one. After the last size the action stops and says so.
    RETRY_BATCH_SIZES: [10, 2],

    isApplyBulkActionSelected() {
        var msg = jQuery('.js-wpml-st-table').find('.js-wpml-st-icl-string-translations-bulk-select-msg');
		return msg.get(0).hasAttribute('data-is-apply-bulk-action-selected');
    },

    init: function(loader) {
        this.loader = loader;
        this.totalItemsCount = 0;
    },

    run: function(initData, processBatchData, handlerData, options) {
        var self = this;
        options = options || {};
        if(typeof options.beforeStart === 'undefined') {
            options.beforeStart = function() {};
        }
        if(typeof options.onComplete === 'undefined') {
            options.onComplete = function() {};
        }
        // Called by stop(): the caller gives back the control it disabled in beforeStart.
        if(typeof options.onStop === 'undefined') {
            options.onStop = function() {};
        }
        this.options = options;
        this.loader.css('display', 'block');
        options.beforeStart();

        var data = {
            action: 'wpml_action',
            data: JSON.stringify(handlerData),
            endpoint: initData.endpoint,
            nonce: initData.nonce,
        };

        jQuery.ajax({
            url:      ajaxurl,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success: function(res) {
                if(!res.success) {
                    self.stop('Error: ' + res.data);
                    return;
                }

                self.totalItemsCount = res.data.totalItemsCount;
                self.completedItemsCount = 0;
                self.runNextBatch(processBatchData, handlerData, options);
            },
            // A count that does not come back is not sent again: the screen is
            // restored and the owner is told the action did not start.
            error: function() {
                self.stop(wpml_st_exec_batch_action_data.notStartedText);
            }
        });
    },

    // The one exit for a run that does not reach onComplete: bar away, control
    // back, one alert (with no message: the action stopped before it finished).
    stop: function(message) {
        this.updatePercentage(0);
        this.loader.css('display', 'none');
        var options = this.options || {};
        if(typeof options.onStop === 'function') {
            options.onStop();
        }
        window.alert(message || wpml_st_exec_batch_action_data.stoppedText);
    },

    runNextBatch: function(processBatchData, handlerData, options, attempt) {
        attempt = attempt || 0;
        var sizes = [WPML_String_Translation.ExecBatchAction.BATCH_SIZE].concat(WPML_String_Translation.ExecBatchAction.RETRY_BATCH_SIZES);
        handlerData.batchSize = sizes[attempt];
        var self = this;
        var data = {
            action: 'wpml_action',
            data: JSON.stringify(handlerData),
            endpoint: processBatchData.endpoint,
            nonce: processBatchData.nonce,
        };

        jQuery.ajax({
            url:      ajaxurl,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success: function(res) {
                if(!res.success) {
                    self.stop('Error: ' + res.data);
                    return;
                }

                var completedInBatch = parseInt(res.data.completedCount, 10) || 0;
                self.completedItemsCount += completedInBatch;
                self.updatePercentage(Math.ceil(100 * (self.completedItemsCount / self.totalItemsCount)));
                if(self.totalItemsCount > self.completedItemsCount && ! completedInBatch) {
                    // Nothing changed in this batch, so the next one would ask for the same strings again.
                    self.stop();
                } else if(self.totalItemsCount > self.completedItemsCount) {
                    self.runNextBatch(processBatchData, handlerData, options);
                } else {
                    self.updatePercentage(0);
                    self.loader.css('display', 'none');
                    options.onComplete(res.data);
                }
            },
            error: function() {
                if(attempt + 1 < sizes.length) {
                    self.runNextBatch(processBatchData, handlerData, options, attempt + 1);
                } else {
                    self.stop();
                }
            }
        });
    },

    updatePercentage: function(pt) {
        this.loader.find('.js-content-percentage').text(pt + '%');
        this.loader.find('.js-content-percentage-bar-status').attr('style', 'width: ' + pt + '%');
    },
}
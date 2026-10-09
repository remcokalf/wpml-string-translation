/**
 * Background worker draining the pending-strings queue through the
 * processstringsqueue REST endpoint after a dashboard page loads.
 *
 * Public contract: on every state transition the worker dispatches a
 * cancelable `wpml-st-pending-strings-queue-state` CustomEvent on `window`
 * with detail `{ status, didProcess, reason }`:
 *
 * - status: 'processing' | 'completed' | 'stalled'
 * - didProcess: whether any strings were processed during this page's run
 * - reason (stalled only): 'error' | 'memory' | 'request-cap'
 *
 * A UI that renders its own indicator (e.g. the TM dashboard strings box)
 * should call event.preventDefault() and, on 'completed' with didProcess,
 * refresh its own data. When no listener cancels the event, the worker
 * renders a standard WP admin notice itself; it never reloads the page on
 * its own.
 */
( function( config ) {
	'use strict';

	if ( ! config || ! config.url || ! window.fetch ) {
		return;
	}

	var texts = config.texts || {};
	var maxWorkRequests = parseInt( config.maxRequestsPerPage, 10 ) || 120;
	var maxBusyRequests = parseInt( config.maxBusyRequestsPerPage, 10 ) || 600;
	var maxErrors = parseInt( config.maxErrors, 10 ) || 5;
	var retryDelay = parseInt( config.retryDelayMs, 10 ) || 250;
	var busyRetryDelay = parseInt( config.busyRetryDelayMs, 10 ) || 1000;

	var stopped = false;
	var inFlight = false;
	var didProcess = false;
	var announcedProcessing = false;
	var workRequests = 0;
	var busyRequests = 0;
	var errorCount = 0;
	var resumeOnVisible = false;
	var noticeElement = null;

	function emitState( status, reason ) {
		var stateEvent = new CustomEvent( 'wpml-st-pending-strings-queue-state', {
			cancelable: true,
			detail: {
				status: status,
				didProcess: didProcess,
				reason: reason || null
			}
		} );
		window.dispatchEvent( stateEvent );

		return ! stateEvent.defaultPrevented;
	}

	function removeNotice() {
		if ( noticeElement && noticeElement.parentNode ) {
			noticeElement.parentNode.removeChild( noticeElement );
		}
		noticeElement = null;
	}

	function renderNotice( type, message, buttonText, onButtonClick, withSpinner ) {
		var anchor = document.querySelector( '.wrap h1' ) || document.querySelector( '#wpbody-content h1' );
		if ( ! anchor || ! anchor.parentNode ) {
			return;
		}

		removeNotice();

		noticeElement = document.createElement( 'div' );
		noticeElement.className = 'notice notice-' + type + ' wpml-st-pending-strings-notice';

		var paragraph = document.createElement( 'p' );

		if ( withSpinner ) {
			var spinner = document.createElement( 'span' );
			spinner.className = 'spinner is-active';
			spinner.style.float = 'none';
			spinner.style.marginTop = '0';
			paragraph.appendChild( spinner );
		}

		paragraph.appendChild( document.createTextNode( message + ' ' ) );

		if ( buttonText && onButtonClick ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button button-small';
			button.appendChild( document.createTextNode( buttonText ) );
			button.addEventListener( 'click', onButtonClick );
			paragraph.appendChild( button );
		}

		noticeElement.appendChild( paragraph );
		anchor.parentNode.insertBefore( noticeElement, anchor.nextSibling );
	}

	function announceProcessing() {
		if ( announcedProcessing ) {
			return;
		}
		announcedProcessing = true;

		if ( emitState( 'processing' ) ) {
			renderNotice(
				'info',
				texts.processing || 'Registering new strings found on your site…',
				null,
				null,
				true
			);
		}
	}

	function complete() {
		stopped = true;

		if ( ! emitState( 'completed' ) ) {
			removeNotice();
			return;
		}

		if ( didProcess ) {
			renderNotice(
				'info',
				texts.completed || 'New strings were registered.',
				texts.refresh || 'Refresh the page to see them',
				function() {
					window.location.reload();
				},
				false
			);
		} else {
			removeNotice();
		}
	}

	function stall( reason ) {
		stopped = true;

		if ( ! emitState( 'stalled', reason ) ) {
			removeNotice();
			return;
		}

		var message;
		if ( 'memory' === reason ) {
			message = texts.stalledMemory
				|| 'String registration is paused: there is not enough PHP memory to continue. Increase the memory limit or retry.';
		} else if ( 'request-cap' === reason ) {
			message = texts.stalledCap
				|| 'String registration is paused after many background requests.';
		} else {
			message = texts.stalledError
				|| 'String registration was interrupted by a server error.';
		}

		renderNotice( 'warning', message, texts.retry || 'Retry', restart, false );
	}

	function restart() {
		stopped = false;
		workRequests = 0;
		busyRequests = 0;
		errorCount = 0;
		announcedProcessing = false;
		removeNotice();
		schedule( 0 );
	}

	function schedule( delay ) {
		if ( ! stopped ) {
			window.setTimeout( processNextChunk, delay );
		}
	}

	function processNextChunk() {
		if ( stopped || inFlight ) {
			return;
		}

		// Hidden tabs stop polling entirely; the visibilitychange listener
		// resumes with an immediate request when the tab is shown again.
		if ( document.hidden ) {
			resumeOnVisible = true;
			return;
		}

		inFlight = true;

		window.fetch( config.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: '{}'
		} ).then( function( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Pending strings worker returned HTTP ' + response.status );
			}

			return response.json();
		} ).then( function( result ) {
			inFlight = false;

			if ( ! result || result.error ) {
				handleError();
				return;
			}

			errorCount = 0;
			didProcess = didProcess || !! result.wasProcessed;

			if ( result.shouldContinue ) {
				announceProcessing();

				if ( result.workerBusy ) {
					// Waiting for another tab's worker is idle time, not work:
					// it has its own, higher cap so this tab can still take
					// over once the lock is free.
					busyRequests++;
					if ( busyRequests >= maxBusyRequests ) {
						stopped = true;
						return;
					}
					schedule( busyRetryDelay );
					return;
				}

				workRequests++;
				if ( workRequests >= maxWorkRequests ) {
					stall( 'request-cap' );
					return;
				}
				schedule( retryDelay );
				return;
			}

			if ( result.hasPending ) {
				// The server refuses to continue: with retryFreshRequest
				// explicitly false the memory admission cannot fit the work.
				stall( false === result.retryFreshRequest ? 'memory' : 'error' );
				return;
			}

			complete();
		} ).catch( function() {
			inFlight = false;
			handleError();
		} );
	}

	function handleError() {
		errorCount++;
		if ( errorCount > maxErrors ) {
			stall( 'error' );
			return;
		}

		schedule( Math.min( busyRetryDelay * Math.pow( 2, errorCount - 1 ), 10000 ) );
	}

	document.addEventListener( 'visibilitychange', function() {
		if ( ! document.hidden && resumeOnVisible && ! stopped ) {
			resumeOnVisible = false;
			schedule( 0 );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', processNextChunk, { once: true } );
	} else {
		schedule( 0 );
	}
}( window.wpmlStPendingStringsQueueWorker ) );

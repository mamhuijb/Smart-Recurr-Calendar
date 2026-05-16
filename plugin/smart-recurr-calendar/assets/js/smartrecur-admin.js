/**
 * SmartRecur admin scripts.
 *
 * Plain vanilla JS (no build step). Handles the appointment recurrence-builder
 * show/hide logic and the Office 365 connect / calendar / sync interactions.
 */
( function () {
	'use strict';

	var cfg = window.smartrecurAdmin || {};

	/**
	 * Call a SmartRecur REST endpoint with the WP nonce.
	 *
	 * @param {string} method HTTP method.
	 * @param {string} path   Path relative to the smartrecur/v1 base.
	 * @param {Object} [body] Optional JSON body.
	 * @returns {Promise<Object>}
	 */
	function rest( method, path, body ) {
		return fetch( cfg.restUrl + path.replace( /^\//, '' ), {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: body !== undefined ? JSON.stringify( body ) : undefined
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				if ( ! res.ok ) {
					throw new Error( ( data && ( data.message || data.error ) ) || ( 'HTTP ' + res.status ) );
				}
				return data;
			} );
		} );
	}

	/* -----------------------------------------------------------------
	 * Appointment recurrence builder + live preview.
	 * --------------------------------------------------------------- */
	function initRecurrenceBuilder() {
		var form = document.getElementById( 'smartrecur-appointment-form' );
		if ( ! form ) {
			return;
		}

		var oneTime   = document.getElementById( 'sr-onetime-fields' );
		var recurring = document.getElementById( 'sr-recurring-fields' );
		var absRows   = form.querySelectorAll( '.sr-pattern-absolute' );
		var relRows   = form.querySelectorAll( '.sr-pattern-relative' );
		var prevList  = document.getElementById( 'sr-preview-list' );
		var prevCount = document.getElementById( 'sr-preview-count' );
		var prevRule  = document.getElementById( 'sr-preview-rule' );
		var debounce  = null;

		// Reflect the checked radio in each segmented control via an is-active class.
		function paintSegments() {
			form.querySelectorAll( '.sr-seg-opt' ).forEach( function ( opt ) {
				var input = opt.querySelector( 'input[type="radio"]' );
				opt.classList.toggle( 'is-active', !! ( input && input.checked ) );
			} );
		}

		function syncScheduleType() {
			var rec = form.querySelector( 'input[name="is_recurring"]:checked' );
			var on  = rec && '1' === rec.value;
			if ( oneTime ) { oneTime.style.display = on ? 'none' : ''; }
			if ( recurring ) { recurring.style.display = on ? '' : 'none'; }
		}

		function syncPattern() {
			var pattern = form.querySelector( 'input[name="pattern_type"]:checked' );
			var isRel   = pattern && 'RELATIVE' === pattern.value;
			absRows.forEach( function ( r ) { r.style.display = isRel ? 'none' : ''; } );
			relRows.forEach( function ( r ) { r.style.display = isRel ? '' : 'none'; } );
		}

		function fieldVal( name ) {
			var el = form.querySelector( '[name="' + name + '"]' );
			return el ? el.value : '';
		}

		function refreshPreview() {
			if ( ! prevList ) { return; }
			var rec = form.querySelector( 'input[name="is_recurring"]:checked' );
			if ( ! rec || '1' !== rec.value ) { return; }
			var pattern = form.querySelector( 'input[name="pattern_type"]:checked' );

			prevList.innerHTML = '<div class="sr-empty-sub" style="padding:8px;">…</div>';
			rest( 'POST', 'appointments/generate', {
				frequency:   fieldVal( 'frequency' ),
				patternType: pattern ? pattern.value : 'RELATIVE',
				dayOfMonth:  fieldVal( 'day_of_month' ),
				ordinal:     fieldVal( 'ordinal' ),
				weekday:     fieldVal( 'weekday' ),
				startMonth:  fieldVal( 'start_month' ),
				startYear:   fieldVal( 'start_year' )
			} ).then( function ( data ) {
				var dates = ( data && data.dates ) || [];
				prevList.innerHTML = '';
				dates.slice( 0, 8 ).forEach( function ( d ) {
					var row = document.createElement( 'div' );
					row.className = 'sr-upcoming-item';
					row.innerHTML = '<span class="sr-upcoming-dot" style="background:var(--sr-primary)"></span>'
						+ '<span class="sr-upcoming-body"><span class="sr-upcoming-title">' + d + '</span></span>';
					prevList.appendChild( row );
				} );
				if ( ! dates.length ) {
					prevList.innerHTML = '<div class="sr-empty-sub" style="padding:8px;">No dates</div>';
				}
				if ( prevCount ) { prevCount.textContent = String( ( data && data.count ) || 0 ); }
				if ( prevRule ) { prevRule.textContent = ( data && data.rule ) || ''; }
			} ).catch( function () {
				prevList.innerHTML = '<div class="sr-empty-sub" style="padding:8px;">Preview unavailable</div>';
			} );
		}

		function queuePreview() {
			clearTimeout( debounce );
			debounce = setTimeout( refreshPreview, 250 );
		}

		form.querySelectorAll( 'input[name="is_recurring"]' ).forEach( function ( el ) {
			el.addEventListener( 'change', function () { paintSegments(); syncScheduleType(); queuePreview(); } );
		} );
		form.querySelectorAll( 'input[name="pattern_type"]' ).forEach( function ( el ) {
			el.addEventListener( 'change', function () { paintSegments(); syncPattern(); queuePreview(); } );
		} );
		[ 'frequency', 'day_of_month', 'ordinal', 'weekday', 'start_month' ].forEach( function ( name ) {
			var el = form.querySelector( '[name="' + name + '"]' );
			if ( el ) { el.addEventListener( 'change', queuePreview ); }
		} );

		paintSegments();
		syncScheduleType();
		syncPattern();
		refreshPreview();
	}

	/* -----------------------------------------------------------------
	 * Office 365 connect / calendar / sync.
	 * --------------------------------------------------------------- */
	function initOffice365() {
		var connectBtn = document.getElementById( 'sr-o365-connect' );
		if ( connectBtn ) {
			connectBtn.addEventListener( 'click', function () {
				connectBtn.disabled = true;
				rest( 'POST', 'integrations/office365/connect' ).then( function ( data ) {
					if ( ! data.url ) {
						throw new Error( 'No authorization URL returned.' );
					}
					var popup = window.open( data.url, 'smartrecur_o365', 'width=520,height=640' );
					var timer = setInterval( function () {
						if ( popup && popup.closed ) {
							clearInterval( timer );
							window.location.reload();
						}
					}, 800 );
				} ).catch( function ( err ) {
					alert( 'Office 365: ' + err.message );
					connectBtn.disabled = false;
				} );
			} );
		}

		// OAuth popup posts a message back when done.
		window.addEventListener( 'message', function ( ev ) {
			if ( ! ev.data || typeof ev.data !== 'object' ) {
				return;
			}
			if ( 'OAUTH_SUCCESS' === ev.data.type || 'OAUTH_ERROR' === ev.data.type ) {
				window.location.reload();
			}
		} );

		var calendarSelect = document.getElementById( 'sr-o365-calendar' );
		if ( calendarSelect ) {
			rest( 'GET', 'integrations/office365/calendars' ).then( function ( data ) {
				var current = calendarSelect.getAttribute( 'data-current' ) || '';
				calendarSelect.innerHTML = '';
				( data.calendars || [] ).forEach( function ( cal ) {
					var opt = document.createElement( 'option' );
					opt.value = cal.id;
					opt.textContent = cal.name;
					if ( cal.id === current ) {
						opt.selected = true;
					}
					calendarSelect.appendChild( opt );
				} );
				if ( ! calendarSelect.options.length ) {
					var none = document.createElement( 'option' );
					none.textContent = 'No calendars found';
					calendarSelect.appendChild( none );
				}
			} ).catch( function ( err ) {
				calendarSelect.innerHTML = '<option>' + err.message + '</option>';
			} );
		}

		var saveCalBtn = document.getElementById( 'sr-o365-save-calendar' );
		if ( saveCalBtn && calendarSelect ) {
			saveCalBtn.addEventListener( 'click', function () {
				var opt = calendarSelect.options[ calendarSelect.selectedIndex ];
				if ( ! opt || ! opt.value ) {
					return;
				}
				saveCalBtn.disabled = true;
				rest( 'POST', 'integrations/office365/select-calendar', {
					calendarId: opt.value,
					calendarName: opt.textContent
				} ).then( function () {
					window.location.reload();
				} ).catch( function ( err ) {
					alert( 'Office 365: ' + err.message );
					saveCalBtn.disabled = false;
				} );
			} );
		}

		var syncBtn = document.getElementById( 'sr-o365-sync-now' );
		if ( syncBtn ) {
			syncBtn.addEventListener( 'click', function () {
				syncBtn.disabled = true;
				syncBtn.textContent = 'Syncing…';
				rest( 'POST', 'integrations/office365/sync-now' ).then( function () {
					window.location.reload();
				} ).catch( function ( err ) {
					alert( 'Office 365: ' + err.message );
					syncBtn.disabled = false;
					syncBtn.textContent = 'Sync now';
				} );
			} );
		}

		var disconnectBtn = document.getElementById( 'sr-o365-disconnect' );
		if ( disconnectBtn ) {
			disconnectBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Disconnect Office 365? Two-way sync will stop.' ) ) {
					return;
				}
				disconnectBtn.disabled = true;
				rest( 'POST', 'integrations/office365/disconnect' ).then( function () {
					window.location.reload();
				} ).catch( function ( err ) {
					alert( 'Office 365: ' + err.message );
					disconnectBtn.disabled = false;
				} );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initRecurrenceBuilder();
		initOffice365();
	} );
}() );

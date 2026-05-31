/**
 * Checkout integration for the Delivery Date Picker.
 *
 * @package DeliveryDatePicker
 */
( function ( window, document ) {
	'use strict';

	var cfg = window.DDPCheckout || {};
	var i18n = cfg.i18n || {};

	function $( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}

	function api( path ) {
		var base = ( cfg.restUrl || '' ).replace( /\/$/, '' );
		return base + path;
	}

	function fetchJson( url ) {
		return fetch( url, {
			headers: { 'X-WP-Nonce': cfg.nonce || '' },
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function formatPretty( ymd ) {
		// Build a friendly label without relying on locale data round-trips.
		var parts = ymd.split( '-' );
		var date = new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );
		var months = ( cfg.i18n && cfg.i18n.months ) || [];
		var monthName = months[ date.getMonth() ] || ( date.getMonth() + 1 );
		return monthName + ' ' + date.getDate() + ', ' + date.getFullYear();
	}

	function init() {
		var field = $( '#ddp-checkout-field' );
		if ( ! field || field.getAttribute( 'data-ddp-ready' ) === '1' ) {
			return;
		}
		field.setAttribute( 'data-ddp-ready', '1' );

		if ( cfg.accent ) {
			field.style.setProperty( '--ddp-accent', cfg.accent );
		}

		var calMount = $( '#ddp-calendar', field );
		var dateInput = $( '#ddp_delivery_date', field );
		var slotInput = $( '#ddp_slot_id', field );
		var slotsWrap = $( '#ddp-slots', field );
		var slotsGrid = $( '#ddp-slots-grid', field );
		var summary = $( '#ddp-summary', field );
		var summaryText = $( '#ddp-summary-text', field );
		var changeBtn = $( '#ddp-change-btn', field );
		var errorBox = $( '#ddp-error', field );
		var calWrap = $( '.ddp-calendar-wrap', field );

		if ( ! calMount || ! window.DDPCalendar ) {
			return;
		}

		var presetDate = dateInput && dateInput.value ? dateInput.value : '';
		var presetSlot = slotInput && slotInput.value ? parseInt( slotInput.value, 10 ) : 0;

		var startYear, startMonth;
		var base = presetDate || cfg.minDate;
		if ( base ) {
			startYear = parseInt( base.slice( 0, 4 ), 10 );
			startMonth = parseInt( base.slice( 5, 7 ), 10 );
		}

		function clearError() {
			if ( errorBox ) {
				errorBox.hidden = true;
				errorBox.textContent = '';
			}
		}

		function showSummary( ymd ) {
			if ( summary && summaryText ) {
				summaryText.textContent = ( i18n.selected || 'Delivery date' ) + ': ' + formatPretty( ymd );
				summary.hidden = false;
			}
			if ( calWrap ) {
				calWrap.classList.add( 'ddp-collapsed' );
			}
		}

		function expandCalendar() {
			if ( summary ) {
				summary.hidden = true;
			}
			if ( calWrap ) {
				calWrap.classList.remove( 'ddp-collapsed' );
			}
		}

		var controller = window.DDPCalendar.create( calMount, {
			year: startYear,
			month: startMonth,
			firstDay: typeof cfg.firstDay === 'number' ? cfg.firstDay : 1,
			minDate: cfg.minDate,
			maxDate: cfg.maxDate,
			months: cfg.i18n && cfg.i18n.months,
			weekdays: cfg.i18n && cfg.i18n.weekdays,
			selected: presetDate,
			i18n: { prev: i18n.prev, next: i18n.next },
			onNavigate: function ( year, month ) {
				loadMonth( year, month );
			},
			onSelect: function ( ymd ) {
				clearError();
				dateInput.value = ymd;
				if ( slotInput ) {
					slotInput.value = '';
				}
				showSummary( ymd );
				if ( parseInt( cfg.enableSlots, 10 ) === 1 ) {
					loadSlots( ymd );
				}
			}
		} );

		function loadMonth( year, month ) {
			var mm = ( month < 10 ? '0' : '' ) + month;
			fetchJson( api( '/availability?month=' + year + '-' + mm ) ).then( function ( data ) {
				if ( data && data.days ) {
					controller.setAvailability( data.days );
				}
			} ).catch( function () {} );
		}

		function loadSlots( ymd ) {
			if ( ! slotsWrap || ! slotsGrid ) {
				return;
			}
			slotsWrap.hidden = false;
			slotsGrid.innerHTML = '<div class="ddp-slots-loading">' + ( i18n.loading || 'Loading…' ) + '</div>';

			fetchJson( api( '/slots?date=' + encodeURIComponent( ymd ) ) ).then( function ( data ) {
				slotsGrid.innerHTML = '';
				var slots = ( data && data.slots ) || [];
				if ( ! slots.length ) {
					slotsGrid.innerHTML = '<div class="ddp-slots-empty">' + ( i18n.noSlots || 'No time slots available.' ) + '</div>';
					return;
				}
				slots.forEach( function ( slot ) {
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'ddp-slot-chip';
					btn.setAttribute( 'data-slot-id', slot.id );

					var label = document.createElement( 'span' );
					label.className = 'ddp-slot-chip-label';
					label.textContent = slot.label;
					btn.appendChild( label );

					var meta = document.createElement( 'span' );
					meta.className = 'ddp-slot-chip-meta';
					if ( ! slot.available ) {
						meta.textContent = i18n.full || 'Full';
						btn.classList.add( 'ddp-is-disabled' );
						btn.disabled = true;
					} else if ( slot.remaining === null || typeof slot.remaining === 'undefined' ) {
						meta.textContent = i18n.unlimited || '';
					} else {
						meta.textContent = slot.remaining + ' ' + ( i18n.left || 'left' );
					}
					btn.appendChild( meta );

					if ( slotInput && parseInt( slotInput.value, 10 ) === slot.id ) {
						btn.classList.add( 'ddp-is-active' );
					}

					btn.addEventListener( 'click', function () {
						if ( btn.disabled ) {
							return;
						}
						var active = slotsGrid.querySelector( '.ddp-is-active' );
						if ( active ) {
							active.classList.remove( 'ddp-is-active' );
						}
						btn.classList.add( 'ddp-is-active' );
						slotInput.value = slot.id;
						clearError();
					} );

					slotsGrid.appendChild( btn );
				} );
			} ).catch( function () {
				slotsGrid.innerHTML = '<div class="ddp-slots-empty">' + ( i18n.noSlots || 'No time slots available.' ) + '</div>';
			} );
		}

		if ( changeBtn ) {
			changeBtn.addEventListener( 'click', function () {
				expandCalendar();
			} );
		}

		// Restore previous selection on (re)render.
		if ( presetDate ) {
			showSummary( presetDate );
			if ( parseInt( cfg.enableSlots, 10 ) === 1 ) {
				loadSlots( presetDate );
			}
		}
		if ( presetSlot && slotInput ) {
			slotInput.value = presetSlot;
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Re-initialise after WooCommerce refreshes the checkout fragments.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'updated_checkout', function () {
			init();
		} );
	}
} )( window, document );

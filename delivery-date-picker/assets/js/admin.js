/**
 * Admin application logic: dashboard, schedule, settings, onboarding.
 *
 * @package DeliveryDatePicker
 */
( function ( window, document ) {
	'use strict';

	var cfg = window.DDPAdmin || {};
	var i18n = cfg.i18n || {};
	var screen = window.DDPScreen || '';

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */
	function $( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}
	function $all( sel, ctx ) {
		return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) );
	}
	function restBase() {
		return ( cfg.restUrl || '' ).replace( /\/$/, '' );
	}
	function getJson( path ) {
		return fetch( restBase() + path, {
			headers: { 'X-WP-Nonce': cfg.restNonce || '' },
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json();
		} );
	}
	function ajaxPost( action, params ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce || '' );
		Object.keys( params || {} ).forEach( function ( key ) {
			var val = params[ key ];
			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else if ( val !== null && typeof val === 'object' ) {
				Object.keys( val ).forEach( function ( k2 ) {
					var inner = val[ k2 ];
					if ( Array.isArray( inner ) ) {
						inner.forEach( function ( v ) {
							body.append( key + '[' + k2 + '][]', v );
						} );
					} else {
						body.append( key + '[' + k2 + ']', inner );
					}
				} );
			} else {
				body.append( key, val );
			}
		} );
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}
	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = ( str === null || typeof str === 'undefined' ) ? '' : String( str );
		return d.innerHTML;
	}

	/* ------------------------------------------------------------------ *
	 * Theme toggle
	 * ------------------------------------------------------------------ */
	function initTheme() {
		var app = $( '.ddp-app' );
		if ( ! app ) {
			return;
		}
		var stored = window.localStorage ? window.localStorage.getItem( 'ddpAdminTheme' ) : null;
		if ( stored ) {
			app.setAttribute( 'data-ddp-theme', stored );
		}
		var toggle = $( '#ddp-theme-toggle' );
		if ( ! toggle ) {
			return;
		}
		toggle.addEventListener( 'click', function () {
			var current = app.getAttribute( 'data-ddp-theme' );
			var resolved = current;
			if ( current === 'auto' || ! current ) {
				resolved = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
			}
			var next = resolved === 'dark' ? 'light' : 'dark';
			app.setAttribute( 'data-ddp-theme', next );
			if ( window.localStorage ) {
				window.localStorage.setItem( 'ddpAdminTheme', next );
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Dashboard
	 * ------------------------------------------------------------------ */
	function initDashboard() {
		getJson( '/stats' ).then( function ( data ) {
			if ( ! data ) {
				return;
			}
			setStat( 'today', data.today );
			setStat( 'week', data.week );
			setStat( 'upcoming', data.upcoming );
			var busiest = $( '[data-stat="busiest"]' );
			if ( busiest ) {
				busiest.textContent = data.busiestDate ? ( data.busiestDate + ' · ' + data.busiestCount ) : '—';
			}
			renderTrend( data.trend || [] );
			renderNext( data.next || [] );
		} ).catch( function () {
			renderTrendError();
		} );
	}
	function setStat( key, value ) {
		var node = $( '[data-stat="' + key + '"]' );
		if ( node ) {
			node.textContent = typeof value === 'number' ? value : ( value || '0' );
		}
	}
	function renderTrend( series ) {
		var chart = $( '#ddp-trend-chart' );
		var totalPill = $( '#ddp-trend-total' );
		if ( ! chart ) {
			return;
		}
		chart.innerHTML = '';
		if ( ! series.length ) {
			chart.innerHTML = '<div class="ddp-chart-empty">' + esc( i18n.noOrders || 'No data yet.' ) + '</div>';
			if ( totalPill ) {
				totalPill.textContent = '0';
			}
			return;
		}
		var max = 0;
		var total = 0;
		series.forEach( function ( pt ) {
			if ( pt.count > max ) {
				max = pt.count;
			}
			total += pt.count;
		} );
		if ( totalPill ) {
			totalPill.textContent = total + ' ' + ( i18n.deliveries || '' );
		}
		var wrap = document.createElement( 'div' );
		wrap.className = 'ddp-bars';
		series.forEach( function ( pt ) {
			var col = document.createElement( 'div' );
			col.className = 'ddp-bar-col';
			col.setAttribute( 'title', pt.label + ': ' + pt.count );

			var barWrap = document.createElement( 'div' );
			barWrap.className = 'ddp-bar-track';
			var bar = document.createElement( 'div' );
			bar.className = 'ddp-bar';
			var h = max > 0 ? Math.round( ( pt.count / max ) * 100 ) : 0;
			bar.style.height = ( pt.count > 0 ? Math.max( h, 6 ) : 0 ) + '%';
			if ( pt.count === 0 ) {
				bar.classList.add( 'ddp-bar-empty' );
			}
			var val = document.createElement( 'span' );
			val.className = 'ddp-bar-value';
			val.textContent = pt.count > 0 ? pt.count : '';
			bar.appendChild( val );
			barWrap.appendChild( bar );

			var lbl = document.createElement( 'span' );
			lbl.className = 'ddp-bar-label';
			lbl.textContent = pt.label;

			col.appendChild( barWrap );
			col.appendChild( lbl );
			wrap.appendChild( col );
		} );
		chart.appendChild( wrap );
	}
	function renderTrendError() {
		var chart = $( '#ddp-trend-chart' );
		if ( chart ) {
			chart.innerHTML = '<div class="ddp-chart-empty">' + esc( i18n.error || 'Unable to load.' ) + '</div>';
		}
	}
	function renderNext( items ) {
		var list = $( '#ddp-next-list' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		if ( ! items.length ) {
			list.innerHTML = '<li class="ddp-next-empty">' + esc( i18n.noOrders || 'No upcoming deliveries.' ) + '</li>';
			return;
		}
		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			li.className = 'ddp-next-item';
			var main = '<div class="ddp-next-main"><span class="ddp-next-date">' + esc( item.date ) + '</span>';
			if ( item.slot ) {
				main += '<span class="ddp-next-slot">' + esc( item.slot ) + '</span>';
			}
			main += '</div>';
			var meta = '<div class="ddp-next-meta"><span class="ddp-next-customer">' + esc( item.customer || ( '#' + item.orderId ) ) + '</span>';
			if ( item.editUrl ) {
				meta += '<a class="ddp-next-link" href="' + esc( item.editUrl ) + '">#' + esc( item.orderId ) + '</a>';
			}
			meta += '</div>';
			li.innerHTML = main + meta;
			list.appendChild( li );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Schedule
	 * ------------------------------------------------------------------ */
	function initSchedule() {
		var grid = $( '#ddp-schedule-grid' );
		if ( ! grid || ! window.DDPCalendar ) {
			return;
		}
		var today = new Date();
		var state = {
			year: today.getFullYear(),
			month: today.getMonth() + 1,
			counts: {},
			blocked: [],
			ordersByDate: {},
			selected: ''
		};

		var titleEl = $( '#ddp-cal-title' );
		var weekdaysEl = $( '#ddp-cal-weekdays' );
		var prevBtn = $( '#ddp-cal-prev' );
		var nextBtn = $( '#ddp-cal-next' );
		var dayTitle = $( '#ddp-day-title' );
		var dayOrders = $( '#ddp-day-orders' );
		var blockBtn = $( '#ddp-block-toggle' );

		function renderWeekdays() {
			if ( ! weekdaysEl ) {
				return;
			}
			weekdaysEl.innerHTML = '';
			var order = window.DDPCalendar.weekdayOrder( cfg.firstDay || 1 );
			order.forEach( function ( idx ) {
				var span = document.createElement( 'span' );
				span.textContent = ( cfg.weekdays && cfg.weekdays[ idx ] ) || '';
				weekdaysEl.appendChild( span );
			} );
		}

		function level( count, max ) {
			if ( count <= 0 ) {
				return 0;
			}
			if ( max <= 0 ) {
				return 1;
			}
			var ratio = count / max;
			if ( ratio <= 0.34 ) {
				return 1;
			}
			if ( ratio <= 0.67 ) {
				return 2;
			}
			return 3;
		}

		function render() {
			if ( titleEl ) {
				titleEl.textContent = ( cfg.months && cfg.months[ state.month - 1 ] ? cfg.months[ state.month - 1 ] : state.month ) + ' ' + state.year;
			}
			grid.innerHTML = '';
			var cells = window.DDPCalendar.monthCells( state.year, state.month, cfg.firstDay || 1 );
			var max = 0;
			Object.keys( state.counts ).forEach( function ( k ) {
				if ( state.counts[ k ] > max ) {
					max = state.counts[ k ];
				}
			} );
			var todayStr = window.DDPCalendar.todayYmd();

			cells.forEach( function ( cell ) {
				if ( ! cell ) {
					var blank = document.createElement( 'span' );
					blank.className = 'ddp-scell ddp-scell-blank';
					grid.appendChild( blank );
					return;
				}
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'ddp-scell';
				btn.setAttribute( 'data-date', cell );

				var count = state.counts[ cell ] || 0;
				var isBlocked = state.blocked.indexOf( cell ) !== -1;
				var dotLevel = level( count, max );

				if ( cell === todayStr ) {
					btn.classList.add( 'ddp-scell-today' );
				}
				if ( cell === state.selected ) {
					btn.classList.add( 'ddp-scell-selected' );
				}

				var num = document.createElement( 'span' );
				num.className = 'ddp-scell-num';
				num.textContent = parseInt( cell.slice( 8 ), 10 );
				btn.appendChild( num );

				var dot = document.createElement( 'span' );
				dot.className = 'ddp-dot ' + ( isBlocked ? 'ddp-dot-blocked' : 'ddp-dot-' + dotLevel );
				btn.appendChild( dot );

				if ( count > 0 ) {
					var badge = document.createElement( 'span' );
					badge.className = 'ddp-scell-count';
					badge.textContent = count;
					btn.appendChild( badge );
				}
				if ( isBlocked ) {
					btn.classList.add( 'ddp-scell-blocked' );
				}

				btn.addEventListener( 'click', function () {
					selectDay( cell );
				} );
				grid.appendChild( btn );
			} );
		}

		function monthBounds() {
			var mm = ( state.month < 10 ? '0' : '' ) + state.month;
			var from = state.year + '-' + mm + '-01';
			var lastDay = new Date( state.year, state.month, 0 ).getDate();
			var to = state.year + '-' + mm + '-' + ( lastDay < 10 ? '0' : '' ) + lastDay;
			return { from: from, to: to };
		}

		function load() {
			var b = monthBounds();
			getJson( '/schedule?from=' + b.from + '&to=' + b.to ).then( function ( data ) {
				state.counts = ( data && data.counts ) || {};
				state.blocked = ( data && data.blocked ) || [];
				state.ordersByDate = {};
				( ( data && data.orders ) || [] ).forEach( function ( o ) {
					if ( ! state.ordersByDate[ o.date ] ) {
						state.ordersByDate[ o.date ] = [];
					}
					state.ordersByDate[ o.date ].push( o );
				} );
				render();
				if ( state.selected ) {
					renderDay( state.selected );
				}
			} ).catch( function () {
				render();
			} );
		}

		function selectDay( ymd ) {
			state.selected = ymd;
			render();
			renderDay( ymd );
		}

		function renderDay( ymd ) {
			if ( dayTitle ) {
				dayTitle.textContent = prettyDate( ymd );
			}
			if ( blockBtn ) {
				var isBlocked = state.blocked.indexOf( ymd ) !== -1;
				blockBtn.hidden = false;
				blockBtn.textContent = isBlocked ? ( i18n.unblock || 'Unblock this day' ) : ( i18n.block || 'Block this day' );
				blockBtn.classList.toggle( 'ddp-btn-danger', ! isBlocked );
				blockBtn.onclick = function () {
					ajaxPost( 'ddp_toggle_block', { date: ymd } ).then( function ( res ) {
						if ( res && res.success ) {
							load();
						}
					} );
				};
			}
			if ( ! dayOrders ) {
				return;
			}
			var orders = state.ordersByDate[ ymd ] || [];
			dayOrders.innerHTML = '';
			if ( ! orders.length ) {
				dayOrders.innerHTML = '<p class="ddp-muted">' + esc( i18n.noOrders || 'No deliveries scheduled for this day.' ) + '</p>';
				return;
			}
			orders.forEach( function ( o ) {
				var card = document.createElement( 'div' );
				card.className = 'ddp-order-card';
				var html = '<div class="ddp-order-top"><strong>' + esc( o.customer || ( '#' + o.orderId ) ) + '</strong>';
				if ( o.editUrl ) {
					html += '<a class="ddp-order-link" href="' + esc( o.editUrl ) + '">#' + esc( o.orderId ) + '</a>';
				}
				html += '</div>';
				html += '<div class="ddp-order-meta">';
				if ( o.slot ) {
					html += '<span class="ddp-order-slot">' + esc( o.slot ) + '</span>';
				}
				html += '<span>' + esc( o.items ) + ' ' + esc( i18n.items || 'items' ) + '</span>';
				if ( o.total ) {
					html += '<span class="ddp-order-total">' + esc( o.total ) + '</span>';
				}
				html += '</div>';
				if ( o.orderStatus ) {
					html += '<span class="ddp-order-status">' + esc( o.orderStatus ) + '</span>';
				}
				if ( o.note ) {
					html += '<p class="ddp-order-note">' + esc( o.note ) + '</p>';
				}
				card.innerHTML = html;
				dayOrders.appendChild( card );
			} );
		}

		function prettyDate( ymd ) {
			var parts = ymd.split( '-' );
			var date = new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );
			var weekday = ( cfg.weekdayNames && cfg.weekdayNames[ date.getDay() ] ) || '';
			var month = ( cfg.months && cfg.months[ date.getMonth() ] ) || '';
			return weekday + ', ' + month + ' ' + date.getDate();
		}

		function navigate( delta ) {
			state.month += delta;
			if ( state.month < 1 ) {
				state.month = 12;
				state.year--;
			} else if ( state.month > 12 ) {
				state.month = 1;
				state.year++;
			}
			load();
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				navigate( -1 );
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				navigate( 1 );
			} );
		}

		renderWeekdays();
		render();
		load();
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */
	function initSettings() {
		// Tabs.
		$all( '.ddp-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var name = tab.getAttribute( 'data-tab' );
				$all( '.ddp-tab' ).forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
				} );
				$all( '.ddp-tab-panel' ).forEach( function ( p ) {
					p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === name );
				} );
			} );
		} );

		// Accent live value.
		var accent = $( '#ddp-accent_color' );
		var accentVal = $( '#ddp-accent-value' );
		if ( accent && accentVal ) {
			accent.addEventListener( 'input', function () {
				accentVal.textContent = accent.value;
			} );
		}

		// Save.
		var saveBtn = $( '#ddp-save-settings' );
		var status = $( '#ddp-save-status' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				var settings = collectSettings();
				saveBtn.disabled = true;
				if ( status ) {
					status.textContent = i18n.saving || 'Saving…';
					status.className = 'ddp-save-status is-saving';
				}
				ajaxPost( 'ddp_save_settings', { settings: settings } ).then( function ( res ) {
					saveBtn.disabled = false;
					if ( res && res.success ) {
						if ( status ) {
							status.textContent = i18n.saved || 'Saved';
							status.className = 'ddp-save-status is-saved';
						}
					} else {
						if ( status ) {
							status.textContent = ( res && res.data && res.data.message ) || i18n.error;
							status.className = 'ddp-save-status is-error';
						}
					}
				} ).catch( function () {
					saveBtn.disabled = false;
					if ( status ) {
						status.textContent = i18n.error;
						status.className = 'ddp-save-status is-error';
					}
				} );
			} );
		}

		loadSlots();
		loadHolidays();
		bindSlotAdd();
		bindHolidayAdd();
	}

	function collectSettings() {
		var out = {};
		$all( '[data-ddp-field]' ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-ddp-field' );
			if ( field.type === 'checkbox' ) {
				out[ key ] = field.checked ? 1 : 0;
			} else {
				out[ key ] = field.value;
			}
		} );
		var days = [];
		$all( '[data-ddp-weekday]' ).forEach( function ( cb ) {
			if ( cb.checked ) {
				days.push( cb.value );
			}
		} );
		out.disabled_weekdays = days;
		return out;
	}

	function loadSlots() {
		var list = $( '#ddp-slot-list' );
		if ( ! list ) {
			return;
		}
		ajaxPost( 'ddp_get_slots', {} ).then( function ( res ) {
			renderSlots( res && res.success ? res.data.slots : [] );
		} );
	}

	function renderSlots( slots ) {
		var list = $( '#ddp-slot-list' );
		var tpl = $( '#ddp-slot-template' );
		if ( ! list || ! tpl ) {
			return;
		}
		list.innerHTML = '';
		if ( ! slots || ! slots.length ) {
			var p = document.createElement( 'p' );
			p.className = 'ddp-muted';
			p.id = 'ddp-slot-empty';
			p.textContent = i18n.noSlots || '';
			list.appendChild( p );
			return;
		}
		slots.forEach( function ( slot ) {
			list.appendChild( buildSlotRow( tpl, slot ) );
		} );
	}

	function buildSlotRow( tpl, slot ) {
		var node = tpl.content.firstElementChild.cloneNode( true );
		node.setAttribute( 'data-id', slot.id || '' );
		$( '.ddp-slot-label', node ).value = slot.label || '';
		$( '.ddp-slot-start', node ).value = slot.start_time || '';
		$( '.ddp-slot-end', node ).value = slot.end_time || '';
		$( '.ddp-slot-capacity', node ).value = typeof slot.capacity === 'number' ? slot.capacity : 0;
		$( '.ddp-slot-enabled', node ).checked = slot.enabled !== 0;
		bindSlotRow( node );
		return node;
	}

	function bindSlotRow( node ) {
		var saveBtn = $( '.ddp-slot-save', node );
		var delBtn = $( '.ddp-slot-delete', node );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				var data = {
					id: node.getAttribute( 'data-id' ) || 0,
					label: $( '.ddp-slot-label', node ).value,
					start_time: $( '.ddp-slot-start', node ).value,
					end_time: $( '.ddp-slot-end', node ).value,
					capacity: $( '.ddp-slot-capacity', node ).value || 0,
					enabled: $( '.ddp-slot-enabled', node ).checked ? 1 : 0
				};
				saveBtn.classList.add( 'is-busy' );
				ajaxPost( 'ddp_save_slot', data ).then( function ( res ) {
					saveBtn.classList.remove( 'is-busy' );
					if ( res && res.success ) {
						renderSlots( res.data.slots );
					} else {
						window.alert( ( res && res.data && res.data.message ) || i18n.error );
					}
				} );
			} );
		}
		if ( delBtn ) {
			delBtn.addEventListener( 'click', function () {
				var id = node.getAttribute( 'data-id' );
				if ( ! id ) {
					node.parentNode.removeChild( node );
					return;
				}
				if ( ! window.confirm( i18n.confirmSlot || 'Delete this time slot?' ) ) {
					return;
				}
				ajaxPost( 'ddp_delete_slot', { id: id } ).then( function ( res ) {
					if ( res && res.success ) {
						renderSlots( res.data.slots );
					}
				} );
			} );
		}
	}

	function bindSlotAdd() {
		var addBtn = $( '#ddp-add-slot' );
		var list = $( '#ddp-slot-list' );
		var tpl = $( '#ddp-slot-template' );
		if ( ! addBtn || ! list || ! tpl ) {
			return;
		}
		addBtn.addEventListener( 'click', function () {
			var empty = $( '#ddp-slot-empty' );
			if ( empty ) {
				empty.remove();
			}
			list.appendChild( buildSlotRow( tpl, { id: '', label: '', start_time: '', end_time: '', capacity: 0, enabled: 1 } ) );
		} );
	}

	function loadHolidays() {
		var list = $( '#ddp-holiday-list' );
		if ( ! list ) {
			return;
		}
		ajaxPost( 'ddp_get_holidays', {} ).then( function ( res ) {
			renderHolidays( res && res.success ? res.data.holidays : [] );
		} );
	}

	function renderHolidays( holidays ) {
		var list = $( '#ddp-holiday-list' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		if ( ! holidays || ! holidays.length ) {
			var p = document.createElement( 'p' );
			p.className = 'ddp-muted';
			p.textContent = i18n.noHolidays || '';
			list.appendChild( p );
			return;
		}
		holidays.forEach( function ( h ) {
			var row = document.createElement( 'div' );
			row.className = 'ddp-holiday-row';
			var info = '<div class="ddp-holiday-info"><span class="ddp-holiday-date">' + esc( h.pretty || h.date ) + '</span>';
			if ( h.reason ) {
				info += '<span class="ddp-holiday-reason">' + esc( h.reason ) + '</span>';
			}
			info += '</div>';
			row.innerHTML = info;
			var del = document.createElement( 'button' );
			del.type = 'button';
			del.className = 'ddp-icon-btn';
			del.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
			del.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmHol || 'Remove this holiday?' ) ) {
					return;
				}
				ajaxPost( 'ddp_delete_holiday', { id: h.id } ).then( function ( res ) {
					if ( res && res.success ) {
						renderHolidays( res.data.holidays );
					}
				} );
			} );
			row.appendChild( del );
			list.appendChild( row );
		} );
	}

	function bindHolidayAdd() {
		var btn = $( '#ddp-add-holiday' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var dateEl = $( '#ddp-holiday-date' );
			var reasonEl = $( '#ddp-holiday-reason' );
			if ( ! dateEl || ! dateEl.value ) {
				dateEl.focus();
				return;
			}
			ajaxPost( 'ddp_add_holiday', { date: dateEl.value, reason: reasonEl ? reasonEl.value : '' } ).then( function ( res ) {
				if ( res && res.success ) {
					renderHolidays( res.data.holidays );
					dateEl.value = '';
					if ( reasonEl ) {
						reasonEl.value = '';
					}
				} else {
					window.alert( ( res && res.data && res.data.message ) || i18n.error );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Onboarding
	 * ------------------------------------------------------------------ */
	function initOnboarding() {
		var step = 1;
		var totalSteps = 4;
		var nextBtn = $( '#ddp-ob-next' );
		var backBtn = $( '#ddp-ob-back' );
		var bar = $( '#ddp-progress-bar' );

		function show( n ) {
			step = Math.max( 1, Math.min( totalSteps, n ) );
			$all( '.ddp-step' ).forEach( function ( s ) {
				s.classList.toggle( 'is-active', parseInt( s.getAttribute( 'data-step' ), 10 ) === step );
			} );
			$all( '#ddp-step-indicator li' ).forEach( function ( li ) {
				var dot = parseInt( li.getAttribute( 'data-step-dot' ), 10 );
				li.classList.toggle( 'is-active', dot === step );
				li.classList.toggle( 'is-done', dot < step );
			} );
			if ( bar ) {
				bar.style.width = Math.round( ( step / totalSteps ) * 100 ) + '%';
			}
			if ( backBtn ) {
				backBtn.hidden = step === 1;
			}
			if ( nextBtn ) {
				nextBtn.textContent = step === totalSteps ? ( i18n.finishBtn || 'Finish setup' ) : ( i18n.continueBtn || 'Continue' );
			}
		}

		$all( '.ddp-choice' ).forEach( function ( choice ) {
			choice.addEventListener( 'click', function () {
				$all( '.ddp-choice' ).forEach( function ( c ) {
					c.classList.remove( 'is-selected' );
				} );
				choice.classList.add( 'is-selected' );
				var lead = choice.getAttribute( 'data-lead' );
				var leadInput = $( '#ddp-ob-lead' );
				if ( leadInput && lead !== null ) {
					leadInput.value = lead;
				}
				show( 2 );
			} );
		} );

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				if ( step < totalSteps ) {
					show( step + 1 );
					return;
				}
				finish();
			} );
		}
		if ( backBtn ) {
			backBtn.addEventListener( 'click', function () {
				show( step - 1 );
			} );
		}

		function finish() {
			var disabled = [];
			$all( '.ddp-ob-weekday' ).forEach( function ( cb ) {
				if ( ! cb.checked ) {
					disabled.push( cb.value );
				}
			} );
			var leadEl = $( '#ddp-ob-lead' );
			var advEl = $( '#ddp-ob-advance' );
			var slotsEl = $( '#ddp-ob-slots' );
			var settings = {
				enabled: 1,
				min_lead_days: leadEl ? leadEl.value : 1,
				max_advance_days: advEl ? advEl.value : 60,
				disabled_weekdays: disabled,
				enable_time_slots: slotsEl && slotsEl.checked ? 1 : 0
			};
			if ( nextBtn ) {
				nextBtn.disabled = true;
				nextBtn.textContent = ( cfg.i18n && cfg.i18n.saving ) || 'Saving…';
			}
			ajaxPost( 'ddp_complete_onboarding', { settings: settings } ).then( function ( res ) {
				if ( res && res.success && res.data && res.data.redirect ) {
					window.location.href = res.data.redirect;
				} else {
					window.location.reload();
				}
			} ).catch( function () {
				if ( nextBtn ) {
					nextBtn.disabled = false;
				}
			} );
		}

		show( 1 );
	}

	/* ------------------------------------------------------------------ *
	 * Boot
	 * ------------------------------------------------------------------ */
	function boot() {
		initTheme();
		if ( screen === 'dashboard' ) {
			initDashboard();
		} else if ( screen === 'schedule' ) {
			initSchedule();
		} else if ( screen === 'settings' ) {
			initSettings();
		} else if ( screen === 'onboarding' ) {
			initOnboarding();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )( window, document );

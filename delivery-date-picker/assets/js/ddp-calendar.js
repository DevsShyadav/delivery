/**
 * DDPCalendar — a tiny, dependency-free month calendar.
 *
 * Exposes:
 *   DDPCalendar.monthCells(year, month, firstDay)  -> Array<string|null> (Y-m-d or null blank)
 *   DDPCalendar.weekdayOrder(firstDay)             -> Array<int> weekday indices
 *   DDPCalendar.create(container, options)         -> self-contained picker controller
 *
 * @package DeliveryDatePicker
 */
( function ( window, document ) {
	'use strict';

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function ymd( y, m, d ) {
		return y + '-' + pad( m ) + '-' + pad( d );
	}

	function daysInMonth( y, m ) {
		return new Date( y, m, 0 ).getDate(); // m is 1-12.
	}

	function firstWeekday( y, m ) {
		return new Date( y, m - 1, 1 ).getDay(); // 0 (Sun) .. 6 (Sat).
	}

	function todayYmd() {
		var d = new Date();
		return ymd( d.getFullYear(), d.getMonth() + 1, d.getDate() );
	}

	function weekdayOrder( firstDay ) {
		var arr = [];
		for ( var i = 0; i < 7; i++ ) {
			arr.push( ( firstDay + i ) % 7 );
		}
		return arr;
	}

	function monthCells( year, month, firstDay ) {
		var total = daysInMonth( year, month );
		var startDow = firstWeekday( year, month );
		var lead = ( startDow - firstDay + 7 ) % 7;
		var cells = [];
		var i;
		for ( i = 0; i < lead; i++ ) {
			cells.push( null );
		}
		for ( var d = 1; d <= total; d++ ) {
			cells.push( ymd( year, month, d ) );
		}
		while ( cells.length % 7 !== 0 ) {
			cells.push( null );
		}
		while ( cells.length < 42 ) {
			cells.push( null );
		}
		return cells;
	}

	function el( tag, cls, html ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( typeof html !== 'undefined' && null !== html ) {
			node.innerHTML = html;
		}
		return node;
	}

	/**
	 * Build a self-contained calendar picker.
	 *
	 * @param {HTMLElement} container Mount point.
	 * @param {Object} options Configuration.
	 * @return {Object} controller
	 */
	function create( container, options ) {
		var opts = options || {};
		var months = opts.months || [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
		var weekdays = opts.weekdays || [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ];
		var firstDay = typeof opts.firstDay === 'number' ? opts.firstDay : 1;
		var today = new Date();
		var year = opts.year || today.getFullYear();
		var month = opts.month || ( today.getMonth() + 1 );
		var selected = opts.selected || '';
		var availability = {}; // ymd -> {a:0/1, s:status}.
		var minDate = opts.minDate || '';
		var maxDate = opts.maxDate || '';

		container.classList.add( 'ddp-cal' );
		container.innerHTML = '';

		// Header.
		var header = el( 'div', 'ddp-cal-header' );
		var prevBtn = el( 'button', 'ddp-cal-arrow', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>' );
		prevBtn.type = 'button';
		prevBtn.setAttribute( 'aria-label', opts.i18n && opts.i18n.prev ? opts.i18n.prev : 'Previous month' );
		var title = el( 'span', 'ddp-cal-month-title' );
		var nextBtn = el( 'button', 'ddp-cal-arrow', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>' );
		nextBtn.type = 'button';
		nextBtn.setAttribute( 'aria-label', opts.i18n && opts.i18n.next ? opts.i18n.next : 'Next month' );
		header.appendChild( prevBtn );
		header.appendChild( title );
		header.appendChild( nextBtn );

		// Weekday row.
		var dow = el( 'div', 'ddp-cal-dow' );
		var order = weekdayOrder( firstDay );
		order.forEach( function ( idx ) {
			dow.appendChild( el( 'span', 'ddp-cal-dow-cell', weekdays[ idx ] ) );
		} );

		// Grid.
		var grid = el( 'div', 'ddp-cal-days' );

		container.appendChild( header );
		container.appendChild( dow );
		container.appendChild( grid );

		function inRange( cell ) {
			if ( minDate && cell < minDate ) {
				return false;
			}
			if ( maxDate && cell > maxDate ) {
				return false;
			}
			return true;
		}

		function isSelectable( cell ) {
			if ( ! cell || ! inRange( cell ) ) {
				return false;
			}
			var info = availability[ cell ];
			if ( info ) {
				return !! info.a;
			}
			// Unknown until data loads: allow only if in range (avoids flashing).
			return true;
		}

		function renderGrid() {
			grid.innerHTML = '';
			var cells = monthCells( year, month, firstDay );
			var t = todayYmd();
			cells.forEach( function ( cell ) {
				if ( ! cell ) {
					grid.appendChild( el( 'span', 'ddp-cal-day ddp-is-blank' ) );
					return;
				}
				var btn = el( 'button', 'ddp-cal-day', String( parseInt( cell.slice( 8 ), 10 ) ) );
				btn.type = 'button';
				btn.setAttribute( 'data-date', cell );
				if ( cell === t ) {
					btn.classList.add( 'ddp-is-today' );
				}
				if ( cell === selected ) {
					btn.classList.add( 'ddp-is-selected' );
					btn.setAttribute( 'aria-pressed', 'true' );
				}
				var selectable = isSelectable( cell );
				if ( ! selectable ) {
					btn.classList.add( 'ddp-is-disabled' );
					btn.disabled = true;
				}
				var info = availability[ cell ];
				if ( info && info.s === 'full' ) {
					btn.classList.add( 'ddp-is-full' );
					btn.setAttribute( 'title', 'Full' );
				}
				grid.appendChild( btn );
			} );
		}

		function renderTitle() {
			title.textContent = months[ month - 1 ] + ' ' + year;
		}

		function render() {
			renderTitle();
			renderGrid();
		}

		function navigate( delta ) {
			month += delta;
			if ( month < 1 ) {
				month = 12;
				year--;
			} else if ( month > 12 ) {
				month = 1;
				year++;
			}
			render();
			if ( typeof opts.onNavigate === 'function' ) {
				opts.onNavigate( year, month );
			}
		}

		prevBtn.addEventListener( 'click', function () {
			navigate( -1 );
		} );
		nextBtn.addEventListener( 'click', function () {
			navigate( 1 );
		} );

		grid.addEventListener( 'click', function ( e ) {
			var target = e.target.closest( '.ddp-cal-day' );
			if ( ! target || target.disabled || target.classList.contains( 'ddp-is-blank' ) ) {
				return;
			}
			var date = target.getAttribute( 'data-date' );
			selected = date;
			// Update selected visual state.
			var prev = grid.querySelector( '.ddp-is-selected' );
			if ( prev ) {
				prev.classList.remove( 'ddp-is-selected' );
				prev.removeAttribute( 'aria-pressed' );
			}
			target.classList.add( 'ddp-is-selected' );
			target.setAttribute( 'aria-pressed', 'true' );
			if ( typeof opts.onSelect === 'function' ) {
				opts.onSelect( date );
			}
		} );

		render();
		if ( typeof opts.onNavigate === 'function' ) {
			opts.onNavigate( year, month );
		}

		return {
			render: render,
			setAvailability: function ( map ) {
				availability = map || {};
				renderGrid();
			},
			setBounds: function ( min, max ) {
				minDate = min || minDate;
				maxDate = max || maxDate;
				renderGrid();
			},
			setSelected: function ( date ) {
				selected = date || '';
				renderGrid();
			},
			getSelected: function () {
				return selected;
			},
			getMonth: function () {
				return { year: year, month: month };
			}
		};
	}

	window.DDPCalendar = {
		monthCells: monthCells,
		weekdayOrder: weekdayOrder,
		create: create,
		ymd: ymd,
		todayYmd: todayYmd
	};
} )( window, document );

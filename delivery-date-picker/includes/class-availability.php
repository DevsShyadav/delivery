<?php
/**
 * Availability engine — the single source of truth for what a customer can pick.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_today;
use function DDP\ddp_timezone;
use function DDP\ddp_validate_date;

/**
 * Computes selectable dates, time slots and remaining capacity.
 */
class Availability {

	const STATUS_AVAILABLE   = 'available';
	const STATUS_PAST        = 'past';
	const STATUS_LEAD        = 'lead_time';
	const STATUS_HORIZON     = 'beyond_horizon';
	const STATUS_WEEKDAY_OFF = 'weekday_off';
	const STATUS_HOLIDAY     = 'holiday';
	const STATUS_FULL        = 'full';

	/**
	 * The earliest selectable date (today + lead days), Y-m-d.
	 *
	 * @return string
	 */
	public static function min_date() {
		$lead = (int) Settings::get( 'min_lead_days', 1 );
		$date = new \DateTimeImmutable( ddp_today(), ddp_timezone() );
		if ( $lead > 0 ) {
			$date = $date->modify( '+' . $lead . ' day' );
		}
		return $date->format( 'Y-m-d' );
	}

	/**
	 * The latest selectable date (today + max advance), Y-m-d.
	 *
	 * @return string
	 */
	public static function max_date() {
		$max  = (int) Settings::get( 'max_advance_days', 60 );
		$date = ( new \DateTimeImmutable( ddp_today(), ddp_timezone() ) )->modify( '+' . max( 1, $max ) . ' day' );
		return $date->format( 'Y-m-d' );
	}

	/**
	 * Determine the availability status of a single date.
	 *
	 * @param string $ymd Y-m-d date.
	 * @return array{available:bool,status:string,reason:string}
	 */
	public static function date_status( $ymd ) {
		$valid = ddp_validate_date( $ymd );
		if ( ! $valid ) {
			return self::result( false, self::STATUS_PAST, __( 'Invalid date.', 'delivery-date-picker' ) );
		}

		$today = ddp_today();
		if ( $valid < $today ) {
			return self::result( false, self::STATUS_PAST, __( 'This date has already passed.', 'delivery-date-picker' ) );
		}

		if ( $valid < self::min_date() ) {
			return self::result(
				false,
				self::STATUS_LEAD,
				__( 'This date is too soon — earlier than the minimum preparation time.', 'delivery-date-picker' )
			);
		}

		if ( $valid > self::max_date() ) {
			return self::result( false, self::STATUS_HORIZON, __( 'This date is too far in the future.', 'delivery-date-picker' ) );
		}

		$weekday          = (int) ( new \DateTimeImmutable( $valid, ddp_timezone() ) )->format( 'w' );
		$disabled_weekdays = (array) Settings::get( 'disabled_weekdays', array() );
		if ( in_array( $weekday, array_map( 'intval', $disabled_weekdays ), true ) ) {
			return self::result( false, self::STATUS_WEEKDAY_OFF, __( 'We do not deliver on this day of the week.', 'delivery-date-picker' ) );
		}

		if ( self::is_blocked( $valid ) ) {
			return self::result( false, self::STATUS_HOLIDAY, __( 'This date is unavailable (holiday or closed day).', 'delivery-date-picker' ) );
		}

		// Daily capacity (global) — only relevant when slots are off or as an overall cap.
		$daily_cap = (int) Settings::get( 'daily_capacity', 0 );
		if ( $daily_cap > 0 ) {
			$booked = self::booked_count( $valid );
			if ( $booked >= $daily_cap ) {
				return self::result( false, self::STATUS_FULL, __( 'Fully booked for this date.', 'delivery-date-picker' ) );
			}
		}

		// If time slots are required and none have remaining capacity, the day is full.
		if ( Settings::get( 'enable_time_slots' ) && Settings::get( 'slot_required' ) ) {
			$slots     = self::slots_for_date( $valid );
			$has_room  = false;
			foreach ( $slots as $slot ) {
				if ( $slot['available'] ) {
					$has_room = true;
					break;
				}
			}
			if ( $slots && ! $has_room ) {
				return self::result( false, self::STATUS_FULL, __( 'All delivery slots are full for this date.', 'delivery-date-picker' ) );
			}
		}

		return self::result( true, self::STATUS_AVAILABLE, '' );
	}

	/**
	 * Convenience boolean check.
	 *
	 * @param string $ymd Y-m-d.
	 * @return bool
	 */
	public static function is_available( $ymd ) {
		$status = self::date_status( $ymd );
		return (bool) $status['available'];
	}

	/**
	 * Build a month availability map for the calendar.
	 *
	 * @param int $year  4-digit year.
	 * @param int $month 1-12.
	 * @return array Keyed by Y-m-d => status array.
	 */
	public static function month_map( $year, $month ) {
		$year  = max( 1970, min( 2100, (int) $year ) );
		$month = max( 1, min( 12, (int) $month ) );

		$cache_key = "month_{$year}_{$month}";
		$cached    = Cache::get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$first = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), ddp_timezone() );
		$days  = (int) $first->format( 't' );
		$map   = array();

		for ( $d = 1; $d <= $days; $d++ ) {
			$ymd         = sprintf( '%04d-%02d-%02d', $year, $month, $d );
			$status      = self::date_status( $ymd );
			$map[ $ymd ] = array(
				'available' => $status['available'],
				'status'    => $status['status'],
			);
		}

		Cache::set( $cache_key, $map, 15 * MINUTE_IN_SECONDS );
		return $map;
	}

	/**
	 * Time slots for a date, each with availability + remaining capacity.
	 *
	 * @param string $ymd Y-m-d.
	 * @return array
	 */
	public static function slots_for_date( $ymd ) {
		$valid = ddp_validate_date( $ymd );
		if ( ! $valid ) {
			return array();
		}

		$slots  = self::enabled_slots();
		$counts = self::slot_booked_counts( $valid );
		$out    = array();

		foreach ( $slots as $slot ) {
			$capacity  = (int) $slot['capacity'];
			$booked    = isset( $counts[ (int) $slot['id'] ] ) ? (int) $counts[ (int) $slot['id'] ] : 0;
			$unlimited = ( 0 === $capacity );
			$remaining = $unlimited ? null : max( 0, $capacity - $booked );
			$available = $unlimited ? true : ( $remaining > 0 );

			$out[] = array(
				'id'        => (int) $slot['id'],
				'label'     => $slot['label'],
				'start'     => $slot['start_time'],
				'end'       => $slot['end_time'],
				'capacity'  => $capacity,
				'booked'    => $booked,
				'remaining' => $remaining,
				'available' => $available,
			);
		}

		return $out;
	}

	/**
	 * Validate that a slot id is valid + has room for a given date.
	 *
	 * @param int    $slot_id Slot id.
	 * @param string $ymd     Y-m-d.
	 * @return array{valid:bool,label:string,reason:string}
	 */
	public static function validate_slot( $slot_id, $ymd ) {
		$slot_id = (int) $slot_id;
		$slots   = self::slots_for_date( $ymd );

		foreach ( $slots as $slot ) {
			if ( $slot['id'] === $slot_id ) {
				if ( $slot['available'] ) {
					return array(
						'valid'  => true,
						'label'  => $slot['label'],
						'reason' => '',
					);
				}
				return array(
					'valid'  => false,
					'label'  => $slot['label'],
					'reason' => __( 'The selected time slot is no longer available.', 'delivery-date-picker' ),
				);
			}
		}

		return array(
			'valid'  => false,
			'label'  => '',
			'reason' => __( 'Invalid time slot selected.', 'delivery-date-picker' ),
		);
	}

	/**
	 * Get all enabled slots (cached per request).
	 *
	 * @return array
	 */
	public static function enabled_slots() {
		static $slots = null;
		if ( null !== $slots ) {
			return $slots;
		}

		global $wpdb;
		$table = Installer::table( 'slots' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results( "SELECT id, label, start_time, end_time, capacity, sort_order FROM {$table} WHERE enabled = 1 ORDER BY sort_order ASC, id ASC", ARRAY_A );

		$slots = is_array( $rows ) ? $rows : array();
		return $slots;
	}

	/**
	 * Is a date explicitly blocked (holiday)?
	 *
	 * The full blocked-date set is tiny, so it is loaded once per request.
	 *
	 * @param string $ymd Y-m-d.
	 * @return bool
	 */
	public static function is_blocked( $ymd ) {
		static $set = null;
		if ( null === $set ) {
			global $wpdb;
			$table = Installer::table( 'blocked' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$rows  = $wpdb->get_col( "SELECT blocked_date FROM {$table}" );
			$set   = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $d ) {
					$set[ $d ] = true;
				}
			}
		}
		return isset( $set[ $ymd ] );
	}

	/**
	 * Count active bookings for a date (all slots). Memoised per request.
	 *
	 * @param string $ymd Y-m-d.
	 * @return int
	 */
	public static function booked_count( $ymd ) {
		static $memo = array();
		if ( array_key_exists( $ymd, $memo ) ) {
			return $memo[ $ymd ];
		}
		global $wpdb;
		$table = Installer::table( 'bookings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$memo[ $ymd ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE delivery_date = %s AND status = 'active'", $ymd ) );
		return $memo[ $ymd ];
	}

	/**
	 * Booked counts grouped by slot for a date. Memoised per request.
	 *
	 * @param string $ymd Y-m-d.
	 * @return array<int,int> slot_id => count.
	 */
	public static function slot_booked_counts( $ymd ) {
		static $memo = array();
		if ( array_key_exists( $ymd, $memo ) ) {
			return $memo[ $ymd ];
		}
		global $wpdb;
		$table = Installer::table( 'bookings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT slot_id, COUNT(*) AS c FROM {$table} WHERE delivery_date = %s AND status = 'active' GROUP BY slot_id", $ymd ),
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['slot_id'] ] = (int) $row['c'];
			}
		}
		$memo[ $ymd ] = $out;
		return $out;
	}

	/**
	 * Helper to build a status result.
	 *
	 * @param bool   $available Available flag.
	 * @param string $status    Status code.
	 * @param string $reason    Human reason.
	 * @return array
	 */
	private static function result( $available, $status, $reason ) {
		return array(
			'available' => (bool) $available,
			'status'    => $status,
			'reason'    => $reason,
		);
	}
}

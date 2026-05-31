<?php
/**
 * REST API controller for the ddp/v1 namespace.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_validate_date;
use function DDP\ddp_admin_capability;
use function DDP\ddp_format_date;
use function DDP\ddp_today;
use function DDP\ddp_timezone;

/**
 * Registers and serves all REST endpoints.
 */
class Rest_Controller {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = DDP_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/availability',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'month' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/slots',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_slots' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/schedule',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_schedule' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'from' => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'to'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
	}

	/**
	 * Permission check for admin endpoints (capability based).
	 *
	 * @return bool|\WP_Error
	 */
	public function admin_permission() {
		if ( current_user_can( ddp_admin_capability() ) ) {
			return true;
		}
		return new \WP_Error( 'ddp_forbidden', __( 'You are not allowed to access this resource.', 'delivery-date-picker' ), array( 'status' => 403 ) );
	}

	/**
	 * GET /availability — month map for the calendar.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_availability( $request ) {
		$month = (string) $request->get_param( 'month' );

		// Expect YYYY-MM; fall back to current month.
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $month, $m ) ) {
			$now   = new \DateTimeImmutable( ddp_today(), ddp_timezone() );
			$year  = (int) $now->format( 'Y' );
			$month = (int) $now->format( 'm' );
		} else {
			$year  = (int) $m[1];
			$month = (int) $m[2];
		}

		$map  = Availability::month_map( $year, $month );
		$days = array();
		foreach ( $map as $ymd => $info ) {
			$days[ $ymd ] = array(
				'a' => $info['available'] ? 1 : 0,
				's' => $info['status'],
			);
		}

		return rest_ensure_response(
			array(
				'year'      => $year,
				'month'     => $month,
				'minDate'   => Availability::min_date(),
				'maxDate'   => Availability::max_date(),
				'days'      => $days,
				'enableSlots' => (int) Settings::get( 'enable_time_slots', 1 ),
			)
		);
	}

	/**
	 * GET /slots — slots for a date.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_slots( $request ) {
		$date = ddp_validate_date( (string) $request->get_param( 'date' ) );
		if ( ! $date ) {
			return new \WP_Error( 'ddp_bad_date', __( 'Invalid date.', 'delivery-date-picker' ), array( 'status' => 400 ) );
		}

		$status = Availability::date_status( $date );
		$slots  = Availability::slots_for_date( $date );

		$out = array();
		foreach ( $slots as $slot ) {
			$out[] = array(
				'id'        => $slot['id'],
				'label'     => $slot['label'],
				'available' => $slot['available'] ? 1 : 0,
				'remaining' => $slot['remaining'], // null = unlimited.
			);
		}

		return rest_ensure_response(
			array(
				'date'      => $date,
				'available' => $status['available'] ? 1 : 0,
				'reason'    => $status['reason'],
				'slots'     => $out,
			)
		);
	}

	/**
	 * GET /schedule — bookings within a date range (admin).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_schedule( $request ) {
		$from = ddp_validate_date( (string) $request->get_param( 'from' ) );
		$to   = ddp_validate_date( (string) $request->get_param( 'to' ) );

		if ( ! $from ) {
			$first = new \DateTimeImmutable( 'first day of this month', ddp_timezone() );
			$from  = $first->format( 'Y-m-d' );
		}
		if ( ! $to ) {
			$last = new \DateTimeImmutable( 'last day of this month', ddp_timezone() );
			$to   = $last->format( 'Y-m-d' );
		}
		if ( $to < $from ) {
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		global $wpdb;
		$table = Installer::table( 'bookings' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, delivery_date, slot_id, slot_label, status FROM {$table}
				 WHERE delivery_date BETWEEN %s AND %s AND status <> 'cancelled'
				 ORDER BY delivery_date ASC, slot_id ASC",
				$from,
				$to
			),
			ARRAY_A
		);

		$counts = array();
		$orders = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$date              = $row['delivery_date'];
				$counts[ $date ]   = isset( $counts[ $date ] ) ? $counts[ $date ] + 1 : 1;
				$order             = wc_get_order( (int) $row['order_id'] );
				$item              = array(
					'orderId'    => (int) $row['order_id'],
					'date'       => $date,
					'slot'       => (string) $row['slot_label'],
					'status'     => (string) $row['status'],
					'customer'   => '',
					'items'      => 0,
					'total'      => '',
					'orderStatus' => '',
					'editUrl'    => '',
					'note'       => '',
				);

				if ( $order ) {
					$item['customer']    = trim( $order->get_formatted_billing_full_name() );
					$item['items']       = (int) $order->get_item_count();
					$item['total']       = wp_strip_all_tags( $order->get_formatted_order_total() );
					$item['orderStatus'] = wc_get_order_status_name( $order->get_status() );
					$item['editUrl']     = esc_url_raw( $order->get_edit_order_url() );
					$item['note']        = (string) $order->get_meta( '_ddp_delivery_note' );
				}

				$orders[] = $item;
			}
		}

		return rest_ensure_response(
			array(
				'from'    => $from,
				'to'      => $to,
				'counts'  => $counts,
				'orders'  => $orders,
				'blocked' => $this->blocked_in_range( $from, $to ),
			)
		);
	}

	/**
	 * GET /stats — dashboard KPIs + trend series (admin).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats() {
		global $wpdb;
		$table = Installer::table( 'bookings' );
		$today = ddp_today();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$today_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE delivery_date = %s AND status = 'active'", $today ) );

		$week_end = ( new \DateTimeImmutable( $today, ddp_timezone() ) )->modify( '+6 day' )->format( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$week_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE delivery_date BETWEEN %s AND %s AND status = 'active'", $today, $week_end ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$upcoming = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE delivery_date >= %s AND status = 'active'", $today ) );

		// Busiest upcoming day.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$busiest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT delivery_date, COUNT(*) AS c FROM {$table} WHERE delivery_date >= %s AND status = 'active' GROUP BY delivery_date ORDER BY c DESC, delivery_date ASC LIMIT 1",
				$today
			),
			ARRAY_A
		);

		// 14-day trend.
		$trend = array();
		$start = new \DateTimeImmutable( $today, ddp_timezone() );
		for ( $i = 0; $i < 14; $i++ ) {
			$d           = $start->modify( '+' . $i . ' day' )->format( 'Y-m-d' );
			$trend[ $d ] = 0;
		}
		$trend_end = $start->modify( '+13 day' )->format( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT delivery_date, COUNT(*) AS c FROM {$table} WHERE delivery_date BETWEEN %s AND %s AND status = 'active' GROUP BY delivery_date",
				$today,
				$trend_end
			),
			ARRAY_A
		);
		if ( is_array( $trend_rows ) ) {
			foreach ( $trend_rows as $row ) {
				if ( isset( $trend[ $row['delivery_date'] ] ) ) {
					$trend[ $row['delivery_date'] ] = (int) $row['c'];
				}
			}
		}

		$trend_series = array();
		foreach ( $trend as $date => $count ) {
			$trend_series[] = array(
				'date'  => $date,
				'label' => wp_date( 'D j', ( new \DateTimeImmutable( $date, ddp_timezone() ) )->getTimestamp() ),
				'count' => (int) $count,
			);
		}

		// Next deliveries list (limit 8).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$next_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, delivery_date, slot_label FROM {$table} WHERE delivery_date >= %s AND status = 'active' ORDER BY delivery_date ASC, slot_id ASC LIMIT 8",
				$today
			),
			ARRAY_A
		);

		$next = array();
		if ( is_array( $next_rows ) ) {
			foreach ( $next_rows as $row ) {
				$order      = wc_get_order( (int) $row['order_id'] );
				$next[]     = array(
					'orderId'  => (int) $row['order_id'],
					'date'     => ddp_format_date( $row['delivery_date'] ),
					'rawDate'  => $row['delivery_date'],
					'slot'     => (string) $row['slot_label'],
					'customer' => $order ? trim( $order->get_formatted_billing_full_name() ) : '',
					'editUrl'  => $order ? esc_url_raw( $order->get_edit_order_url() ) : '',
				);
			}
		}

		return rest_ensure_response(
			array(
				'today'        => $today_count,
				'week'         => $week_count,
				'upcoming'     => $upcoming,
				'busiestDate'  => $busiest ? ddp_format_date( $busiest['delivery_date'] ) : '',
				'busiestCount' => $busiest ? (int) $busiest['c'] : 0,
				'trend'        => $trend_series,
				'next'         => $next,
			)
		);
	}

	/**
	 * Blocked dates within a range.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array
	 */
	private function blocked_in_range( $from, $to ) {
		global $wpdb;
		$table = Installer::table( 'blocked' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT blocked_date FROM {$table} WHERE blocked_date BETWEEN %s AND %s", $from, $to ) );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}
}

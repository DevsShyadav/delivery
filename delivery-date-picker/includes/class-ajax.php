<?php
/**
 * Admin AJAX handlers (all nonce + capability protected).
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_validate_date;
use function DDP\ddp_admin_capability;

/**
 * Handles admin-ajax writes for settings, slots, holidays and onboarding.
 */
class Ajax {

	const NONCE_ACTION = 'ddp_admin';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_ddp_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_ddp_save_slot', array( $this, 'save_slot' ) );
		add_action( 'wp_ajax_ddp_delete_slot', array( $this, 'delete_slot' ) );
		add_action( 'wp_ajax_ddp_get_slots', array( $this, 'get_slots' ) );
		add_action( 'wp_ajax_ddp_add_holiday', array( $this, 'add_holiday' ) );
		add_action( 'wp_ajax_ddp_delete_holiday', array( $this, 'delete_holiday' ) );
		add_action( 'wp_ajax_ddp_get_holidays', array( $this, 'get_holidays' ) );
		add_action( 'wp_ajax_ddp_toggle_block', array( $this, 'toggle_block' ) );
		add_action( 'wp_ajax_ddp_complete_onboarding', array( $this, 'complete_onboarding' ) );
	}

	/**
	 * Verify nonce + capability or die with a JSON error.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( ddp_admin_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'delivery-date-picker' ) ), 403 );
		}

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'delivery-date-picker' ) ), 400 );
		}
	}

	/**
	 * Save the settings form.
	 *
	 * @return void
	 */
	public function save_settings() {
		$this->guard();

		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Settings::sanitize().
			: array();

		$saved = Settings::update( (array) $raw );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings saved.', 'delivery-date-picker' ),
				'settings' => $saved,
			)
		);
	}

	/**
	 * Create or update a time slot.
	 *
	 * @return void
	 */
	public function save_slot() {
		$this->guard();

		global $wpdb;
		$table = Installer::table( 'slots' );

		$id         = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$label      = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$start      = isset( $_POST['start_time'] ) ? $this->sanitize_time( wp_unslash( $_POST['start_time'] ) ) : '';
		$end        = isset( $_POST['end_time'] ) ? $this->sanitize_time( wp_unslash( $_POST['end_time'] ) ) : '';
		$capacity   = isset( $_POST['capacity'] ) ? absint( $_POST['capacity'] ) : 0;
		$enabled    = ( isset( $_POST['enabled'] ) && '0' !== (string) $_POST['enabled'] ) ? 1 : 0;
		$sort_order = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

		if ( '' === $label ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a slot name.', 'delivery-date-picker' ) ), 400 );
		}

		$data    = array(
			'label'      => $label,
			'start_time' => '' !== $start ? $start : null,
			'end_time'   => '' !== $end ? $end : null,
			'capacity'   => $capacity,
			'enabled'    => $enabled,
			'sort_order' => $sort_order,
		);
		$formats = array( '%s', '%s', '%s', '%d', '%d', '%d' );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$formats[]          = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data, $formats );
			$id = (int) $wpdb->insert_id;
		}

		Cache::flush_all();
		wp_send_json_success(
			array(
				'message' => __( 'Time slot saved.', 'delivery-date-picker' ),
				'id'      => $id,
				'slots'   => $this->all_slots(),
			)
		);
	}

	/**
	 * Delete a time slot.
	 *
	 * @return void
	 */
	public function delete_slot() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid slot.', 'delivery-date-picker' ) ), 400 );
		}

		global $wpdb;
		$table = Installer::table( 'slots' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Cache::flush_all();
		wp_send_json_success(
			array(
				'message' => __( 'Time slot deleted.', 'delivery-date-picker' ),
				'slots'   => $this->all_slots(),
			)
		);
	}

	/**
	 * Return all slots.
	 *
	 * @return void
	 */
	public function get_slots() {
		$this->guard();
		wp_send_json_success( array( 'slots' => $this->all_slots() ) );
	}

	/**
	 * Add a blocked date (holiday).
	 *
	 * @return void
	 */
	public function add_holiday() {
		$this->guard();

		$date = ddp_validate_date( isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '' );
		if ( ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid date.', 'delivery-date-picker' ) ), 400 );
		}
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		global $wpdb;
		$table = Installer::table( 'blocked' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE blocked_date = %s", $date ) );
		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, array( 'reason' => $reason ), array( 'id' => (int) $exists ), array( '%s' ), array( '%d' ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'blocked_date' => $date,
					'reason'       => $reason,
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s' )
			);
		}

		Cache::flush_all();
		wp_send_json_success(
			array(
				'message'  => __( 'Holiday added.', 'delivery-date-picker' ),
				'holidays' => $this->all_holidays(),
			)
		);
	}

	/**
	 * Delete a blocked date by id.
	 *
	 * @return void
	 */
	public function delete_holiday() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid holiday.', 'delivery-date-picker' ) ), 400 );
		}

		global $wpdb;
		$table = Installer::table( 'blocked' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		Cache::flush_all();
		wp_send_json_success(
			array(
				'message'  => __( 'Holiday removed.', 'delivery-date-picker' ),
				'holidays' => $this->all_holidays(),
			)
		);
	}

	/**
	 * Return all holidays.
	 *
	 * @return void
	 */
	public function get_holidays() {
		$this->guard();
		wp_send_json_success( array( 'holidays' => $this->all_holidays() ) );
	}

	/**
	 * Toggle a single date blocked/unblocked (used from the schedule calendar).
	 *
	 * @return void
	 */
	public function toggle_block() {
		$this->guard();

		$date = ddp_validate_date( isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '' );
		if ( ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 'delivery-date-picker' ) ), 400 );
		}

		global $wpdb;
		$table = Installer::table( 'blocked' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE blocked_date = %s", $date ) );

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'id' => (int) $exists ), array( '%d' ) );
			$blocked = false;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'blocked_date' => $date,
					'reason'       => __( 'Blocked from schedule', 'delivery-date-picker' ),
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s' )
			);
			$blocked = true;
		}

		Cache::flush_all();
		wp_send_json_success(
			array(
				'date'    => $date,
				'blocked' => $blocked,
				'message' => $blocked ? __( 'Date blocked.', 'delivery-date-picker' ) : __( 'Date unblocked.', 'delivery-date-picker' ),
			)
		);
	}

	/**
	 * Complete the onboarding wizard (saves initial settings + marks done).
	 *
	 * @return void
	 */
	public function complete_onboarding() {
		$this->guard();

		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Settings::sanitize().
			: array();

		if ( ! empty( $raw ) ) {
			Settings::update( (array) $raw );
		}

		update_option( 'ddp_onboarded', 1, false );

		wp_send_json_success(
			array(
				'message'  => __( 'You are all set!', 'delivery-date-picker' ),
				'redirect' => admin_url( 'admin.php?page=ddp-dashboard' ),
			)
		);
	}

	/**
	 * Normalise an HH:MM or HH:MM:SS time string.
	 *
	 * @param string $value Raw value.
	 * @return string Empty string if invalid.
	 */
	private function sanitize_time( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m ) ) {
			$sec = isset( $m[3] ) ? $m[3] : '00';
			return sprintf( '%s:%s:%s', $m[1], $m[2], $sec );
		}
		return '';
	}

	/**
	 * Fetch all slots as arrays for JSON.
	 *
	 * @return array
	 */
	private function all_slots() {
		global $wpdb;
		$table = Installer::table( 'slots' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results( "SELECT id, label, start_time, end_time, capacity, sort_order, enabled FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'id'         => (int) $row['id'],
					'label'      => $row['label'],
					'start_time' => $row['start_time'] ? substr( $row['start_time'], 0, 5 ) : '',
					'end_time'   => $row['end_time'] ? substr( $row['end_time'], 0, 5 ) : '',
					'capacity'   => (int) $row['capacity'],
					'sort_order' => (int) $row['sort_order'],
					'enabled'    => (int) $row['enabled'],
				);
			}
		}
		return $out;
	}

	/**
	 * Fetch all holidays as arrays for JSON.
	 *
	 * @return array
	 */
	private function all_holidays() {
		global $wpdb;
		$table = Installer::table( 'blocked' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results( "SELECT id, blocked_date, reason FROM {$table} ORDER BY blocked_date ASC", ARRAY_A );

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'id'     => (int) $row['id'],
					'date'   => $row['blocked_date'],
					'pretty' => \DDP\ddp_format_date( $row['blocked_date'] ),
					'reason' => $row['reason'],
				);
			}
		}
		return $out;
	}
}

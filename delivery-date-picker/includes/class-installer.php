<?php
/**
 * Activation / upgrade / deactivation routines.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

/**
 * Handles database schema creation and version migrations.
 */
class Installer {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		Settings::install_defaults();
		self::seed_default_slots();

		if ( false === get_option( 'ddp_db_version' ) ) {
			update_option( 'ddp_db_version', DDP_DB_VERSION, false );
		}

		// Flag a fresh install so we can show the onboarding wizard.
		if ( false === get_option( 'ddp_onboarded' ) ) {
			update_option( 'ddp_onboarded', 0, false );
		}

		update_option( 'ddp_db_version', DDP_DB_VERSION, false );
		flush_rewrite_rules();
	}

	/**
	 * Run on deactivation. We keep data (clean up only happens on uninstall).
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cache::flush_all();
		flush_rewrite_rules();
	}

	/**
	 * Run on every admin load to migrate the schema if the version changed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'ddp_db_version' );
		if ( DDP_DB_VERSION !== $installed ) {
			self::create_tables();
			Settings::install_defaults();
			update_option( 'ddp_db_version', DDP_DB_VERSION, false );
		}
	}

	/**
	 * Table name helpers.
	 *
	 * @param string $name Base name (slots|blocked|bookings).
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		$map = array(
			'slots'    => $wpdb->prefix . 'ddp_time_slots',
			'blocked'  => $wpdb->prefix . 'ddp_blocked_dates',
			'bookings' => $wpdb->prefix . 'ddp_bookings',
		);
		return isset( $map[ $name ] ) ? $map[ $name ] : '';
	}

	/**
	 * Create / update tables with dbDelta.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$slots           = self::table( 'slots' );
		$blocked         = self::table( 'blocked' );
		$bookings        = self::table( 'bookings' );

		$sql = array();

		$sql[] = "CREATE TABLE {$slots} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(120) NOT NULL DEFAULT '',
			start_time TIME NULL DEFAULT NULL,
			end_time TIME NULL DEFAULT NULL,
			capacity INT UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY enabled (enabled),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$blocked} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			blocked_date DATE NOT NULL,
			reason VARCHAR(190) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY blocked_date (blocked_date)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$bookings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			delivery_date DATE NOT NULL,
			slot_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			slot_label VARCHAR(120) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY delivery_date (delivery_date),
			KEY slot_id (slot_id),
			KEY status (status)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Seed a couple of sensible default time slots (only if none exist).
	 *
	 * @return void
	 */
	private static function seed_default_slots() {
		global $wpdb;

		$slots = self::table( 'slots' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$slots}" );

		if ( $count > 0 ) {
			return;
		}

		$now      = current_time( 'mysql' );
		$defaults = array(
			array( 'Morning (9:00 AM – 12:00 PM)', '09:00:00', '12:00:00', 0, 1 ),
			array( 'Afternoon (12:00 PM – 4:00 PM)', '12:00:00', '16:00:00', 0, 2 ),
			array( 'Evening (4:00 PM – 8:00 PM)', '16:00:00', '20:00:00', 0, 3 ),
		);

		foreach ( $defaults as $slot ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$slots,
				array(
					'label'      => $slot[0],
					'start_time' => $slot[1],
					'end_time'   => $slot[2],
					'capacity'   => $slot[3],
					'sort_order' => $slot[4],
					'enabled'    => 1,
					'created_at' => $now,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
		}
	}
}

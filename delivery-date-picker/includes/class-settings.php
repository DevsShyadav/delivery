<?php
/**
 * Settings store: schema, defaults, sanitization, accessors.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

/**
 * Centralised settings management.
 */
class Settings {

	/**
	 * Runtime cache of the settings array.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Register hooks (none required currently, kept for interface symmetry).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'           => 1,
			'field_label'       => __( 'Delivery Date', 'delivery-date-picker' ),
			'field_description' => __( 'Choose the day you would like your order to arrive.', 'delivery-date-picker' ),
			'required'          => 1,
			'min_lead_days'     => 1,
			'max_advance_days'  => 60,
			'disabled_weekdays' => array(), // 0 (Sun) .. 6 (Sat).
			'daily_capacity'    => 0,       // 0 = unlimited.
			'enable_time_slots' => 1,
			'slot_required'     => 1,
			'enable_note'       => 1,
			'note_label'        => __( 'Delivery Instructions', 'delivery-date-picker' ),
			'theme'             => 'auto',  // auto | light | dark.
			'accent_color'      => '#6366f1',
			'first_day'         => (int) get_option( 'start_of_week', 1 ),
			'placement'         => 'after_order_notes', // after_order_notes | before_payment | review_order.
		);
	}

	/**
	 * Install default settings if not present, and backfill new keys.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		$existing = get_option( DDP_SETTINGS_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$merged = wp_parse_args( $existing, self::defaults() );
		update_option( DDP_SETTINGS_KEY, $merged, true );
		self::$cache = $merged;
	}

	/**
	 * Get the full settings array.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( DDP_SETTINGS_KEY, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value override.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		$defaults = self::defaults();
		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}
		return $default;
	}

	/**
	 * Persist a sanitized settings array (merged over current values).
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized, saved settings.
	 */
	public static function update( array $input ) {
		$current   = self::all();
		$sanitized = self::sanitize( $input, $current );
		update_option( DDP_SETTINGS_KEY, $sanitized, true );
		self::$cache = $sanitized;
		Cache::flush_all();
		return $sanitized;
	}

	/**
	 * Sanitize a raw settings array.
	 *
	 * @param array $input   Raw input.
	 * @param array $current Current values to merge onto.
	 * @return array
	 */
	public static function sanitize( array $input, array $current = array() ) {
		$defaults = self::defaults();
		$base     = $current ? $current : $defaults;
		$out      = $base;

		if ( array_key_exists( 'enabled', $input ) ) {
			$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'required', $input ) ) {
			$out['required'] = empty( $input['required'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'enable_time_slots', $input ) ) {
			$out['enable_time_slots'] = empty( $input['enable_time_slots'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'slot_required', $input ) ) {
			$out['slot_required'] = empty( $input['slot_required'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'enable_note', $input ) ) {
			$out['enable_note'] = empty( $input['enable_note'] ) ? 0 : 1;
		}

		if ( isset( $input['field_label'] ) ) {
			$out['field_label'] = sanitize_text_field( wp_unslash( $input['field_label'] ) );
		}
		if ( isset( $input['field_description'] ) ) {
			$out['field_description'] = sanitize_text_field( wp_unslash( $input['field_description'] ) );
		}
		if ( isset( $input['note_label'] ) ) {
			$out['note_label'] = sanitize_text_field( wp_unslash( $input['note_label'] ) );
		}

		if ( isset( $input['min_lead_days'] ) ) {
			$out['min_lead_days'] = max( 0, min( 365, absint( $input['min_lead_days'] ) ) );
		}
		if ( isset( $input['max_advance_days'] ) ) {
			$out['max_advance_days'] = max( 1, min( 730, absint( $input['max_advance_days'] ) ) );
		}
		if ( isset( $input['daily_capacity'] ) ) {
			$out['daily_capacity'] = max( 0, absint( $input['daily_capacity'] ) );
		}

		if ( array_key_exists( 'disabled_weekdays', $input ) ) {
			$days = is_array( $input['disabled_weekdays'] ) ? $input['disabled_weekdays'] : array();
			$days = array_values( array_unique( array_filter( array_map( 'absint', $days ), static fn( $d ) => $d >= 0 && $d <= 6 ) ) );
			$out['disabled_weekdays'] = $days;
		}

		if ( isset( $input['theme'] ) ) {
			$theme         = sanitize_key( $input['theme'] );
			$out['theme']  = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
		}

		if ( isset( $input['placement'] ) ) {
			$placement        = sanitize_key( $input['placement'] );
			$allowed          = array( 'after_order_notes', 'before_payment', 'review_order' );
			$out['placement'] = in_array( $placement, $allowed, true ) ? $placement : 'after_order_notes';
		}

		if ( isset( $input['accent_color'] ) ) {
			$color               = sanitize_hex_color( $input['accent_color'] );
			$out['accent_color'] = $color ? $color : $defaults['accent_color'];
		}

		if ( isset( $input['first_day'] ) ) {
			$out['first_day'] = ( absint( $input['first_day'] ) === 0 ) ? 0 : 1;
		}

		return $out;
	}

	/**
	 * Reset runtime cache (used in tests / after external writes).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$cache = null;
	}
}

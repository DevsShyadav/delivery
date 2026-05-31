<?php
/**
 * Lightweight caching layer for availability data.
 *
 * Uses transients keyed by a global "generation" number. Bumping the
 * generation invalidates everything at once without scanning the options table.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

/**
 * Cache helper.
 */
class Cache {

	const GEN_OPTION = 'ddp_cache_gen';
	const TTL        = HOUR_IN_SECONDS;

	/**
	 * Current cache generation.
	 *
	 * @return int
	 */
	public static function generation() {
		$gen = (int) get_option( self::GEN_OPTION, 1 );
		return $gen > 0 ? $gen : 1;
	}

	/**
	 * Build a namespaced transient key.
	 *
	 * @param string $key Logical key.
	 * @return string
	 */
	private static function key( $key ) {
		return 'ddp_' . self::generation() . '_' . md5( $key );
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key Logical key.
	 * @return mixed|false
	 */
	public static function get( $key ) {
		return get_transient( self::key( $key ) );
	}

	/**
	 * Store a cached value.
	 *
	 * @param string $key   Logical key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime in seconds.
	 * @return void
	 */
	public static function set( $key, $value, $ttl = self::TTL ) {
		set_transient( self::key( $key ), $value, (int) $ttl );
	}

	/**
	 * Invalidate every cached entry by bumping the generation counter.
	 *
	 * @return void
	 */
	public static function flush_all() {
		$gen = self::generation() + 1;
		update_option( self::GEN_OPTION, $gen, false );
	}
}

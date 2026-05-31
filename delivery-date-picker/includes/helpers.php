<?php
/**
 * Procedural helper functions.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

/**
 * Get a single setting value with default fallback.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function ddp_get_setting( $key, $default = null ) {
	return Settings::get( $key, $default );
}

/**
 * Return the site-configured timezone object.
 *
 * @return \DateTimeZone
 */
function ddp_timezone() {
	return wp_timezone();
}

/**
 * Get "today" as a Y-m-d string in the site timezone.
 *
 * @return string
 */
function ddp_today() {
	return ( new \DateTimeImmutable( 'now', ddp_timezone() ) )->format( 'Y-m-d' );
}

/**
 * Validate that a string is a real calendar date in Y-m-d format.
 *
 * @param string $value Raw value.
 * @return string|false Normalised Y-m-d on success, false on failure.
 */
function ddp_validate_date( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value ) {
		return false;
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, ddp_timezone() );
	$errs = \DateTimeImmutable::getLastErrors();

	if ( false === $date ) {
		return false;
	}
	// getLastErrors() returns false in PHP 8.2+ when there are no errors.
	if ( is_array( $errs ) && ( $errs['warning_count'] > 0 || $errs['error_count'] > 0 ) ) {
		return false;
	}

	return $date->format( 'Y-m-d' );
}

/**
 * Human-friendly formatted date using the site date format.
 *
 * @param string $ymd Y-m-d date.
 * @return string
 */
function ddp_format_date( $ymd ) {
	$valid = ddp_validate_date( $ymd );
	if ( ! $valid ) {
		return '';
	}
	$ts = ( new \DateTimeImmutable( $valid, ddp_timezone() ) )->getTimestamp();
	return wp_date( (string) get_option( 'date_format', 'F j, Y' ), $ts );
}

/**
 * Capability required to manage this plugin.
 *
 * @return string
 */
function ddp_admin_capability() {
	return apply_filters( 'ddp_admin_capability', 'manage_woocommerce' );
}

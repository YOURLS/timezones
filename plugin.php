<?php
/*
Plugin Name: Time Zones ⏰
Plugin URI: https://github.com/YOURLS/timezones
Description: Tell YOURLS what timezone you are in
Version: 1.3
Author: YOURLS contributors
Author URI: https://yourls.org/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/**
 * Register plugin admin page
 */
yourls_add_action( 'plugins_loaded', 'yourls_tzp_config' );
function yourls_tzp_config() {
    if( yourls_is_admin() ) {
        require_once __DIR__ . '/admin.php';
    }
    yourls_register_plugin_page( 'time_zone_config', 'Time Zone Configuration', 'yourls_tzp_admin_page' );
}

/**
 * Filter for time offset
 */
yourls_add_filter( 'get_time_offset', 'yourls_tzp_get_time_offset' );
function yourls_tzp_get_time_offset() {
    return yourls_tzp_timezoned_offset( yourls_tzp_read_options( 'time_zone', 'UTC' ) );
}

/**
 * Filter for timestamps
 */
yourls_add_filter( 'get_timestamp', 'yourls_tzp_get_timestamp' );
function yourls_tzp_get_timestamp($timestamp_offset, $timestamp, $offset) {
    return yourls_tzp_timezoned_timestamp( $timestamp, yourls_tzp_read_options( 'time_zone', 'UTC' ) );
}

/**
 * Filter for date + time format
 */
yourls_add_filter( 'get_datetime_format', 'yourls_tzp_get_datetime_format' );
function yourls_tzp_get_datetime_format() {
    return yourls_tzp_get_date_format() . ' ' .  yourls_tzp_get_time_format();
}

/**
 * Filter for date (no time) format
 */
yourls_add_filter( 'get_date_format', 'yourls_tzp_get_date_format' );
function yourls_tzp_get_date_format() {
    $date_format = yourls_tzp_read_options('date_format', 'Y/m/d');
    if( $date_format == 'custom' ) {
        $date_format = yourls_tzp_read_options('date_format_custom', 'Y/m/d');
    }
    return $date_format;
}

/**
 * Filter for time (no date) format
 */
yourls_add_filter( 'get_time_format', 'yourls_tzp_get_time_format' );
function yourls_tzp_get_time_format() {
    $time_format = yourls_tzp_read_options('time_format', 'H:i');
    if( $time_format == 'custom' ) {
        $time_format = yourls_tzp_read_options('time_format_custom', 'H:i');
    }

    return $time_format;
}

/**
 * Return time offset of a timezone from UTC
 *
 * @param  string $timezone   Optional timezone (eg "Europe/Paris"). Default is UTC
 * @return int                Timezoned time offset
 */
function yourls_tzp_timezoned_offset($timezone = 'UTC') {
    $tz = new DateTime('now', new DateTimeZone($timezone));
    return $tz->getOffset()/3600;
}

/**
 * Return timezoned and formatted time
 *
 * @param  int    $timestamp  Optional timestamp. If omitted, function will use time()
 * @param  string $timezone   Optional timezone (eg "Europe/Paris"). Default is UTC
 * @param  string $format     Optional format as what PHP's date() needs. Default it 'U' (epoch)
 * @return string             Timezoned and formatted time
 */
function yourls_tzp_timezoned_timestamp($timestamp = false, $timezone = 'UTC') {
    $timestamp = $timestamp ? $timestamp : time();
    $offset    = yourls_tzp_timezoned_offset($timezone);
    return $timestamp + $offset * 3600;
}

/**
 * Get (string)key from array, or return false if not defined
 *
 * @param  array  $array Array
 * @param  string $key   Key
 * @return string        Value of (string)$array[$key], or false
 */
function yourls_tzp_get_value( $array, $key, $default ) {
    return isset ( $array[$key] ) ? (string)($array[$key]) : $default ;
}

/**
 * Read timezone options from the DB, and return all keys or specified key
 *
 * @param  string $key   Key of timezone option array
 * @return array|mixed   Array of options, or value for specified key if exists (false otherwise)
 */
function yourls_tzp_read_options( $key = false, $default = false ) {
    $options = (array)yourls_get_option( 'timezone' );

    if( $key !== false ) {
        $options = array_key_exists($key, $options) ? $options[$key] : $default ;
    }

    return $options;
}

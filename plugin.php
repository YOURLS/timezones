<?php
/*
Plugin Name: Time Zones ⏰
Plugin URI: https://github.com/YOURLS/timezones
Description: Tell YOURLS what timezone you are in
Version: 1.0
Author: YOURLS contributors
Author URI: https://yourls.org/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/* TODO
 *
 * - add some explanation that if people activate this plugin and have YOURLS_HOURS_OFFSET defined,
 *   YOURLS_HOURS_OFFSET will be ignored
 */

// Register our plugin admin page
yourls_add_action( 'plugins_loaded', 'yourls_time_zone_config' );
function yourls_time_zone_config() {
    yourls_register_plugin_page( 'time_zone_config', 'Time Zone Configuration', 'yourls_time_zone_config_do_page' );
    // parameters: page slug, page title, and function that will display the page itself
}

//
yourls_add_filter( 'get_timezoned_offset', 'yourls_time_zone_config_get_offset' );
function yourls_time_zone_config_get_offset() {

    $time_zone = yourls_get_option( 'timezone' );

    // If YOURLS_HOURS_OFFSET is not set and time_zone isn't set either
    if ( !is_string($time_zone) ) {
        return 0;
    }

    $datetimezone = new DateTimeZone($time_zone);

    // Compare current time zone time vs current GMT time to get the offset
    return $datetimezone->getOffset(new DateTime("now", new DateTimeZone("GMT"))) / 3600;

}

// Display time zone configuration page
function yourls_time_zone_config_do_page() {

    // Check if a form was submitted
    if( isset( $_POST['time_zone'] ) ) {
        // Check nonce and process form
        yourls_verify_nonce( 'time_zone_config' );
        yourls_time_zone_config_update_timezone();
    }

    // Get options
    $options = (array)yourls_get_option( 'timezone' );
    $user_time_zone          = yourls_time_zone_get_value($options,'time_zone');
    $user_date_format        = yourls_time_zone_get_value($options,'date_format');
    $user_date_format_custom = yourls_time_zone_get_value($options,'date_format_custom');
    $user_time_format        = yourls_time_zone_get_value($options,'time_format');
    $user_time_format_custom = yourls_time_zone_get_value($options,'time_format_custom');

    // Create nonce
    $nonce = yourls_create_nonce( 'time_zone_config' );


    // Continent list
    $continent = array(
        'Africa'     => DateTimeZone::AFRICA,
        'America'    => DateTimeZone::AMERICA,
        'Antarctica' => DateTimeZone::ANTARCTICA,
        'Asia'       => DateTimeZone::ASIA,
        'Atlantic'   => DateTimeZone::ATLANTIC,
        'Europe'     => DateTimeZone::EUROPE,
        'Indian'     => DateTimeZone::INDIAN,
        'Pacific'    => DateTimeZone::PACIFIC,
    );

    // Timezones per continents
    $timezones = array();
    foreach ($continent as $name => $mask) {
        $zones = DateTimeZone::listIdentifiers($mask);
        foreach($zones as $timezone) {
            // Remove region name and add a sample time
            $timezones[$name][$timezone] = substr($timezone, strlen($name) + 1);
            }
    }

    // Manual UTC offset
    $offset_range = array(
        -12,     -11.5,    -11,     -10.5,    -10,     -9.5,     -9,
        -8.5,    -8,       -7.5,    -7,       -6.5,    -6,       -5.5,
        -5,      -4.5,     -4,      -3.5,     -3,      -2.5,     -2,
        -1.5,    -1,       -0.5,    0,        0.5,     1,        1.5,
        2,       2.5,      3,       3.5,      4,       4.5,      5,
        5.5,     5.75,     6,       6.5,      7,       7.5,      8,
        8.5,     8.75,     9,       9.5,      10,      10.5,     11,
        11.5,    12,       12.75,   13,       13.75,   14
    );

    foreach( $offset_range as $offset ) {
        if ( 0 <= $offset ) {
            $offset_name = '+' . $offset;
        } else {
            $offset_name = (string) $offset;
        }

        $offset_value = $offset_name;
        $offset_name  = str_replace( array( '.25', '.5', '.75' ), array( ':15', ':30', ':45' ), $offset_name );
        $offset_name  = 'UTC' . $offset_name;
        $offset_value = 'UTC' . $offset_value;
        $timezones['UTC'][$offset_value] = $offset_name;
    }

    // View
    print '<h2>Time Zone Configuration</h2>';
    print '<p>This plugin enables the configuration of which time zone to use when displaying dates and time.</p>';
    print '<form method="post">';
    print '<input type="hidden" name="nonce" value="' . $nonce . '" />';

    print '<label for="time_zone">Time zone: </label><br>';
    print '<select name="time_zone" id="time_zone">';
    print '<option name="">Choose a time zone</option>';
    foreach($timezones as $region => $list) {
        print '<optgroup label="' . $region . '">' . "\n";
        foreach($list as $timezone => $name) {
            print '<option value="' . $timezone . '" ' . (($timezone == $user_time_zone) ? "selected='selected'":"") . '>' . $name . '</option>' . "\n";
        }
        print '<optgroup>' . "\n";
    }
    print '</select>';

    $choices = array(
        'j F Y',  // 13 April 2020
        'F j, Y', // May 10, 2020
        'd/m/Y',  // 20/10/2020
        'm/d/Y',  // 10/20/2020
        'Y/m/d',  // 2020/10/20
        );
    yourls_time_zone_format_radio( 'Date Format', 'date_format', $choices, $user_date_format, $user_date_format_custom );

    $choices = array(
        'H:i',    // 21:23
        'g:i a',  // 9:23 pm
        'g:i A',  // 9:23 PM
        );
    yourls_time_zone_format_radio( 'Time Format', 'time_format', $choices, $user_time_format, $user_time_format_custom );

    print '<p><input type="submit" value="Update configuration" /></p>';
    print '</form>';

    // auto select radio when custom input field is focused
    print <<<JS
    <script>
    $('.custom :input').focusin(function() {
        $(this).prev().click();
    });
    </script>
JS;

}

/**
 * Output radio button list
 *
 * @param  string $title       Dropdown title
 * @param  string $input_name  Dropdown 'radio' name
 * @param  array  $formats     List of available choices, to which 'custom' will be appended
 * @param  string $selected    Checked radio value
 * @param  string $custom      Custom format value
 */
function yourls_time_zone_format_radio( $title, $input_name, $formats, $selected, $custom ) {
    print "<h3>$title:</h3>";

    foreach ($formats as $format) {
        $checked = ( $format === $selected ) ? 'checked="checked"' : '' ;
        print "<p><label><input type='radio' name='$input_name' value='$format' $checked >";
        print date($format);
        print "</label></p>\n";
    }

    $checked = ( 'custom' === $selected ) ? 'checked="checked"' : '' ;
    print "<label class='custom'><input type='radio' id='${input_name}_custom' name='$input_name' value='custom' $checked >
           Custom: <input type='text' id='${input_name}_custom_value' name='${input_name}_custom_value' value='$custom' />
           </label>\n";

}

/**
 * Get (string)key from array, or return false if not defined
 *
 * @param  array  $array Array
 * @param  string $key   Key
 * @return string        Value of (string)$array[$key], or false
 */
function yourls_time_zone_get_value( $array, $key ) {
    return isset ( $array[$key] ) ? (string)($array[$key]) : false ;
}


// Update time zone in database
function yourls_time_zone_config_update_timezone() {
    yourls_update_option( 'timezone', array(
        'time_zone'          => yourls_time_zone_get_value($_POST, 'time_zone'),
        'date_format'        => yourls_time_zone_get_value($_POST, 'date_format'),
        'date_format_custom' => yourls_time_zone_get_value($_POST, 'date_format_custom_value'),
        'time_format'        => yourls_time_zone_get_value($_POST, 'time_format'),
        'time_format_custom' => yourls_time_zone_get_value($_POST, 'time_format_custom_value'),
    ));
}

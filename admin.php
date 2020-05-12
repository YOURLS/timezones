<?php
/**
 * Time Zone plugin admin page
 */

// Display time zone configuration page
function yourls_tzp_admin_page() {

    // Check if a form was submitted
    if( isset( $_POST['time_zone'] ) ) {
        // Check nonce and process form
        yourls_verify_nonce( 'time_zone_config' );
        $options = yourls_tzp_config_update_settings();
        echo yourls_notice_box('Timezone settings updated');
    } else {
        $options = (array)yourls_get_option( 'timezone' );
    }

    $user_time_zone          = yourls_tzp_get_value($options,'time_zone');
    $user_date_format        = yourls_tzp_get_value($options,'date_format');
    $user_date_format_custom = yourls_tzp_get_value($options,'date_format_custom');
    $user_time_format        = yourls_tzp_get_value($options,'time_format');
    $user_time_format_custom = yourls_tzp_get_value($options,'time_format_custom');

    // Draw page
    yourls_tzp_js_css();
    print '<h2>Time Zone Configuration</h2>
           <p>This plugin enables the configuration of which time zone to use when displaying dates and time.</p>
           <form method="post">';
    print '<input type="hidden" name="nonce" value="' . yourls_create_nonce( 'time_zone_config' ) . '" />';

    print '<h3>Time zone: </h3>
           <div class="settings">
           <p>Choose a city near your location, in the same timezone as you, or a UTC time offset.</p>';
    yourls_tzp_tz_dropdown( $user_time_zone );
    print '<p>Universal time (<code>UTC</code>) time is: <tt>' . yourls_tzp_timezoned_time( time(), 'UTC', 'Y-m-d H:i:s'  ) . '</tt></p>';
    if($user_time_zone) {
        print "<p>Time in $user_time_zone is: <tt>" . yourls_tzp_timezoned_time( time(), $user_time_zone, 'Y-m-d H:i:s'  ) . '</tt></p>';
    }
    print '</div>';

    // Display radio button for date format
    $choices = array(
        'j F Y',  // 13 April 2020
        'F j, Y', // May 10, 2020
        'd/m/Y',  // 20/10/2020
        'm/d/Y',  // 10/20/2020
        'Y/m/d',  // 2020/10/20
        );
    yourls_tzp_format_radio( 'Date Format', 'date_format', $choices, $user_time_zone, $user_date_format, $user_date_format_custom );

    // Display radio button for date format
    $choices = array(
        'H:i',    // 21:23
        'g:i a',  // 9:23 pm
        'g:i A',  // 9:23 PM
        );
    yourls_tzp_format_radio( 'Time Format', 'time_format', $choices, $user_time_zone, $user_time_format, $user_time_format_custom );

    print '<p><input type="submit" class="button" value="Update configuration" /></p>';
    print '</form>';

}


function yourls_tzp_js_css() {
    print <<<JSCSS
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
    jQuery( document ).ready(function() {
        // auto select radio when custom input field is focused
        $('.custom :input').focusin(function() {
            $(this).prev().click();
        });

        // easy selector on timezones
        $('#time_zone').select2({
            templateResult: format_region,
            placeholder:'Choose a time zone '
        }
        );
    })

    function format_region(item) {
        if (!item.id) {
            return item.text;
        }
        var text = item.text.split('/');
        var region=text[0];
        var city=text[1];
        return $('<span class="region">'+region+'</span> '+city+'</span>');
    }
    </script>
    <style>
    body {
        text-align:left;
    }
    h3 {
        border-bottom:1px solid #ccc;
    }
    div.settings {
        padding-bottom:2em;
    }
    .region{
        color:#aaa;
        font-style:italic;
    }
    </style>
JSCSS;

}


function yourls_tzp_tz_dropdown( $user_time_zone ) {
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

    print '<select name="time_zone" id="time_zone">';
    print '<option value="" dzisabled="dzisabled">Choose a time zone</option>';
    foreach($timezones as $region => $list) {
        print '<optgroup label="' . $region . '">' . "\n";
        foreach($list as $timezone => $name) {
            print '<option value="' . $timezone . '" ' . (($timezone == $user_time_zone) ? "selected='selected'":"") . '>' . "$region/$name" . '</option>' . "\n";
        }
        print '<optgroup>' . "\n";
    }
    print '</select>';
}


/**
 * Output radio button list
 *
 * @param  string $title       Dropdown title
 * @param  string $input_name  Dropdown 'radio' name
 * @param  array  $formats     List of available choices, to which 'custom' will be appended
 * @param  string $tz          Time zone
 * @param  string $selected    Checked radio value
 * @param  string $custom      Custom format value
 */
function yourls_tzp_format_radio( $title, $input_name, $formats, $tz, $selected, $custom ) {
    print "<h3>$title:</h3>
           <div class='settings'>";

    foreach ($formats as $format) {
        $checked = ( $format === $selected ) ? 'checked="checked"' : '' ;
        print "<p><label><input type='radio' name='$input_name' value='$format' $checked >";
        print yourls_date_i18n( $format, yourls_tzp_timezoned_time( time(), $tz ), true );
        print "<br>";
        print yourls_tzp_timezoned_time( time(), $tz, $format );
        print "</label></p>\n";
    }

    $checked = ( 'custom' === $selected ) ? 'checked="checked"' : '' ;
    print "<label class='custom'><input type='radio' id='${input_name}_custom' name='$input_name' value='custom' $checked >
           Custom: <input type='text' class='text' id='${input_name}_custom_value' name='${input_name}_custom_value' value='$custom' />
           </label>\n";

    print '</div>';

}

/**
 * Update time zone in database
 *
 * The array isn't sanitized here, it should be done in the caller
 *
 * @since
 */
function yourls_tzp_config_update_settings() {

    $settings = array(
        'time_zone'          => yourls_tzp_get_value($_POST, 'time_zone'),
        'date_format'        => yourls_tzp_get_value($_POST, 'date_format'),
        'date_format_custom' => yourls_tzp_get_value($_POST, 'date_format_custom_value'),
        'time_format'        => yourls_tzp_get_value($_POST, 'time_format'),
        'time_format_custom' => yourls_tzp_get_value($_POST, 'time_format_custom_value'),
    );

    yourls_update_option( 'timezone', $settings );

    return $settings;
}

<?php
/**
 * Time Zone plugin admin page
 */

/**
 * Display time zone configuration page
 */
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

    $user_time_zone          = yourls_tzp_get_value($options, 'time_zone', 'UTC');
    $user_time_zone_display  =  $user_time_zone;
    if (substr($user_time_zone, 0, 1) == '+' or substr($user_time_zone, 0, 1) == '-') {
        $user_time_zone_display = 'UTC' . $user_time_zone_display;
    }
    $user_date_format        = yourls_tzp_get_value($options, 'date_format', 'Y/m/d');
    $user_date_format_custom = yourls_tzp_get_value($options, 'date_format_custom', 'Y/m/d');
    $user_time_format        = yourls_tzp_get_value($options, 'time_format', 'H:i');
    $user_time_format_custom = yourls_tzp_get_value($options, 'time_format_custom', 'H:i');

    // Draw page
    yourls_tzp_js_css();
    print '<h2>Time Zone Configuration</h2>
           <p>This plugin allows to specify a time zone and to format time/date display</p>';

    if( defined('YOURLS_HOURS_OFFSET') ) {
        print '<p><strong>Note:</strong> you have <code>YOURLS_HOURS_OFFSET</code> defined in your config.php. This plugin will override this setting.</p>';
    }

    print '<form method="post">
           <input type="hidden" name="nonce" value="' . yourls_create_nonce( 'time_zone_config' ) . '" />';

    // Time zones drop down
    print '<h3>Time zone: </h3>
           <div class="settings">
           <p>Choose a city near your location, in the same timezone as you, or a UTC time offset.</p>';
    yourls_tzp_tz_dropdown( $user_time_zone );
    print '<p>Universal time (<code>UTC</code>) time is: <tt id="utc_time">' . date( 'Y-m-d H:i:s', yourls_tzp_timezoned_timestamp( time(), 'UTC' ) ). '</tt></p>';
    if($user_time_zone) {
        print "<p>Time in <code>$user_time_zone_display</code> is: <tt id='local_time'>" . date( 'Y-m-d H:i:s', yourls_tzp_timezoned_timestamp( time(), $user_time_zone) ) . '</tt></p>';
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

    print '<p><input type="submit" class="button primary" value="Update configuration" /></p>
           </form>
           <p><strong>Note:</strong> custom formats need a PHP <code><a href="https://php.net/date">date()</a></code> string.';

}

/**
 * Output CSS & JS
 */
function yourls_tzp_js_css() {
    $plugin_url = yourls_plugin_url( __DIR__ );
    print <<<JSCSS
    <link href='$plugin_url/assets/select2.min.css' rel='stylesheet' />
    <script src='$plugin_url/assets/select2.min.js'></script>
    <script src='$plugin_url/assets/php-date-formatter.min.js'></script>
    <script>
    jQuery( document ).ready(function() {
        // auto select radio when custom input field is focused
        $('.custom :input').focusin(function() {
            $(this).prev().click();
        });

        // easy selector on timezones
        $('#time_zone').select2({
            templateResult: format_region,
            placeholder:'Choose a time zone'
        });

        // Real time date format preview
        $(".custom_format").on("keyup", function() {
            format_preview($(this).attr('id'), $(this).val());
        });

        // Click a radio to change custom format accordingly
        $('.radio_format').change(
            function(){
                if (this.checked) {
                    name = $(this).attr('name');
                    value = $(this).attr('value');
                    $('#'+name+'_custom_value').val(value).trigger($.Event("keyup"));
                }
            }
        );
    })

    // Real time preview of date/time format
    function format_preview(id, value) {
        if($('#local_time')) {
            time = $('#local_time').text();
        } else {
            time = $('#utc_time').text();
        }
        id = id.replace('_custom_value', '');
        fmt = new DateFormatter();
        parse = fmt.parseDate(time, 'Y-m-d H:i:s');
        format = fmt.formatDate(parse, value );
        $('#tz_test_' + id).text(format);
    }

    // modify dropdown list
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
        padding-bottom:1em;
    }
    .region{
        color:#aaa;
        font-style:italic;
    }
    </style>
JSCSS;

}

/**
 * Draw the time zone drop down
 *
 * @param  string $user_time_zone   Timezone to be marked as "selected" in the dropdown
 */
function yourls_tzp_tz_dropdown( $user_time_zone ) {
    // Continent list
    $continent = array(
        'Africa'     => DateTimeZone::AFRICA,
        'America'    => DateTimeZone::AMERICA,
        'Antarctica' => DateTimeZone::ANTARCTICA,
        'Asia'       => DateTimeZone::ASIA,
        'Atlantic'   => DateTimeZone::ATLANTIC,
        'Australia'  => DateTimeZone::AUSTRALIA,
        'Europe'     => DateTimeZone::EUROPE,
        'Indian'     => DateTimeZone::INDIAN,
        'Pacific'    => DateTimeZone::PACIFIC,
    );

    // Timezones per continents
    $timezones = array();
    foreach ($continent as $name => $mask) {
        $zones = DateTimeZone::listIdentifiers($mask);
        foreach($zones as $timezone) {
            // Remove region name
            $timezones[$name][$timezone] = substr($timezone, strlen($name) +1);
        }
    }

    // Manual UTC offset
    $offset_range = array(
        '-12',     '-11:30',   '-11',     '-10:30',   '-10',     '-9.5',    '-9',
        '-8:30',   '-8',       '-7:30',   '-7',       '-6:30',   '-6',      '-5:30',
        '-5',      '-4:30',    '-4',      '-3:30',    '-3',      '-2:30',   '-2',
        '-1:30',   '-1',       '-0:30',   'UTC',        '+0:30',   '+1',      '+1:30',
        '+2',      '+2:30',    '+3',      '+3:30',    '+4',      '+4:30',   '+5',
        '+5:30',   '+5:45',    '+6',      '+6:30',    '+7',      '+7:30',   '+8',
        '+8:30',   '+8:45',    '+9',      '+9:30',    '+10',     '+10:30',  '+11',
        '+11:30',  '+12',      '+12:45',  '+13',      '+13:45',  '+14'
    );

    foreach( $offset_range as $offset_name ) {
        $offset_value = $offset_name;
        $offset_name  = str_replace( array( '.25', '.5', '.75' ), array( ':15', ':30', ':45' ), $offset_name );
        if ($offset_name != 'UTC') {
            $offset_name  = 'UTC' . $offset_name;
        }
        // $offset_value = 'UTC' . $offset_value;
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
        print "<p><label><input type='radio' class='radio_format radio_$input_name' name='$input_name' value='$format' $checked >";
        print yourls_date_i18n( $format, yourls_tzp_timezoned_timestamp( time(), $tz ), true );
        print "</label></p>\n";
    }

    $checked = ( 'custom' === $selected ) ? 'checked="checked"' : '' ;
    $preview = date( $custom, yourls_tzp_timezoned_timestamp( time(), $tz ) );
    print "<label class='custom'><input type='radio' id='${input_name}_custom' name='$input_name' value='custom' $checked >
           Custom: <input type='text' class='text custom_format' id='${input_name}_custom_value' name='${input_name}_custom_value' value='$custom' />
           <span class='tz_test' id='tz_test_$input_name'>$preview</span>
           </label>\n";

    print '</div>';

}

/**
 * Update time zone in database
 */
function yourls_tzp_config_update_settings() {

    $settings = array(
        'time_zone'          => yourls_tzp_get_value($_POST, 'time_zone', 'UTC'),
        'date_format'        => yourls_tzp_get_value($_POST, 'date_format', 'Y/m/d'),
        'date_format_custom' => yourls_tzp_get_value($_POST, 'date_format_custom_value', 'Y/m/d'),
        'time_format'        => yourls_tzp_get_value($_POST, 'time_format', 'H:i'),
        'time_format_custom' => yourls_tzp_get_value($_POST, 'time_format_custom_value', 'H:i'),
    );

    yourls_update_option( 'timezone', $settings );

    return $settings;
}

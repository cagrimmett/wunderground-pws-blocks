<?php
/**
 * Plugin Name: Wunderground PWS
 * Plugin URI: http://www.cagrimmett.com/
 * Description: Fetches weather data from Wunderground for a personal weather station and displays it in Gutenberg blocks.
 * Version: 0.0.1
 * Author: cagrimmett
 * Author URI: https://cagrimmett.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
*/

// plugin register function

// https://api.weather.com/v2/pws/observations/all/1day?stationId=KNYPEEKS11&format=json&units=e&apiKey=yourApiKey


function wu_pws_activate() {

	// Table for daily summary
	global $wpdb;
	$table_name      = $wpdb->prefix . 'wunderground_pws_daily';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        observation_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        tempHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        tempLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        tempAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        windspeedHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        windspeedLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        windspeedAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        windgustHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        windgustLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        windgustAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        dewptHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        dewptLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        dewptAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        windchillHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        windchillLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        windchillAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        heatindexHigh decimal(5,2) DEFAULT '0.00' NOT NULL,
        heatindexLow decimal(5,2) DEFAULT '0.00' NOT NULL,
        heatindexAvg decimal(5,2) DEFAULT '0.00' NOT NULL,
        pressureMax decimal(5,2) DEFAULT '0.00' NOT NULL,
        pressureMin decimal(5,2) DEFAULT '0.00' NOT NULL,
        pressureTrend decimal(5,2) DEFAULT '0.00' NOT NULL,
        precipRate decimal(5,2) DEFAULT '0.00' NOT NULL,
        precipTotal decimal(5,2) DEFAULT '0.00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Register plugin options
	register_setting(
		'wu_pws_settings',
		'wu_pws_station_id'
	);

	register_setting(
		'wu_pws_settings',
		'wu_pws_api_key'
	);

	// schedule cron hook
	if ( ! wp_next_scheduled( 'wu_pws_daily_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'wu_pws_daily_hook' );
	}
	if ( ! wp_next_scheduled( 'wu_pws_current_hook' ) ) {
		wp_schedule_event( time(), 'ten_minutes', 'wu_pws_current_hook' );
	}
}
register_activation_hook( __FILE__, 'wu_pws_activate' );

add_filter( 'cron_schedules', 'add_cron_interval' );
function add_cron_interval( $schedules ) {
	$schedules['ten_minutes'] = array(
		'interval' => 600,
		'display'  => esc_html__( 'Every Ten Minutes' ),
	);
	return $schedules;
}

function wu_pws_deactivate() {
	wp_clear_scheduled_hook( 'wu_pws_hook' );
}
register_deactivation_hook( __FILE__, 'wu_pws_deactivate' );

function wu_pws_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wunderground_pws_daily';
	$sql        = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query( $sql );

	delete_option( 'wu_pws_station_id' );
	delete_option( 'wu_pws_api_key' );
}
 register_uninstall_hook( __FILE__, 'wu_pws_uninstall' );


 // get current observations

function wu_pws_fetch_current_data() {
	$api_key    = get_option( 'wu_pws_api_key' );
	$station_id = get_option( 'wu_pws_station_id' );
	$url        = 'https://api.weather.com/v2/pws/observations/current?stationId=' . $station_id . '&format=json&units=e&apiKey=' . $api_key;
	$response   = wp_remote_get( $url );
	if ( is_wp_error( $response ) ) {
		error_log( 'Error fetching data from Wunderground API: ' . $response->get_error_message() );
		return;
	}
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( empty( $data ) || ! isset( $data['observations'] ) ) {
		return;
	}
	$observation = $data['observations'][0];
	$options     = array(
		'temp'         => $observation['imperial']['temp'],
		'heatIndex'    => $observation['imperial']['heatIndex'],
		'dewpt'        => $observation['imperial']['dewpt'],
		'windChill'    => $observation['imperial']['windChill'],
		'windSpeed'    => $observation['imperial']['windSpeed'],
		'windGust'     => $observation['imperial']['windGust'],
		'pressure'     => $observation['imperial']['pressure'],
		'precipRate'   => $observation['imperial']['precipRate'],
		'precipTotal'  => $observation['imperial']['precipTotal'],
		'humidity'     => $observation['humidity'],
		'uv'           => $observation['uv'],
		'obsTimeLocal' => $observation['obsTimeLocal'],
	);
	update_option( 'wunderground_pws_data', $options );
	error_log( 'Wunderground PWS current data updated successfully' );
}

// hook the current data function onto our scheduled event
add_action( 'wu_pws_current_hook', 'wu_pws_fetch_current_data' );


// get daily summary

function wu_pws_fetch_daily_summary() {
	// Get the API key and station ID from the plugin settings
	$api_key    = get_option( 'wu_pws_api_key' );
	$station_id = get_option( 'wu_pws_station_id' );

	$yesterday = date( 'Ymd', strtotime( '-1 day' ) );

	// Build the API URL
	$api_url = 'https://api.weather.com//v2/pws/history/daily?stationId=' . $station_id . '&format=json&units=e&apiKey=' . $api_key . '&date=' . $yesterday;

	// Fetch the data from the API
	$response = wp_remote_get( $api_url );

	// If there was an error fetching the data, log an error and return
	if ( is_wp_error( $response ) ) {
		error_log( 'Error fetching data from Wunderground API: ' . $response->get_error_message() );
		return;
	}

	// If the response code is not 200 OK, log an error and return
	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		error_log( 'Error fetching data from Wunderground API: Response code ' . $response_code );
		return;
	}

	// Parse the response body as JSON
	$response_body = wp_remote_retrieve_body( $response );
	$data          = json_decode( $response_body, true );

	// Extract the relevant data from the response
	$observations = $data['observations'];
	if ( count( $observations ) < 1 ) {
		error_log( 'No observations found in API response' );
		return;
	}
	$tempHigh         = $observations[0]['imperial']['tempHigh'];
	$tempLow          = $observations[0]['imperial']['tempLow'];
	$tempAvg          = $observations[0]['imperial']['tempAvg'];
	$windspeedHigh    = $observations[0]['imperial']['windspeedHigh'];
	$windspeedLow     = $observations[0]['imperial']['windspeedLow'];
	$windspeedAvg     = $observations[0]['imperial']['windspeedAvg'];
	$windgustHigh     = $observations[0]['imperial']['windgustHigh'];
	$windgustLow      = $observations[0]['imperial']['windgustLow'];
	$windgustAvg      = $observations[0]['imperial']['windgustAvg'];
	$dewptHigh        = $observations[0]['imperial']['dewptHigh'];
	$dewptLow         = $observations[0]['imperial']['dewptLow'];
	$dewptAvg         = $observations[0]['imperial']['dewptAvg'];
	$windchillHigh    = $observations[0]['imperial']['windchillHigh'];
	$windchillLow     = $observations[0]['imperial']['windchillLow'];
	$windchillAvg     = $observations[0]['imperial']['windchillAvg'];
	$heatindexHigh    = $observations[0]['imperial']['heatindexHigh'];
	$heatindexLow     = $observations[0]['imperial']['heatindexLow'];
	$heatindexAvg     = $observations[0]['imperial']['heatindexAvg'];
	$pressureMax      = $observations[0]['imperial']['pressureMax'];
	$pressureMin      = $observations[0]['imperial']['pressureMin'];
	$pressureTrend    = $observations[0]['imperial']['pressureTrend'];
	$precipRate       = $observations[0]['imperial']['precipRate'];
	$precipTotal      = $observations[0]['imperial']['precipTotal'];
	$observation_time = $observations[0]['obsTimeLocal'];

	// Insert the data into the database
	global $wpdb;
	$table_name = $wpdb->prefix . 'wunderground_pws_daily';

	// Check if the observation already exists in the database
	$observation_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE observation_time = %s", $observation_time ) );
	if ( $observation_exists ) {
		error_log( 'Wunderground PWS: Daily observation already in the database, not saving this one' );
		return;
	}
	$wpdb->insert(
		$table_name,
		array(
			'tempHigh'         => $tempHigh,
			'tempLow'          => $tempLow,
			'tempAvg'          => $tempAvg,
			'windspeedHigh'    => $windspeedHigh,
			'windspeedLow'     => $windspeedLow,
			'windspeedAvg'     => $windspeedAvg,
			'windgustHigh'     => $windgustHigh,
			'windgustLow'      => $windgustLow,
			'windgustAvg'      => $windgustAvg,
			'dewptHigh'        => $dewptHigh,
			'dewptLow'         => $dewptLow,
			'dewptAvg'         => $dewptAvg,
			'windchillHigh'    => $windchillHigh,
			'windchillLow'     => $windchillLow,
			'windchillAvg'     => $windchillAvg,
			'heatindexHigh'    => $heatindexHigh,
			'heatindexLow'     => $heatindexLow,
			'heatindexAvg'     => $heatindexAvg,
			'pressureMax'      => $pressureMax,
			'pressureMin'      => $pressureMin,
			'pressureTrend'    => $pressureTrend,
			'precipRate'       => $precipRate,
			'precipTotal'      => $precipTotal,
			'observation_time' => $observation_time,
		)
	);

	// Check if the query failed
	if ( ! empty( $wpdb->last_error ) ) {
		error_log( 'Error inserting data into database: ' . $wpdb->last_error );
	} else {
		// Log a success message
		error_log( 'Wunderground PWS daily data updated successfully' );
	}
}
// hook the current data function onto our scheduled event
add_action( 'wu_pws_daily_hook', 'wu_pws_fetch_daily_summary' );


// wp-admin page rendering
function wu_pws_options_page() {
	add_submenu_page(
		'tools.php', // Parent page slug
		'Wunderground PWS', // Page title
		'Wunderground PWS', // Menu title
		'manage_options', // Capability required to access the page
		'wu-pws-settings', // Menu slug
		'wu_pws_settings_page' // Callback function to render the page
	);
}
add_action( 'admin_menu', 'wu_pws_options_page' );

// Callback function to render the plugin settings page
function wu_pws_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

			// Check if form has been submitted
	if ( isset( $_POST['wu_pws_settings_form_submitted'] ) ) {
		// Validate and sanitize form input
		$api_key    = sanitize_text_field( $_POST['wu_pws_api_key'] );
		$station_id = sanitize_text_field( $_POST['wu_pws_station_id'] );

		// Save form input to plugin options
		update_option( 'wu_pws_api_key', $api_key );
		update_option( 'wu_pws_station_id', $station_id );

		// Display success message
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>Settings saved successfully.</p>';
		echo '</div>';
	}

			// Render form
	?>

		<div class="wrap">
			<h1>Wunderground PWS Settings</h1>
			<form method="post" action="">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wu_pws_username">Wunderground API key, <a href="https://www.wunderground.com/member/api-keys" target=_blank">available here</a></label>
						</th>
						<td>
							<input type="text" name="wu_pws_api_key" id="wu_pws_api_key" value="<?php echo esc_attr( get_option( 'wu_pws_api_key' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wu_pws_station_id">Wunderground PWS Station ID to pull data from</label>
						</th>
						<td>
						<input type="text" name="wu_pws_station_id" id="wu_pws_station_id" value="<?php echo esc_attr( get_option( 'wu_pws_station_id' ) ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>
				<input type="hidden" name="wu_pws_settings_form_submitted" value="1" />
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
}

function wu_pws_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'tools.php?page=wu-pws-settings' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wu_pws_settings_link' );


// block assets

add_action( 'init', 'register_wunderground_pws_current_weather_block' );
function register_wunderground_pws_current_weather_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script(
		'wu-pws-blocks',
		plugins_url( 'blocks/current-weather.js', __FILE__ ),
		array(
			'wp-blocks',
			'wp-components',
		)
	);

	register_block_type(
		'wu-pws-blocks/current-weather',
		array(
			'editor_script'   => 'wu-pws-blocks',
			'style'           => 'wu-pws-blocks',
			'render_callback' => 'current_weather_block_render',
		),
	);
}

function current_weather_block_render() {
		$options = get_option( 'wunderground_pws_data' );

	if ( ! $options ) {
		return '<div>Weather data not available.</div>';
	}

		$temp         = isset( $options['temp'] ) ? $options['temp'] : '';
		$heatIndex    = isset( $options['heatIndex'] ) ? $options['heatIndex'] : '';
		$dewpt        = isset( $options['dewpt'] ) ? $options['dewpt'] : '';
		$windChill    = isset( $options['windChill'] ) ? $options['windChill'] : '';
		$windSpeed    = isset( $options['windSpeed'] ) ? $options['windSpeed'] : '';
		$windGust     = isset( $options['windGust'] ) ? $options['windGust'] : '';
		$pressure     = isset( $options['pressure'] ) ? $options['pressure'] : '';
		$precipRate   = isset( $options['precipRate'] ) ? $options['precipRate'] : '';
		$precipTotal  = isset( $options['precipTotal'] ) ? $options['precipTotal'] : '';
		$obsTimeLocal = isset( $options['obsTimeLocal'] ) ? $options['obsTimeLocal'] : '';
		$humidity     = isset( $options['humidity'] ) ? $options['humidity'] : '';
		$uv           = isset( $options['uv'] ) ? $options['uv'] : '';
		$station_id   = get_option( 'wu_pws_station_id' );

		$output  = '<div>';
		$output .= "<h4>Current weather conditions from <a href='https://www.wunderground.com/dashboard/pws/$station_id' target='_blank'>$station_id</a></h4>";
		$output .= "<p><em>Last updated: $obsTimeLocal</em></p>";
		$output .= "<p>Temperature: $temp &deg;F</p>";
		$output .= "<p>Humidity: $humidity %</p>";
		$output .= "<p>UV Index: $uv</p>";
		$output .= "<p>Heat Index: $heatIndex &deg;F</p>";
		$output .= "<p>Dew Point: $dewpt &deg;F</p>";
		$output .= "<p>Precipitation Rate: $precipRate in/hr</p>";
		$output .= "<p>Precipitation Total: $precipTotal in</p>";
		$output .= "<p>Wind Chill: $windChill &deg;F</p>";
		$output .= "<p>Wind Speed: $windSpeed mph</p>";
		$output .= "<p>Wind Gust: $windGust mph</p>";
		$output .= "<p>Pressure: $pressure inHg</p>";
		$output .= '</div>';

		return $output;

}

// make options available via REST API
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wu-pws/v1',
			'/current',
			array(
				'methods'  => 'GET',
				'callback' => 'get_current_conditions',
				// 'permission_callback' => function () {
				// 	return current_user_can( 'administrator' );
				// },
			)
		);
	}
);
function get_current_conditions( $data ) {
	return get_option( 'wunderground_pws_data' );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wu-pws/v1',
			'/station_id',
			array(
				'methods'  => 'GET',
				'callback' => 'get_station_id',
				// 'permission_callback' => function () {
				// 	return current_user_can( 'administrator' );
				// },
			)
		);
	}
);
function get_station_id( $data ) {
	return get_option( 'wu_pws_station_id' );
}

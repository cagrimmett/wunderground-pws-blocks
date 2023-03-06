<?php

function wu_pws_render_weather_block( $attributes ) {
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

	$output  = '<div>';
	$output .= "<p>Temperature: $temp &deg;F</p>";
	$output .= "<p>Heat Index: $heatIndex &deg;F</p>";
	$output .= "<p>Dew Point: $dewpt &deg;F</p>";
	$output .= "<p>Wind Chill: $windChill &deg;F</p>";
	$output .= "<p>Wind Speed: $windSpeed mph</p>";
	$output .= "<p>Wind Gust: $windGust mph</p>";
	$output .= "<p>Pressure: $pressure inHg</p>";
	$output .= "<p>Precipitation Rate: $precipRate in/hr</p>";
	$output .= "<p>Precipitation Total: $precipTotal in</p>";
	$output .= "<p>Observation Time: $obsTimeLocal</p>";
	$output .= '</div>';

	return $output;
}

<?php
/**
 * Geocoding utilities and backfill
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wom_geocode_backfill_event', 'wom_geocode_backfill_batch' );

/**
 * Backfill a small batch of orders lacking coordinates
 */
function wom_geocode_backfill_batch() {
	$orders = wc_get_orders( array(
		'limit'  => 50,
		'orderby'=> 'date',
		'order'  => 'DESC',
		'return' => 'ids',
	) );
	foreach ( $orders as $order_id ) {
		if ( get_post_meta( $order_id, '_wom_lat', true ) !== '' ) { continue; }
		$order = wc_get_order( $order_id );
		if ( ! $order ) { continue; }
		$addr = wc_format_address( $order->get_address( 'shipping' ) );
		if ( empty( $addr ) ) { continue; }
		$coords = wom_geocode_address( $addr );
		if ( $coords ) {
			update_post_meta( $order_id, '_wom_lat', $coords['lat'] );
			update_post_meta( $order_id, '_wom_lng', $coords['lng'] );
		}
	}
}

/**
 * Geocode a single address using selected provider
 */
function wom_geocode_address( $address ) {
	$opts = wom_get_settings();
	$provider = $opts['geocoding_provider'];
	$api_key  = $opts['geocoding_api_key'];

	if ( 'google' === $provider && $api_key ) {
		$url = add_query_arg( array(
			'address' => rawurlencode( $address ),
			'key'     => $api_key,
		), 'https://maps.googleapis.com/maps/api/geocode/json' );
		$response = wp_remote_get( $url, array( 'timeout' => 12 ) );
		if ( is_wp_error( $response ) ) { return null; }
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['results'][0]['geometry']['location'] ) ) {
			$loc = $body['results'][0]['geometry']['location'];
			return array( 'lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'] );
		}
		return null;
	}

	// Default: Nominatim (OSM) without key (observe usage policy)
	$url = add_query_arg( array(
		'q'      => rawurlencode( $address ),
		'format' => 'json',
		'limit'  => 1,
	), 'https://nominatim.openstreetmap.org/search' );
	$response = wp_remote_get( $url, array( 'timeout' => 12, 'headers' => array( 'User-Agent' => 'WOM-Plugin/0.1' ) ) );
	if ( is_wp_error( $response ) ) { return null; }
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! empty( $body[0]['lat'] ) && ! empty( $body[0]['lon'] ) ) {
		return array( 'lat' => (float) $body[0]['lat'], 'lng' => (float) $body[0]['lon'] );
	}
	return null;
}


<?php
/**
 * Admin Dashboard Map Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'wom_orders_map_widget', __( 'Orders Map', 'woocommerce-orders-map' ), 'wom_render_orders_map_widget' );
} );

function wom_render_orders_map_widget() {
	$map_id = 'wom-admin-map';
	$handle = 'wom-admin-map';
	$ver    = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : WOM_PLUGIN_VERSION;

	$opts = function_exists( 'wom_get_settings' ) ? wom_get_settings() : array( 'map_provider' => 'osm', 'maps_api_key' => '' );
	$provider = isset( $opts['map_provider'] ) ? $opts['map_provider'] : 'osm';
	$maps_api_key = isset( $opts['maps_api_key'] ) ? $opts['maps_api_key'] : '';

	if ( 'google' === $provider ) {
		// Google Maps JS API
		$gmaps_url = add_query_arg( array(
			'key'      => $maps_api_key,
			'v'        => 'quarterly',
			'libraries'=> 'marker',
		), 'https://maps.googleapis.com/maps/api/js' );
		wp_enqueue_script( 'google-maps', $gmaps_url, array(), null, true );
		wp_register_script( $handle . '-google', WOM_PLUGIN_URL . 'assets/admin-map-google.js', array( 'google-maps' ), $ver, true );
		$settings = array(
			'root'     => esc_url_raw( rest_url( 'wom/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'provider' => 'google',
		);
		wp_localize_script( $handle . '-google', 'WOM_AdminMap', $settings );
		wp_enqueue_script( $handle . '-google' );
	} else {
		// Leaflet CSS/JS (CDN)
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
		wp_register_script( $handle, WOM_PLUGIN_URL . 'assets/admin-map.js', array( 'leaflet' ), $ver, true );
		$settings = array(
			'root'     => esc_url_raw( rest_url( 'wom/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'provider' => 'osm',
		);
		wp_localize_script( $handle, 'WOM_AdminMap', $settings );
		wp_enqueue_script( $handle );
	}

	// Container
	echo '<div id="' . esc_attr( $map_id ) . '" style="height:240px"></div>';
}

// Simple REST endpoint to fetch recent orders with basic location info
add_action( 'rest_api_init', function () {
    register_rest_route( 'wom/v1', '/admin/orders-for-map', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => function () { return current_user_can( 'wom_manage_assignments' ); },
		'callback'            => function ( WP_REST_Request $request ) {
            $bounds = $request->get_param( 'bounds' ); // {south,west,north,east}
            $limit  = min( 200, max( 1, absint( $request->get_param( 'limit' ) ) ) );

            $cache_key = 'wom_map_feed_' . md5( wp_json_encode( array( 'b' => $bounds, 'l' => $limit ) ) );
            $cached    = get_transient( $cache_key );
            if ( $cached ) { return new WP_REST_Response( $cached, 200 ); }

            $orders = wc_get_orders( array(
                'limit'   => $limit,
                'orderby' => 'date',
                'order'   => 'DESC',
                'return'  => 'ids',
            ) );
			$data = array();
			foreach ( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) { continue; }
				$lat = get_post_meta( $order_id, '_wom_lat', true );
				$lng = get_post_meta( $order_id, '_wom_lng', true );
				// Only include orders with coords (assumes geocoding done elsewhere)
				if ( '' === $lat || '' === $lng ) { continue; }
				if ( is_array( $bounds ) && count( $bounds ) === 4 ) {
					$south = (float) $bounds['south'];
					$west  = (float) $bounds['west'];
					$north = (float) $bounds['north'];
					$east  = (float) $bounds['east'];
					if ( $lat < $south || $lat > $north || $lng < $west || $lng > $east ) { continue; }
				}
				$data[] = array(
					'id'      => $order_id,
					'number'  => $order->get_order_number(),
					'lat'     => (float) $lat,
					'lng'     => (float) $lng,
					'address' => wc_format_address( $order->get_address( 'shipping' ) ),
					'status'  => $order->get_status(),
					'assignedDriver' => (int) get_post_meta( $order_id, WOM_META_ASSIGNED_DRIVER, true ),
				);
			}
            set_transient( $cache_key, $data, MINUTE_IN_SECONDS );
            return new WP_REST_Response( $data, 200 );
		},
	) );

	// Assign/Unassign bulk
	register_rest_route( 'wom/v1', '/admin/assign', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => function () { return current_user_can( 'wom_manage_assignments' ); },
		'callback'            => function ( WP_REST_Request $request ) {
			$order_ids = array_filter( array_map( 'absint', (array) $request->get_param( 'order_ids' ) ) );
			$driver_id = absint( $request->get_param( 'driver_id' ) );
			if ( empty( $order_ids ) || $driver_id <= 0 ) {
				return new WP_Error( 'wom_invalid_params', __( 'Invalid parameters', 'woocommerce-orders-map' ), array( 'status' => 400 ) );
			}
			$user = get_user_by( 'id', $driver_id );
			if ( ! $user ) {
				return new WP_Error( 'wom_invalid_driver', __( 'Driver not found', 'woocommerce-orders-map' ), array( 'status' => 404 ) );
			}
            foreach ( $order_ids as $oid ) {
				update_post_meta( $oid, WOM_META_ASSIGNED_DRIVER, (string) $driver_id );
				if ( ! get_post_meta( $oid, '_wom_assigned_at', true ) ) {
					update_post_meta( $oid, '_wom_assigned_at', time() );
				}
			}
            // Bust map cache
            global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wom_map_feed_%' OR option_name LIKE '_transient_timeout_wom_map_feed_%'" );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );

	register_rest_route( 'wom/v1', '/admin/unassign', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => function () { return current_user_can( 'wom_manage_assignments' ); },
		'callback'            => function ( WP_REST_Request $request ) {
			$order_ids = array_filter( array_map( 'absint', (array) $request->get_param( 'order_ids' ) ) );
			if ( empty( $order_ids ) ) {
				return new WP_Error( 'wom_invalid_params', __( 'Invalid parameters', 'woocommerce-orders-map' ), array( 'status' => 400 ) );
			}
            foreach ( $order_ids as $oid ) {
				delete_post_meta( $oid, WOM_META_ASSIGNED_DRIVER );
			}
            global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wom_map_feed_%' OR option_name LIKE '_transient_timeout_wom_map_feed_%'" );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );
} );

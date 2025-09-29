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

	// Capability check: show a friendly message instead of an empty widget
	if ( ! current_user_can( 'wom_manage_assignments' ) ) {
		echo '<div class="notice notice-warning" style="margin:0"><p>' . esc_html__( 'You do not have permission to view the Orders Map.', 'woocommerce-orders-map' ) . '</p></div>';
		return;
	}

	// Provider fallback: if Google is selected but no key, fall back to OSM
	if ( 'google' === $provider && empty( $maps_api_key ) ) {
		$provider = 'osm';
	}

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

// Also expose the map via a WooCommerce submenu page for easier discovery
add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Orders Map', 'woocommerce-orders-map' ),
		__( 'Orders Map', 'woocommerce-orders-map' ),
		'wom_manage_assignments',
		'wom-orders-map',
		'wom_render_orders_map_admin_page'
	);
} );

function wom_render_orders_map_admin_page() {
	if ( ! current_user_can( 'wom_manage_assignments' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'woocommerce-orders-map' ) );
	}
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Orders Map', 'woocommerce-orders-map' ) . '</h1>';
	// Reuse the same renderer used by the dashboard widget
	wom_render_orders_map_widget();
	echo '</div>';
}

// Simple REST endpoint to fetch recent orders with basic location info
add_action( 'rest_api_init', function () {
    register_rest_route( 'wom/v1', '/admin/orders-for-map', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => function () { return current_user_can( 'wom_manage_assignments' ); },
		'callback'            => function ( WP_REST_Request $request ) {
			$bounds = $request->get_param( 'bounds' ); // {south,west,north,east}
			$limit  = min( 200, max( 1, absint( $request->get_param( 'limit' ) ) ) );
			$live   = (bool) $request->get_param( 'live' );

			$cache_key = 'wom_map_feed_' . md5( wp_json_encode( array( 'b' => $bounds, 'l' => $limit ) ) );
			$cached    = $live ? false : get_transient( $cache_key );
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
					// Live positions (if available)
					'driverLat' => (float) get_post_meta( $order_id, WOM_META_DRIVER_LAT, true ),
					'driverLng' => (float) get_post_meta( $order_id, WOM_META_DRIVER_LNG, true ),
					'driverLocAt' => (int) get_post_meta( $order_id, WOM_META_DRIVER_LOC_AT, true ),
					'customerLat' => (float) get_post_meta( $order_id, WOM_META_CUSTOMER_LAT, true ),
					'customerLng' => (float) get_post_meta( $order_id, WOM_META_CUSTOMER_LNG, true ),
					'customerLocAt' => (int) get_post_meta( $order_id, WOM_META_CUSTOMER_LOC_AT, true ),
				);
			}
			if ( ! $live ) {
				set_transient( $cache_key, $data, 10 );
			}
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

	// Store location endpoint for admin map
	register_rest_route( 'wom/v1', '/admin/store-location', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => function () { return current_user_can( 'wom_manage_assignments' ); },
		'callback'            => function () {
			$opts = function_exists( 'wom_get_settings' ) ? wom_get_settings() : array( 'show_store_marker' => 1 );
			if ( empty( $opts['show_store_marker'] ) ) { return new WP_REST_Response( array(), 200 ); }
			$base_country = get_option( 'woocommerce_default_country' ); // e.g. GB:London
			$store_addr1  = get_option( 'woocommerce_store_address' );
			$store_addr2  = get_option( 'woocommerce_store_address_2' );
			$store_city   = get_option( 'woocommerce_store_city' );
			$store_post   = get_option( 'woocommerce_store_postcode' );
			$country      = '';
			$state        = '';
			if ( strpos( (string) $base_country, ':' ) !== false ) {
				list( $country, $state ) = array_pad( explode( ':', (string) $base_country ), 2, '' );
			} else {
				$country = (string) $base_country;
			}
			$parts = array_filter( array( (string) $store_addr1, (string) $store_addr2, (string) $store_city, (string) $state, (string) $store_post, (string) $country ) );
			$address = implode( ', ', $parts );
			$coords  = function_exists( 'wom_geocode_address' ) && ! empty( $address ) ? wom_geocode_address( $address ) : null;
			if ( $coords ) {
				return new WP_REST_Response( array( 'lat' => (float) $coords['lat'], 'lng' => (float) $coords['lng'], 'address' => $address ), 200 );
			}
			return new WP_REST_Response( array(), 200 );
		},
	) );
} );

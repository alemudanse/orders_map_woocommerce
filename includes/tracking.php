<?php
/**
 * Customer tracking and driver location request
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Driver triggers: request customer location
add_action( 'rest_api_init', function () {
	register_rest_route( 'wom/v1', '/driver/orders/(?P<order_id>\d+)/request-location', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => 'wom_rest_require_logged_in_driver_for_order',
		'callback'            => function ( WP_REST_Request $request ) {
			$order_id = absint( $request->get_param( 'order_id' ) );
			$method   = sanitize_text_field( (string) $request->get_param( 'method' ) );
			$allowed  = array( 'email', 'sms' );
			if ( ! in_array( $method, $allowed, true ) ) {
				return new WP_Error( 'wom_bad_method', __( 'Invalid method', 'woocommerce-orders-map' ), array( 'status' => 400 ) );
			}
			$token   = wp_generate_password( 32, false, false );
			$expires = time() + 2 * HOUR_IN_SECONDS;
			update_post_meta( $order_id, WOM_META_TRACK_TOKEN, $token );
			update_post_meta( $order_id, WOM_META_TRACK_EXPIRES, $expires );
			$track_url = add_query_arg( array( 'rest_route' => '/wom/v1/track', 'token' => rawurlencode( $token ) ), site_url( '/' ) );
			do_action( 'wom_send_location_request', $order_id, $method, $track_url );
			wc_create_order_note( $order_id, __( 'Requested customer location.', 'woocommerce-orders-map' ) );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
		'args'                => array(
			'method' => array( 'required' => true, 'type' => 'string' ),
		),
	) );

	// Public: get tracking info (limited) by token
	register_rest_route( 'wom/v1', '/track', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
			$order_num = sanitize_text_field( (string) $request->get_param( 'order' ) );
			$email = sanitize_email( (string) $request->get_param( 'email' ) );
			if ( empty( $token ) && ( empty( $order_num ) || empty( $email ) ) ) { return new WP_Error( 'wom_bad_token', __( 'Missing token or order/email', 'woocommerce-orders-map' ), array( 'status' => 400 ) ); }
			$order_id = 0;
			if ( $token ) { $order_id = wom_find_order_by_meta( WOM_META_TRACK_TOKEN, $token ); }
			if ( ! $order_id && $order_num && $email ) {
				$order = wc_get_order( $order_num );
				if ( $order && strtolower( $order->get_billing_email() ) === strtolower( $email ) ) {
					$order_id = $order->get_id();
				}
			}
			if ( ! $order_id ) { return new WP_Error( 'wom_not_found', __( 'Invalid or expired token', 'woocommerce-orders-map' ), array( 'status' => 404 ) ); }
			$expires = (int) get_post_meta( $order_id, WOM_META_TRACK_EXPIRES, true );
			if ( $expires && time() > $expires ) { return new WP_Error( 'wom_expired', __( 'Token expired', 'woocommerce-orders-map' ), array( 'status' => 410 ) ); }
			return new WP_REST_Response( array(
				'orderId' => $order_id,
				'customerLat' => (float) get_post_meta( $order_id, WOM_META_CUSTOMER_LAT, true ),
				'customerLng' => (float) get_post_meta( $order_id, WOM_META_CUSTOMER_LNG, true ),
				'customerLocAt' => (int) get_post_meta( $order_id, WOM_META_CUSTOMER_LOC_AT, true ),
				'driverLat' => (float) get_post_meta( $order_id, WOM_META_DRIVER_LAT, true ),
				'driverLng' => (float) get_post_meta( $order_id, WOM_META_DRIVER_LNG, true ),
				'driverLocAt' => (int) get_post_meta( $order_id, WOM_META_DRIVER_LOC_AT, true ),
				'orderStatus' => get_post_status( $order_id ),
			), 200 );
		},
	) );

	// Public: share location (from customer device)
	register_rest_route( 'wom/v1', '/track/share', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
			$lat   = (float) $request->get_param( 'lat' );
			$lng   = (float) $request->get_param( 'lng' );
			if ( empty( $token ) ) { return new WP_Error( 'wom_bad_token', __( 'Missing token', 'woocommerce-orders-map' ), array( 'status' => 400 ) ); }
			$order_id = wom_find_order_by_meta( WOM_META_TRACK_TOKEN, $token );
			if ( ! $order_id ) { return new WP_Error( 'wom_not_found', __( 'Invalid or expired token', 'woocommerce-orders-map' ), array( 'status' => 404 ) ); }
			$expires = (int) get_post_meta( $order_id, WOM_META_TRACK_EXPIRES, true );
			if ( $expires && time() > $expires ) { return new WP_Error( 'wom_expired', __( 'Token expired', 'woocommerce-orders-map' ), array( 'status' => 410 ) ); }
			update_post_meta( $order_id, WOM_META_CUSTOMER_LAT, $lat );
			update_post_meta( $order_id, WOM_META_CUSTOMER_LNG, $lng );
			update_post_meta( $order_id, WOM_META_CUSTOMER_LOC_AT, time() );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
		'args'                => array(
			'token' => array( 'required' => true, 'type' => 'string' ),
			'lat'   => array( 'required' => true, 'type' => 'number' ),
			'lng'   => array( 'required' => true, 'type' => 'number' ),
		),
	) );

	// Driver shares live location for an order
	register_rest_route( 'wom/v1', '/driver/orders/(?P<order_id>\d+)/location', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => 'wom_rest_require_logged_in_driver_for_order',
		'callback'            => function ( WP_REST_Request $request ) {
			$order_id = absint( $request->get_param( 'order_id' ) );
			$lat = (float) $request->get_param( 'lat' );
			$lng = (float) $request->get_param( 'lng' );
			update_post_meta( $order_id, WOM_META_DRIVER_LAT, $lat );
			update_post_meta( $order_id, WOM_META_DRIVER_LNG, $lng );
			update_post_meta( $order_id, WOM_META_DRIVER_LOC_AT, time() );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
		'args'                => array(
			'lat' => array( 'required' => true, 'type' => 'number' ),
			'lng' => array( 'required' => true, 'type' => 'number' ),
		),
	) );
} );

function wom_find_order_by_meta( $key, $value ) {
	$q = new WP_Query( array(
		'post_type'      => 'shop_order',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => $key, 'value' => $value ) ),
	) );
	return empty( $q->posts ) ? 0 : (int) $q->posts[0];
}

// Customer tracking page (shortcode)
add_action( 'init', function () {
    add_shortcode( 'wom_customer_tracking', function () {
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        ob_start();
		echo '<div id="wom-customer-track"></div>';
		$root_js  = wp_json_encode( esc_url_raw( rest_url( 'wom/v1' ) ) );
		$token_js = wp_json_encode( $token );
		echo <<<JS
<script>(function(){
    var root={$root_js};
    var token={$token_js};
    var el=document.getElementById("wom-customer-track");
    el.innerHTML = "<div><label>Order # <input id=\"wom-order\"/></label> <label>Email <input id=\"wom-email\" type=\"email\"/></label> <button id=\"wom-track\">Track</button></div><div id=\"wom-map\" style=\"height:320px;margin-top:8px\"></div>";
    function get(u){return fetch(u).then(function(r){return r.json();});}
    function g(cb){if(!navigator.geolocation){cb(null);return;}navigator.geolocation.getCurrentPosition(function(p){cb({lat:p.coords.latitude,lng:p.coords.longitude});},function(){cb(null);});}
    function post(u,d){return fetch(u,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();});}
    function renderTrack(q){
        var url = root + "/track" + q;
        get(url).then(function(data){
            if(data && data.orderId){
                if(typeof L !== 'undefined'){
                    var mapEl=document.getElementById("wom-map");
                    var map = L.map(mapEl).setView([51.505,-0.09], 12);
                    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
                    var markers=[];
                    function upd(){
                        get(url).then(function(d){
                            markers.forEach(function(m){map.removeLayer(m);}); markers=[];
                            if(d.customerLat && d.customerLng){ markers.push(L.marker([d.customerLat,d.customerLng]).addTo(map).bindPopup("Customer")); }
                            if(d.driverLat && d.driverLng){ markers.push(L.marker([d.driverLat,d.driverLng]).addTo(map).bindPopup("Driver")); }
                            var b=[]; markers.forEach(function(m){ b.push(m.getLatLng()); }); if(b.length){ map.fitBounds(b,{padding:[20,20]}); }
                        });
                    }
                    upd(); setInterval(upd, 20000);
                }
            } else { alert("Not found. Check details."); }
        });
    }
    document.getElementById("wom-track").addEventListener("click", function(){
        var no = document.getElementById("wom-order").value.trim();
        var em = document.getElementById("wom-email").value.trim();
        renderTrack("?order="+encodeURIComponent(no)+"&email="+encodeURIComponent(em));
    });
    if(token){ renderTrack("?token="+encodeURIComponent(token)); }
})();</script>
JS;
        return ob_get_clean();
    } );
} );


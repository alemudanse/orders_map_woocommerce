<?php
/**
 * Notifications (email/SMS hooks)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send POD confirmation link via selected method
 *
 * @param int    $order_id
 * @param string $method   'email' or 'sms'
 * @param string $confirm_url
 */
add_action( 'wom_send_pod_confirmation', function ( $order_id, $method, $confirm_url ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	if ( 'email' === $method ) {
		$to      = $order->get_billing_email();
		$subject = __( 'Confirm your delivery', 'woocommerce-orders-map' );
		$body    = '<p>' . sprintf( __( 'Please confirm you received order #%s.', 'woocommerce-orders-map' ), esc_html( $order->get_order_number() ) ) . '</p>';
		$body   .= '<p><a href="' . esc_url( $confirm_url ) . '">' . esc_html__( 'Confirm Delivery', 'woocommerce-orders-map' ) . '</a></p>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
	}

	if ( 'sms' === $method ) {
		// Stub: integrate with SMS gateway. For now, write an order note.
		$phone = $order->get_billing_phone();
		wc_create_order_note( $order_id, sprintf( __( 'SMS confirmation link to %1$s: %2$s', 'woocommerce-orders-map' ), esc_html( $phone ), esc_url( $confirm_url ) ) );
	}
}, 10, 3 );

/**
 * Send customer location request
 */
add_action( 'wom_send_location_request', function ( $order_id, $method, $track_url ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }
	if ( 'email' === $method ) {
		$to      = $order->get_billing_email();
		$subject = __( 'Share your location for delivery', 'woocommerce-orders-map' );
		$body    = '<p>' . esc_html__( 'Please share your current location to help our driver find you.', 'woocommerce-orders-map' ) . '</p>';
		$body   .= '<p><a href="' . esc_url( $track_url ) . '">' . esc_html__( 'Share my location', 'woocommerce-orders-map' ) . '</a></p>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
	} else {
		$phone = $order->get_billing_phone();
		wc_create_order_note( $order_id, sprintf( __( 'SMS location link to %1$s: %2$s', 'woocommerce-orders-map' ), esc_html( $phone ), esc_url( $track_url ) ) );
	}
}, 10, 3 );

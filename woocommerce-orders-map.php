<?php
/**
 * Plugin Name: WooCommerce Orders Map
 * Description: Map view of WooCommerce orders with driver assignment and a driver dashboard.
 * Version: 0.1.0
 * Author: Your Company
 * License: GPLv2 or later
 * Text Domain: woocommerce-orders-map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core plugin constants
if ( ! defined( 'WOM_PLUGIN_FILE' ) ) {
	define( 'WOM_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WOM_PLUGIN_VERSION' ) ) {
	define( 'WOM_PLUGIN_VERSION', '0.1.0' );
}
if ( ! defined( 'WOM_PLUGIN_PATH' ) ) {
	define( 'WOM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WOM_PLUGIN_URL' ) ) {
	define( 'WOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Meta keys used by this plugin
if ( ! defined( 'WOM_META_ASSIGNED_DRIVER' ) ) {
	define( 'WOM_META_ASSIGNED_DRIVER', '_wom_assigned_driver' );
}
if ( ! defined( 'WOM_META_DRIVER_STATUS' ) ) {
	define( 'WOM_META_DRIVER_STATUS', '_wom_driver_status' );
}
if ( ! defined( 'WOM_META_POD_TOKEN' ) ) {
	define( 'WOM_META_POD_TOKEN', '_wom_pod_token' );
}
if ( ! defined( 'WOM_META_POD_EXPIRES' ) ) {
	define( 'WOM_META_POD_EXPIRES', '_wom_pod_expires' );
}
if ( ! defined( 'WOM_META_POD_CONFIRMED' ) ) {
	define( 'WOM_META_POD_CONFIRMED', '_wom_pod_confirmed' );
}
if ( ! defined( 'WOM_META_POD_METHOD' ) ) {
	define( 'WOM_META_POD_METHOD', '_wom_pod_method' );
}
if ( ! defined( 'WOM_META_TRACK_TOKEN' ) ) {
	define( 'WOM_META_TRACK_TOKEN', '_wom_track_token' );
}
if ( ! defined( 'WOM_META_TRACK_EXPIRES' ) ) {
	define( 'WOM_META_TRACK_EXPIRES', '_wom_track_expires' );
}
if ( ! defined( 'WOM_META_CUSTOMER_LAT' ) ) {
	define( 'WOM_META_CUSTOMER_LAT', '_wom_customer_lat' );
}
if ( ! defined( 'WOM_META_CUSTOMER_LNG' ) ) {
	define( 'WOM_META_CUSTOMER_LNG', '_wom_customer_lng' );
}
if ( ! defined( 'WOM_META_CUSTOMER_LOC_AT' ) ) {
	define( 'WOM_META_CUSTOMER_LOC_AT', '_wom_customer_loc_at' );
}
if ( ! defined( 'WOM_META_DRIVER_LAT' ) ) {
	define( 'WOM_META_DRIVER_LAT', '_wom_driver_lat' );
}
if ( ! defined( 'WOM_META_DRIVER_LNG' ) ) {
	define( 'WOM_META_DRIVER_LNG', '_wom_driver_lng' );
}
if ( ! defined( 'WOM_META_DRIVER_LOC_AT' ) ) {
	define( 'WOM_META_DRIVER_LOC_AT', '_wom_driver_loc_at' );
}

// Bootstrap feature modules
require_once WOM_PLUGIN_PATH . 'includes/driver-dashboard.php';
require_once WOM_PLUGIN_PATH . 'includes/settings.php';
require_once WOM_PLUGIN_PATH . 'includes/geocoding.php';
require_once WOM_PLUGIN_PATH . 'includes/roles.php';
require_once WOM_PLUGIN_PATH . 'includes/tracking.php';

// Optionally, future modules can be required here
require_once WOM_PLUGIN_PATH . 'includes/map-dashboard.php';
require_once WOM_PLUGIN_PATH . 'includes/driver-frontend-reports.php';
require_once WOM_PLUGIN_PATH . 'includes/notifications.php';

// Ensure WooCommerce is active (soft check)
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// WooCommerce not active. We keep plugin loaded but features relying on WC should guard themselves.
	}
	// Load translations
	load_plugin_textdomain( 'woocommerce-orders-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Activation/Deactivation hooks for cron schedules
register_activation_hook( WOM_PLUGIN_FILE, function () {
	if ( ! wp_next_scheduled( 'wom_geocode_backfill_event' ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'wom_geocode_backfill_event' );
	}
} );

register_deactivation_hook( WOM_PLUGIN_FILE, function () {
	wp_clear_scheduled_hook( 'wom_geocode_backfill_event' );
} );

// No closing PHP tag to avoid accidental output
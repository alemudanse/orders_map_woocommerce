<?php
/**
 * Settings page for API keys and feature toggles
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Orders Map Settings', 'woocommerce-orders-map' ),
		__( 'Orders Map Settings', 'woocommerce-orders-map' ),
		'manage_woocommerce',
		'wom-settings',
		'wom_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'wom_settings', 'wom_settings', array( 'type' => 'array', 'sanitize_callback' => 'wom_sanitize_settings' ) );

	add_settings_section( 'wom_section_api', __( 'API Keys', 'woocommerce-orders-map' ), '__return_false', 'wom_settings' );
	add_settings_field( 'map_provider', __( 'Map Provider', 'woocommerce-orders-map' ), 'wom_field_map_provider', 'wom_settings', 'wom_section_api' );
	add_settings_field( 'maps_api_key', __( 'Maps JS API Key (Google)', 'woocommerce-orders-map' ), 'wom_field_maps_api_key', 'wom_settings', 'wom_section_api' );
	add_settings_field( 'geocoding_provider', __( 'Geocoding Provider', 'woocommerce-orders-map' ), 'wom_field_geocoder', 'wom_settings', 'wom_section_api' );
	add_settings_field( 'geocoding_api_key', __( 'API Key', 'woocommerce-orders-map' ), 'wom_field_api_key', 'wom_settings', 'wom_section_api' );

	add_settings_section( 'wom_section_features', __( 'Features', 'woocommerce-orders-map' ), '__return_false', 'wom_settings' );
	add_settings_field( 'enable_pod', __( 'Enable Proof of Delivery', 'woocommerce-orders-map' ), 'wom_field_enable_pod', 'wom_settings', 'wom_section_features' );
	add_settings_field( 'enable_live_tracking', __( 'Enable Live Tracking (future)', 'woocommerce-orders-map' ), 'wom_field_enable_live', 'wom_settings', 'wom_section_features' );
	add_settings_field( 'token_only_tracking', __( 'Require Token-Only Tracking', 'woocommerce-orders-map' ), 'wom_field_token_only_tracking', 'wom_settings', 'wom_section_features' );
} );

function wom_sanitize_settings( $input ) {
	$output = array();
	$output['map_provider']         = isset( $input['map_provider'] ) ? sanitize_text_field( $input['map_provider'] ) : 'osm';
	$output['maps_api_key']         = isset( $input['maps_api_key'] ) ? sanitize_text_field( $input['maps_api_key'] ) : '';
	$output['geocoding_provider']   = isset( $input['geocoding_provider'] ) ? sanitize_text_field( $input['geocoding_provider'] ) : 'nominatim';
	$output['geocoding_api_key']    = isset( $input['geocoding_api_key'] ) ? sanitize_text_field( $input['geocoding_api_key'] ) : '';
	$output['enable_pod']           = ! empty( $input['enable_pod'] ) ? 1 : 0;
	$output['enable_live_tracking'] = ! empty( $input['enable_live_tracking'] ) ? 1 : 0;
	$output['token_only_tracking']  = ! empty( $input['token_only_tracking'] ) ? 1 : 0;
	return $output;
}

function wom_get_settings() {
	$defaults = array(
		'map_provider'         => 'osm',
		'maps_api_key'         => '',
		'geocoding_provider'   => 'nominatim',
		'geocoding_api_key'    => '',
		'enable_pod'           => 1,
		'enable_live_tracking' => 0,
		'token_only_tracking'  => 0,
	);
	return wp_parse_args( get_option( 'wom_settings', array() ), $defaults );
}

function wom_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	$opts = wom_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Orders Map Settings', 'woocommerce-orders-map' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'wom_settings' ); ?>
			<?php do_settings_sections( 'wom_settings' ); ?>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Geocoding Backfill', 'woocommerce-orders-map' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'wom_backfill' ); ?>
			<input type="hidden" name="wom_backfill" value="1" />
			<button class="button" type="submit"><?php esc_html_e( 'Run Backfill (50 orders)', 'woocommerce-orders-map' ); ?></button>
		</form>
	</div>
	<?php
}

// Handle manual backfill trigger
add_action( 'admin_init', function () {
	if ( isset( $_POST['wom_backfill'] ) && check_admin_referer( 'wom_backfill' ) ) {
		wom_geocode_backfill_batch();
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Geocoding backfill run triggered.', 'woocommerce-orders-map' ) . '</p></div>';
		} );
	}
} );

function wom_field_geocoder() {
	$opts = wom_get_settings();
	echo '<select name="wom_settings[geocoding_provider]">';
	echo '<option value="nominatim"' . selected( $opts['geocoding_provider'], 'nominatim', false ) . '>Nominatim (OSM)</option>';
	echo '<option value="google"' . selected( $opts['geocoding_provider'], 'google', false ) . '>Google Maps</option>';
	echo '</select>';
}

function wom_field_api_key() {
	$opts = wom_get_settings();
	echo '<input type="text" class="regular-text" name="wom_settings[geocoding_api_key]" value="' . esc_attr( $opts['geocoding_api_key'] ) . '" />';
}

function wom_field_map_provider() {
	$opts = wom_get_settings();
	echo '<select name="wom_settings[map_provider]">';
	echo '<option value="osm"' . selected( $opts['map_provider'], 'osm', false ) . '>OpenStreetMap (Leaflet)</option>';
	echo '<option value="google"' . selected( $opts['map_provider'], 'google', false ) . '>Google Maps</option>';
	echo '</select>';
}

function wom_field_maps_api_key() {
	$opts = wom_get_settings();
	echo '<input type="text" class="regular-text" name="wom_settings[maps_api_key]" value="' . esc_attr( $opts['maps_api_key'] ) . '" />';
}

function wom_field_enable_pod() {
	$opts = wom_get_settings();
	echo '<label><input type="checkbox" name="wom_settings[enable_pod]" value="1"' . checked( $opts['enable_pod'], 1, false ) . ' /> ' . esc_html__( 'Enable Proof of Delivery flow', 'woocommerce-orders-map' ) . '</label>';
}

function wom_field_enable_live() {
	$opts = wom_get_settings();
	echo '<label><input type="checkbox" name="wom_settings[enable_live_tracking]" value="1"' . checked( $opts['enable_live_tracking'], 1, false ) . ' /> ' . esc_html__( 'Enable live tracking features (future)', 'woocommerce-orders-map' ) . '</label>';
}

function wom_field_token_only_tracking() {
	$opts = wom_get_settings();
	echo '<label><input type="checkbox" name="wom_settings[token_only_tracking]" value="1"' . checked( $opts['token_only_tracking'], 1, false ) . ' /> ' . esc_html__( 'Require token for customer tracking (disable order+email fallback)', 'woocommerce-orders-map' ) . '</label>';
}


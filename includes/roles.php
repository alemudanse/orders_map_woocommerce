<?php
/**
 * Roles and capabilities
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function wom_register_roles_and_caps() {
    // Driver role
    add_role( 'wom_driver', __( 'Driver', 'woocommerce-orders-map' ), array( 'read' => true ) );

    // Capabilities for managers/admins
    $roles = array( 'administrator', 'shop_manager' );
    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            $role->add_cap( 'wom_manage_assignments' );
            $role->add_cap( 'wom_view_reports' );
            $role->add_cap( 'wom_initiate_pod' );
        }
    }
}

add_action( 'init', function () {
	// Ensure caps exist even if activation hook missed
	wom_register_roles_and_caps();
} );


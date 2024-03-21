<?php
/*
Plugin Name: Paid Memberships Pro - Proration Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-proration/
Description: Custom Prorating Code for Paid Memberships Pro
Version: .3.1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

define( 'PMPRORATE_DIR', dirname( __FILE__ ) );

/**
 * Load the languages folder for translations.
 */
function pmprorate_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-proration', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmprorate_load_plugin_text_domain' );

include_once( PMPRORATE_DIR . '/classes/pmprorate-class-downgrade.php' ); // Handles downgrades.
include_once( PMPRORATE_DIR . '/includes/checkout.php' ); // Handles proration at checkout
include_once( PMPRORATE_DIR . '/includes/delayed-downgrades.php' ); // Handles downgrade UI and processing delayed downgrades.
include_once( PMPRORATE_DIR . '/includes/emails.php' ); // Handles emails.
include_once( PMPRORATE_DIR . '/includes/upgradecheck.php' ); // Checks for upgrades.
include_once( PMPRORATE_DIR . '/includes/deprecated.php' ); // Deprecated functions.

// Set up $wpdb tables.
global $wpdb;
$wpdb->pmprorate_downgrades = $wpdb->prefix . 'pmprorate_downgrades';

/**
 * Add links to the plugin row meta
 */
function pmproproate_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-proration.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/plus-add-ons/proration-prorate-membership/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-proration' ) ) . '">' . esc_html__( 'Docs', 'pmpro-proration' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-proration' ) ) . '">' . esc_html__( 'Support', 'pmpro-proration' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}

	return $links;
}

add_filter( 'plugin_row_meta', 'pmproproate_plugin_row_meta', 10, 2 );

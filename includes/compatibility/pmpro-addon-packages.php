<?php
/**
 * Compatibility with the Add On Packages Add On.
 *
 * Purchasing an Add On Package should not change the buyer's own membership, so skip proration for package checkouts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Before proration runs, unhook it if an Add On Package is being purchased.
 *
 * @param object $level The level being purchased.
 * @return object
 */
function pmprorate_pmproap_maybe_skip_checkout_level( $level ) {
	global $pmprorate_pmproap_skipped_checkout_level;

	if ( ! empty( pmproap_get_addon_price_at_checkout() ) ) {
		$pmprorate_pmproap_skipped_checkout_level = remove_filter( 'pmpro_checkout_level', 'pmprorate_pmpro_checkout_level', 10 );
	}
	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmprorate_pmproap_maybe_skip_checkout_level', 9 );

/**
 * After proration would have run, hook it back up if we unhooked it.
 *
 * @param object $level The level being purchased.
 * @return object
 */
function pmprorate_pmproap_restore_checkout_level( $level ) {
	global $pmprorate_pmproap_skipped_checkout_level;

	if ( ! empty( $pmprorate_pmproap_skipped_checkout_level ) ) {
		add_filter( 'pmpro_checkout_level', 'pmprorate_pmpro_checkout_level', 10, 1 );
		$pmprorate_pmproap_skipped_checkout_level = false;
	}
	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmprorate_pmproap_restore_checkout_level', 11 );

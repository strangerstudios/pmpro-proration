<?php
/**
 * Compatibility with the Gift Levels Add On.
 *
 * Purchasing a gift level should not change the buyer's own membership, so skip proration for gift levels.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Before proration runs, unhook it if the level being purchased is a gift level.
 *
 * @param object $level The level being purchased.
 * @return object
 */
function pmprorate_pmprogl_maybe_skip_checkout_level( $level ) {
	global $pmprorate_pmprogl_skipped_checkout_level;

	if ( ! empty( $level->id ) && pmprogl_is_gift_level( $level->id ) ) {
		$pmprorate_pmprogl_skipped_checkout_level = remove_filter( 'pmpro_checkout_level', 'pmprorate_pmpro_checkout_level', 10 );
	}
	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmprorate_pmprogl_maybe_skip_checkout_level', 9 );

/**
 * After proration would have run, hook it back up if we unhooked it.
 *
 * @param object $level The level being purchased.
 * @return object
 */
function pmprorate_pmprogl_restore_checkout_level( $level ) {
	global $pmprorate_pmprogl_skipped_checkout_level;

	if ( ! empty( $pmprorate_pmprogl_skipped_checkout_level ) ) {
		add_filter( 'pmpro_checkout_level', 'pmprorate_pmpro_checkout_level', 10, 1 );
		$pmprorate_pmprogl_skipped_checkout_level = false;
	}
	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmprorate_pmprogl_restore_checkout_level', 11 );

/**
 * Before the prorated discount code price text is output, unhook it if the level is a gift level.
 *
 * @param string $discount_code    The discount code being applied.
 * @param int    $discount_code_id The ID of the discount code.
 * @param int    $level_id         The ID of the level being purchased.
 */
function pmprorate_pmprogl_maybe_skip_applydiscountcode_return_js( $discount_code, $discount_code_id, $level_id ) {
	global $pmprorate_pmprogl_skipped_applydiscountcode_return_js;

	if ( ! empty( $level_id ) && pmprogl_is_gift_level( $level_id ) ) {
		$pmprorate_pmprogl_skipped_applydiscountcode_return_js = remove_action( 'pmpro_applydiscountcode_return_js', 'pmprorate_applydiscountcode_return_js', 10 );
	}
}
add_action( 'pmpro_applydiscountcode_return_js', 'pmprorate_pmprogl_maybe_skip_applydiscountcode_return_js', 9, 3 );

/**
 * After the discount code price text would have been output, hook it back up if we unhooked it.
 */
function pmprorate_pmprogl_restore_applydiscountcode_return_js() {
	global $pmprorate_pmprogl_skipped_applydiscountcode_return_js;

	if ( ! empty( $pmprorate_pmprogl_skipped_applydiscountcode_return_js ) ) {
		add_action( 'pmpro_applydiscountcode_return_js', 'pmprorate_applydiscountcode_return_js', 10, 4 );
		$pmprorate_pmprogl_skipped_applydiscountcode_return_js = false;
	}
}
add_action( 'pmpro_applydiscountcode_return_js', 'pmprorate_pmprogl_restore_applydiscountcode_return_js', 11 );

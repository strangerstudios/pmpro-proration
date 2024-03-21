<?php

/**
 * Only run this downgrade code if using PMPro v3.0+.
 *
 * @since TBD
 */
function pmprorate_init_downgrades() {
	// Make sure that we are using PMPro v3.0+.
	if ( ! class_exists( 'PMPro_Subscription' ) ) {
		return;
	}

	// Hook functions to set up downgrades.
	add_action( 'pmpro_added_order', 'pmprorate_added_order_mark_order_as_downgrade' ); // Running after order is created so that we can update metadata.
	add_filter( 'pmpro_checkout_before_change_membership_level', 'pmprorate_checkout_before_change_membership_level_remember_downgrade', 20, 2 ); // Priority 20 to run after offsite payment gateway redirects.

	// Hook functions to process downgrades.
	add_action( 'pmpro_added_order', 'pmprorate_added_order_process_downgrade' ); // Running after the order is created so that the new order gets its membership ID changed to the new level.
	add_action( 'pmpro_membership_pre_membership_expiry', 'pmprorate_membership_pre_membership_expiry', 10, 2 );

	// Hook function to remove downgrade if the corresponding level is lost.
	add_action( 'pmpro_after_all_membership_level_changes', 'pmprorate_after_all_membership_level_changes_check_downgrades' );

	// Hook functions to show notices about downgrades.
	add_action( 'pmpro_invoice_bullets_bottom', 'pmprorate_invoice_bullets_buttom_downgrades', 10, 1 );
	add_filter( 'pmpro_membership_expiration_text', 'pmprorate_membership_expiration_text_downgrades', 10, 3 );
	add_filter( 'pmpro_member_edit_panels', 'pmprorate_member_edit_panels_downgrades', 10, 1 );

}
add_action( 'init', 'pmprorate_init_downgrades' );

/**
 * After an order is created, check the global $pmprorate_is_downgrade variable.
 * If it is set, then mark the order as a downgrade to be scheduled later.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order object.
 */
function pmprorate_added_order_mark_order_as_downgrade( $order ) {
	global $pmprorate_is_downgrade;

	// If we don't have an order, bail.
	if ( empty( $order ) || empty( $order->id ) ) {
		return;
	}

	// Update order meta.
	if ( ! empty( $pmprorate_is_downgrade ) ) {
		update_pmpro_membership_order_meta( $order->id, 'pmprorate_is_downgrade', $pmprorate_is_downgrade );
	}
}

/**
 * Bail from the checkout process when a user is downgrading. The downgrade will be completed asynchronously.
 *
 * @since TBD
 *
 * @param int $user_id The ID of the user.
 * @param MemberOrder $order The order object.
 */
function pmprorate_checkout_before_change_membership_level_remember_downgrade( $user_id, $order ) {
	global $wpdb, $pmpro_level, $pmprorate_is_downgrade;

	// If we don't have an order, then this checkout is free. Create a free order.
	if ( empty( $order ) ) {
		// Get the user's email address.
		$bemail = $wpdb->get_var( $wpdb->prepare( "SELECT user_email FROM $wpdb->users WHERE ID = %d LIMIT 1", $user_id ) );

		// Create a free order. Taken from core checkout preheader code.
		$order                 = new MemberOrder();
		$order->InitialPayment = 0;
		$order->Email          = $bemail;
		$order->gateway        = 'free';
		$order->status         = 'success';
		$order = apply_filters( "pmpro_checkout_order_free", $order );
		$order->user_id       = $user_id;
		$order->membership_id = $pmpro_level->id;
	}

	// If this order was not marked as a downgrade, bail.
	// $pmprorate_is_downgrade checks if the checkout started processing on this page load.
	// Checking order meta checks if the checkout started processing on a previous page load, such as with offsite payment gateways.
	if ( empty( get_pmpro_membership_order_meta( $order->id, 'pmprorate_is_downgrade', true ) ) && empty( $pmprorate_is_downgrade) ) {
		return;
	}

	// If we already have a downgrade scheduled for this order, bail.
	$downgrade_query_args = array(
		'downgrade_order_id' => $order->id,
	);
	if ( ! empty( PMProrate_Downgrade::get_downgrades( $downgrade_query_args ) ) ) {
		return;
	}

	// We need to sechedule the downgrade.
	// Get the level that the user is downgrading from.
	$downgrading_from_id = pmproprorate_get_level_id_being_switched_from( $user_id, $order->membership_id );
	if ( empty( $downgrading_from_id ) ) {
		// If we can't determine the level that the user is downgrading from, bail and let PMPro handle the checkout normally.
		return;
	}

	// Get the user's subscriptions for the old level.
	$old_subscriptions = PMPro_Subscription::get_subscriptions_for_user( $user_id, $downgrading_from_id );
	if ( ! empty( $old_subscriptions ) ) {
		// If the level being purchased will not have a subscription, set the expiration date for the
		// user's current membership to the next payment date of their current subscription.
		// This is so that we know when to downgrade the membership without an active subscription.
		if ( empty( (float)$pmpro_level->billing_amount) ) {
			// Get the next payment date for the user's current subscription.
			$next_payment_date = $old_subscriptions[0]->get_next_payment_date( 'Y-m-d H:i:s' );
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->pmpro_memberships_users SET enddate = %s WHERE user_id = %d AND membership_id = %d AND status = 'active'", $next_payment_date, $user_id, $downgrading_from_id ) );
		}

		// Cancel the user's subscriptions for the old level.
		foreach ( $old_subscriptions as $subscription_to_cancel ) {
			$subscription_to_cancel->cancel_at_gateway();
		}
	}

	// Update the order's membership level ID to the level that the user is downgrading from.
	$order->membership_id = $downgrading_from_id;
	$order->user_id = $user_id;
	$order->status  = 'success';
	$order->saveOrder();

	// Save the data collected at checkout to the order.
	pmpro_save_checkout_data_to_order( $order );

	// Update the subscription's membership level ID to the level that the user is downgrading from.
	$subscription = $order->get_subscription();
	if ( ! empty( $subscription ) ) {
		$subscription->set( 'membership_level_id', $downgrading_from_id );
		$subscription->save();
	}

	// Create the downgrade.
	$downgrade = PMProrate_Downgrade::create( $user_id, $downgrading_from_id, $pmpro_level->id, $order->id );
	if ( empty( $downgrade ) ) {
		// Creating the downgrade failed. Bail and let PMPro handle the checkout normally.
		return;
	}

	// Prepare emails stating that their membership will be downgraded.
	$user = get_userdata( $user_id );
	$data = array(
		'display_name' => $user->display_name,
		'sitename' => get_bloginfo( 'name' ),
		'login_url' => wp_login_url(),
		'pmprorate_downgrade_text' => $downgrade->get_downgrade_text(),
		'edit_member_downgrade_url' => admin_url( 'admin.php?page=pmpro-member&user_id=' . $user_id . '&pmpro_member_edit_panel=pmprorate-downgrades' ),
	);

	// Send email to user.
	$data['header_name'] = $user->display_name;
	$email = new PMProEmail();
	$email->template = 'delayed_downgrade_scheduled';
	$email->email = $user->user_email;
	$email->data = $data;
	$email->sendEmail();

	// Send email to the site admin.
	unset( $data['header_name'] );
	$email_admin = new PMProEmail();
	$email_admin->template = 'delayed_downgrade_scheduled_admin';
	$email_admin->email = get_bloginfo( 'admin_email' );
	$email_admin->data = $data;
	$email_admin->sendEmail();

	// Redirect to the invoice page instead of changing the user's level and completing the checkout process.
	wp_redirect( add_query_arg( 'invoice', $order->code, pmpro_url( 'invoice' ) ) );
	exit;
}

/**
 * Add downgrade information to the invoice page.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order object being shown.
 */
function pmprorate_invoice_bullets_buttom_downgrades( $order ) {
	// Check if this order is associated with a pending downgrade.
	$downgrade_query_args = array(
		'downgrade_order_id' => $order->id,
		'status' => 'pending',
	);
	$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );

	// If we don't have any downgrades, bail.
	if ( empty( $downgrades ) ) {
		return;
	}

	// Get the downgrade text.
	$downgrade_text = $downgrades[0]->get_downgrade_text();
	if ( empty( $downgrade_text ) ) {
		return;
	}

	// Show the downgrade text.
	echo '<li>' . esc_html( $downgrade_text ) . '</li>';
}

/**
 * Add downgrade information to the account page.
 *
 * @since TBD
 *
 * @param string $expiration_text The expiration text.
 * @param object $level The level object.
 * @param WP_User $user The user object.
 *
 * @return string
 */
function pmprorate_membership_expiration_text_downgrades( $expiration_text, $level, $user ) {
	// Get the user's pending downgrades for this level.
	$downgrade_query_args = array(
		'user_id' => $user->ID,
		'original_level_id' => $level->id,
		'status' => 'pending',
	);
	$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );

	// Show a downgrade notice for each pending downgrade.
	foreach ( $downgrades as $downgrade ) {
		// Get the downgrade text.
		$downgrade_text = $downgrade->get_downgrade_text();
		if ( ! empty( $downgrade_text ) ) {
			// Show the downgrade text.
			$expiration_text = $downgrade_text;
			break;
		}
	}

	return $expiration_text;
}

/**
 * Add a panel to the Edit Member dashboard page.
 *
 * @since TBD
 *
 * @param array $panels Array of panels.
 * @return array
 */
function pmprorate_member_edit_panels_downgrades( $panels ) {
	// If the class doesn't exist and the abstract class does, require the class.
	if ( ! class_exists( 'PMProup_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		require_once( PMPRORATE_DIR . '/classes/pmprorate-class-member-edit-panel-downgrades.php' );
	}

	// If the class exists, add a panel.
	if ( class_exists( 'PMProrate_Member_Edit_Panel_Downgrades' ) ) {
		$panels[] = new PMProrate_Member_Edit_Panel_Downgrades();
	}

	return $panels;
}

/**
 * When an order is created, check if is a part of a subscription.
 * If so, check if the subscription has a downgrade order linked.
 * If so, process the downgrade.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order object.
 */
 function pmprorate_added_order_process_downgrade( $order ) {
	// Get the subscription for this order.
	$subscription = $order->get_subscription();
	if ( empty( $subscription ) ) {
		// If this order is not for a subscription, bail.
		return;
	}

	// Get all pending downgrades for this user and the order's membership level.
	$downgrade_query_args = array(
		'user_id' => $order->user_id,
		'original_level_id' => $order->membership_id,
		'status' => 'pending',
	);
	$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );

	// If we don't have any downgrades, bail.
	if ( empty( $downgrades ) ) {
		return;
	}

	// For each downgrade, check if $order is part of the same subscription as the downgrade's order.
	$downgrade_processed = false;
	foreach ( $downgrades as $downgrade ) {
		// If we have already processed a downgrade for this order, then we should not process another.
		if ( $downgrade_processed ) {
			$downgrade->update_status( 'error' );
			continue;
		}

		// Get the order for this downgrade.
		$downgrade_order = MemberOrder::get_order( $downgrade->downgrade_order_id );
		if ( empty( $downgrade_order ) ) {
			// If we can't get the order for this downgrade, bail.
			$downgrade->update_status( 'error' );
			continue;
		}

		// Get the subscription for this order.
		$downgrade_subscription = $downgrade_order->get_subscription();
		if ( empty( $downgrade_subscription ) ) {
			// If this order is not for a subscription, bail.
			$downgrade->update_status( 'error' );
			continue;
		}

		// If the subscription for this downgrade is not the same as the subscription for the order, bail.
		if ( $subscription->get_id() !== $downgrade_subscription->get_id() ) {
			$downgrade->update_status( 'error' );
			continue;
		}

		// If we get here, then we have a downgrade for this order. Process it.
		$downgrade_processed = $downgrade->process( $order );
	}
}

/**
 * When a membership is about to expire, check if it is part of a downgrade.
 * If so, process the downgrade.
 *
 * @since TBD
 *
 * @param int $user_id The ID of the user having a level expired.
 * @param int $level_id The ID of the level being expired.
 */
function pmprorate_membership_pre_membership_expiry( $user_id, $level_id ) {
	// Check if the user has a pending downgrade for this level.
	$downgrade_query_args = array(
		'user_id' => $user_id,
		'original_level_id' => $level_id,
		'status' => 'pending',
	);
	$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );
	if ( empty( $downgrades ) ) {
		// If the user does not have a pending downgrade for this level, bail.
		return;
	}

	// For each downgrade, try to process it. We should only have one downgrade, so once we process one, mark the rest as errors.
	$downgrade_processed = false;
	foreach ( $downgrades as $downgrade ) {
		// If we have already processed a downgrade for this level, then we should not process another.
		if ( $downgrade_processed ) {
			$downgrade->update_status( 'error' );
			continue;
		}

		// Process the downgrade.
		$downgrade_processed = $downgrade->process();
	}

	// Now that their level is downgraded, it should not be expired, but we still want to skip the expiration email.
	add_action( 'pmpro_send_expiration_email', 'pmprorate_send_expiration_email' );
}

/**
 * Skip the next expiration email.
 *
 * @since TBD
 *
 * @param bool $skip Whether to skip the expiration email.
 * @return bool Whether to skip the expiration email.
 */
 function pmprorate_send_expiration_email( $skip ) {
	// Unhook this function so that it doesn't run for future expirations.
	remove_action( 'pmpro_send_expiration_email', 'pmprorate_send_expiration_email' );

	// Skip the expiration email.
	return false;
 }

/**
 * When a user loses a level, check if they have a pending downgrade for that level.
 * If so, remove the downgrade.
 *
 * @since TBD
 *
 * @param array $old_user_levels The old levels the users had.
 */
function pmprorate_after_all_membership_level_changes_check_downgrades( $old_user_levels ) {
	// Loop through all users who have had changed levels.
	foreach ( $old_user_levels as $user_id => $old_levels ) {
		// Get the IDs of the user's old levels.
		$old_level_ids = wp_list_pluck( $old_levels, 'id' );

		// Get the new level for this user.
		$new_levels    = pmpro_getMembershipLevelsForUser( $user_id );
		$new_level_ids = wp_list_pluck( $new_levels, 'id' );

		// Get the levels that the user lost.
		$lost_level_ids = array_diff( $old_level_ids, $new_level_ids );

		// Check if the lost level IDs have any pending downgrades.
		foreach ( $lost_level_ids as $lost_level_id ) {
			$downgrade_query_args = array(
				'user_id' => $user_id,
				'original_level_id' => $lost_level_id,
				'status' => 'pending',
			);
			$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );

			// Mark any pending downgrades as lost_original_level.
			foreach ( $downgrades as $downgrade ) {
				$downgrade->update_status( 'lost_original_level' );
			}
		}
	}
}

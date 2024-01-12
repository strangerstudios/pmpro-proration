<?php

/**
 * Update a start date based on the last payment date and the new payment period.
 *
 * @deprecated TBD
 */
function pmprorate_set_startdate_one_period_out_from_last_payment_date( $startdate, $order ) {
	_deprecated_function( __FUNCTION__, 'TBD' );

	// Fetch the date of the most recent order for current user
	if ( ! empty( $order->user_id ) ) {
		$last_recurring_order_datetime = pmprorate_pmpro_getLastRecurringOrderDatetime( $order->user_id );
	} else {
		$last_recurring_order_datetime = pmprorate_pmpro_getLastRecurringOrderDatetime();
	}

	if ( ! empty( $last_recurring_order_datetime ) ) {
		$startdate = date_i18n( "Y-m-d", strtotime( date_i18n( "Y-m-d", $last_recurring_order_datetime ) . " +{$order->BillingFrequency} {$order->BillingPeriod}" ) ) . "T0:0:0";
	}
	
	return $startdate;
}

/**
 * Get the date of the last recurring order.
 *
 * @deprecated TBD
 */
function pmprorate_pmpro_getLastRecurringOrderDatetime( $user_id = null ) {
	global $wpdb;

	_deprecated_function( __FUNCTION__, 'TBD' );

	// Fetch most recent order
	$lastorder = new MemberOrder();
	$lastorder->getLastMemberOrder( $user_id );

	//no order? no current membership
	if ( empty( $lastorder ) ) {
		return false;
	}

	//check gateways
	if ( $lastorder->gateway == "stripe" ) {
		//get subscription from Stripe API and look for current_period_start
		$gateway      = new PMProGateway_stripe();
		$subscription = $gateway->getSubscription( $lastorder );

		if ( ! empty( $subscription ) && ! empty( $subscription->current_period_start ) ) {
			return $subscription->current_period_start;
		}
	}

	//check if this order is recurring and find the latest recurring order
	$check12 = 0;
	while ( ! pmprorate_pmpro_isOrderRecurring( $lastorder ) && $check12 ++ < 12 ) {

		$new_order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id 
					FROM {$wpdb->pmpro_membership_orders} 
					WHERE user_id = %d
					 AND status IN('', 'success') 
					 AND id < %d ORDER BY timestamp DESC LIMIT 1",
				$lastorder->user_id,
				$lastorder->id
			)
		);

		if ( ! empty( $new_order_id ) ) {
			$lastorder = new MemberOrder( $new_order_id );
		}

		if ( empty( $lastorder ) || empty( $lastorder->id ) ) {
			return false;
		}    //found no recurring orders for this member
	}

	//use latest recurring PMPro order to figure out next payment date
	if ( ! empty( $lastorder ) && ! empty( $lastorder->timestamp ) ) {
		return $lastorder->timestamp;
	}

	return false;
}

/**
 * Check if a given order is recurring or not.
 *
 * @deprecated TBD
 */
function pmprorate_pmpro_isOrderRecurring( $order, $test_checkout = false ) {
	global $wpdb;

	_deprecated_function( __FUNCTION__, 'TBD' );

	//must have a subscription_transaction_id
	if ( empty( $order->subscription_transaction_id ) ) {
		return false;
	}

	//check that we aren't processing at checkout
	if ( $test_checkout && ! empty( $_REQUEST['submit-checkout'] ) ) {
		return false;
	}

	//check for earlier orders with the same gateway, user_id, membership_id, and subscription_transaction_id
	$sqlQuery = $wpdb->prepare(
		"SELECT id 
			FROM {$wpdb->pmpro_membership_orders} 
			WHERE gateway = %s
			 AND gateway_environment %s 
			 AND user_id = %d 
			 AND membership_id = %d 
			 AND subscription_transaction_id = %s 
			 AND timestamp < %s",
		$order->gateway,
		$order->gateway_environment,
		$order->user_id,
		$order->membership_id,
		$order->subscription_transaction_id,
		date_i18n( "Y-m-d", $order->timestamp )
	);

	if ( ! empty( $order->id ) ) {
		$sqlQuery .= " AND id <> {$order->id} ";
	}

	$sqlQuery .= "LIMIT 1";
	$earlier_order = $wpdb->get_var( esc_sql( $sqlQuery ) );

	if ( empty( $earlier_order ) ) {
		return false;
	}

	//must be recurring
	return true;
}

/**
 * Function to set up legacy downgrades for PMPro versions before 3.0.
 *
 * @since TBD
 *
 * @param object $clevel The user's current level that is being downgraded from.
 */
function pmprorate_legacy_downgrade_set_up( $clevel ) {
	global $pmpro_checkout_old_level, $current_user;
	$pmpro_checkout_old_level = $clevel;
	$pmpro_checkout_old_level->next_payment = pmprorate_trim_timestamp( pmpro_next_payment( $current_user->ID ) );
}

/**
 * After checkout, if the user downgraded, then revert to the old level and remember to change them to the new level later.
 */
function pmprorate_pmpro_after_checkout( $user_id ) {
	global $pmpro_checkout_old_level, $wpdb;

	// If using PMPro v3.0+, bail since we have a better downgrade process for 3.0+.
	if ( class_exists( 'PMPro_Subscription' ) ) {
		return;
	}

	if ( ! empty( $pmpro_checkout_old_level ) && ! empty( $pmpro_checkout_old_level->next_payment ) ) {
		$new_level = pmpro_getMembershipLevelForUser( $user_id );

		//remember to update to this level later
		update_user_meta( $user_id, "pmpro_change_to_level", array( "date"  => $pmpro_checkout_old_level->next_payment,
		                                                            "level" => $new_level->id
		) );

		//change their membership level
		if ( false === $wpdb->update(
				$wpdb->pmpro_memberships_users,
				array( 'membership_id' => $pmpro_checkout_old_level->id ),
				array( 'membership_id' => $new_level->id, 'user_id' => $user_id, 'status' => 'active' )
			)
		) {
			pmpro_setMessage( esc_html__( 'Problem updating membership information. Please report this to the webmaster.', 'pmpro-proration' ), 'error' );
		};
	} else {
		delete_user_meta( $user_id, "pmpro_change_to_level" );
	}
}
add_filter( 'pmpro_after_checkout', 'pmprorate_pmpro_after_checkout' );

/**
 * Update confirmation message.
 */
function pmprorate_pmpro_confirmation_message( $message, $invoice ) {
	if ( ! empty( $invoice ) && ! empty( $invoice->user_id ) ) {
		$downgrading = get_user_meta( $invoice->user_id, "pmpro_change_to_level", true );

		if ( ! empty( $downgrading ) ) {
			$dlevel = pmpro_getLevel( $downgrading['level'] );

			$message .= "<p>";
			$message .= esc_html(
				sprintf(
					__("You will be downgraded to %s on %s", "pmpro-proration"),
				    $dlevel->name,
				    date_i18n( get_option( "date_format" ), $downgrading['date'] )
				)
			);
			$message .= "</p>";
		}
	}

	return $message;
}

add_filter( "pmpro_confirmation_message", "pmprorate_pmpro_confirmation_message", 10, 2 );

/**
 * Update account page.
 */
function pmprorate_the_content( $content ) {
	global $current_user, $pmpro_pages;

	if ( is_user_logged_in() && is_page( $pmpro_pages['account'] ) ) {
		$downgrading = get_user_meta( $current_user->ID, "pmpro_change_to_level", true );

		if ( ! empty( $downgrading ) ) {
			$downgrade_level = pmpro_getLevel( $downgrading['level'] );

			$downgrade_message = "<p><strong>" . esc_html__( "Important Note:", "pmpro-proration" ) . "</strong>";
			$downgrade_message .= esc_html(
				sprintf(
					__( "You will be downgraded to %s on %s.", "pmpro-proration" ),
					$downgrade_level->name,
					date_i18n( get_option( "date_format" ), $downgrading['date'] )
				)
			);

			$content = $downgrade_message . $content;
		}
	}

	return $content;
}
add_filter( "the_content", "pmprorate_the_content" );

/**
 * Check for level changes daily.
 */
function pmproproate_daily_check_for_membership_changes() {
	global $wpdb;

	//make sure we only run once a day
	$today = date( "Y-m-d", current_time( 'timestamp' ) );

	//get all users with scheduled level changes
	$level_changes = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pmpro_change_to_level'" );

	if ( empty( $level_changes ) ) {
		return;
	}

	foreach ( $level_changes as $user_id ) {
		//today?
		$change = get_user_meta( $user_id, 'pmpro_change_to_level', true );

		if ( ! empty( $change ) && ! empty( $change['date'] ) && ! empty( $change['level'] ) && $change['date'] <= $today ) {
			//get user's current level
			$clevel_id = pmproprorate_get_level_id_being_switched_from( $user_id, $change['level'] );

			//change back
			if ( ! empty( $clevel_id ) ) {

				$wpdb->update( $wpdb->pmpro_memberships_users, array( 'membership_id' => $change['level'] ), array( 'membership_id' => $clevel_id, 'user_id' => $user_id, 'status' => 'active') );

			}

			//delete user meta
			delete_user_meta( $user_id, 'pmpro_change_to_level' );
		}
	}
}

//hook to run when pmpro_cron_expire_memberships does
add_action( 'pmpro_cron_expire_memberships', 'pmproproate_daily_check_for_membership_changes' );

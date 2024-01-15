<?php

/** 
 * Checks if the "delayed downgrade" flow should be used for this checkout.
 *
 * Even if the "delayed downgrade" flow is not being used, the initial payment
 * should still be set to $0 if the user is "downgrading".
 *
 * @since TBD
 *
 * @param object $old The level that the user is switching from.
 * @param object $new The level that the user is switching to.
 * @return bool True if the "delayed downgrade" flow should be used, false otherwise.
 */
function pmprorate_isDowngrade( $old, $new ) {
	// Do not allow downgrading to a level with an exipration date.
	// This is because we can't set an expiration date on the delayed downgrade.
	// Don't allow this to be filtered because the delayed downgrade flow won't handle an expiration date correctly.
	if ( ! empty( $new->expiration_number ) ) {
		return false;
	}

	// Check which PMPro version we are using.
	if ( class_exists( 'PMPro_Subscription' ) ) {
		// Using PMPro v3.0+.
		// Only allow a delayed downgrade if the user currently has an active subscription.
		// This is because we need a "next payment date" to set the delayed downgrade to.
		// Don't allow this to be filtered because the delayed downgrade flow won't handle a lack of subscription correctly.
		$current_subscription = PMPro_Subscription::get_subscriptions_for_user( get_current_user_id(), $old->id );
		if ( empty( $current_subscription ) ) {
			return false;
		}

		// Check if the user is purchasing a recurring membership or a "lifetime" membership.
		if ( empty( $new->billing_amount ) || empty( $new->cycle_number ) ) {
			// Buying a "lifetime" membership.
			// Downgrading if the initial payment for that membership is less than the current subscription billing amount.
			$r = $new->initial_payment < $current_subscription[0]->get_billing_amount();
		} else {
			// Buying a recurring membership.
			// Downgrading if the cost per day of the new membership is less than the cost per day of the old subscription.
			$current_cost_per_day = pmprorate_get_cost_per_day( $current_subscription[0]->get_billing_amount(), $current_subscription[0]->get_cycle_number(), $current_subscription[0]->get_cycle_period() );
			$new_cost_per_day     = pmprorate_get_cost_per_day( $new->billing_amount, $new->cycle_number, $new->cycle_period );
			$r = $new_cost_per_day < $current_cost_per_day;
		}
	} else {
		// If using PMPro v2.x, check if the new level has a smaller initial payment.
		$old_level = is_object( $old ) ? $old : pmpro_getLevel( $old );
		$new_level = is_object( $new ) ? $new : pmpro_getLevel( $new );

		if ( $old_level->initial_payment > $new_level->initial_payment ) {
			$r = true;
		} else {
			$r = false;
		}
	}

	/**
	 * Filter for whether or not a level change is a downgrade.
	 *
	 * @param bool $r True if the level change is a downgrade, false otherwise.
	 * @param int $old_level_id The ID of the old level. Ideally this should be an object, but need to pass the ID for backwards compatibility.
	 * @param int $new_level_id The ID of the new level. Ideally this should be an object, but need to pass the ID for backwards compatibility.
	 *
	 * @since TBD
	 *
	 * @return bool True if the level change is a downgrade, false otherwise.
	 */
	$r = apply_filters( "pmpro_is_downgrade", $r, $old->id, $new->id );

	return $r;
}

/** 
 * Function to check if two levels have the same payment period
 *
 */
function pmprorate_have_same_payment_period( $old, $new ) {
	// If using PMPro v3.0+, $old_level should have data from the user's current subscription.
	if ( class_exists( 'PMPro_Subscription' ) ) {
		// Get the user's current subscription.
		// If the user doesn't have a subscription, then the "payment period" is not the same.
		$current_subscription = PMPro_Subscription::get_subscriptions_for_user( get_current_user_id(), $old->id );
		if ( empty( $current_subscription ) ) {
			return false;
		}

		$corrected_old_level = new stdClass();
		$corrected_old_level->id           = $current_subscription[0]->get_membership_id();
		$corrected_old_level->cycle_number = $current_subscription[0]->get_cycle_number();
		$corrected_old_level->cycle_period = $current_subscription[0]->get_cycle_period();

		// Override $old_level with the corrected level so that we can use the same logic as PMPro v2.x.
		$old = $corrected_old_level;
	}

	$old_level = is_object( $old ) ? $old : pmpro_getLevel( $old );
	$new_level = is_object( $new ) ? $new : pmpro_getLevel( $new );

	if ( $old_level->cycle_number == $new_level->cycle_number &&
		 $old_level->cycle_period == $new_level->cycle_period ) {
		$r = true;
	} else {
		$r = false;
	}

	/**
	 * Filter for whether or not two levels have the same payment period.
	 *
	 * @param bool $r True if the levels have the same payment period, false otherwise.
	 * @param int $old_level_id The ID of the old level. Ideally this should be an object, but need to pass the ID for backwards compatibility.
	 * @param int $new_level_id The ID of the new level. Ideally this should be an object, but need to pass the ID for backwards compatibility.
	 *
	 * @since TBD
	 *
	 * @return bool True if the levels have the same payment period, false otherwise.
	 */
	$r = apply_filters( "pmpro_have_same_payment_period", $r, $old->id, $new->id );

	return $r;
}

/**
 * Trims the hours/minutes off of a timestamp
 */
function pmprorate_trim_timestamp($timestamp, $format = 'Y-m-d') {
	return strtotime( date( $format, $timestamp ), current_time( 'timestamp' ) );
}

/**
 * Update the initial payment amount at checkout per prorating rules.
 *
 * @param object $level The level that the user is purchasing.
 * @return object
 */
function pmprorate_pmpro_checkout_level( $level ) {	
	global $current_user, $pmprorate_is_downgrade;

	// Bail if no level.
	if ( empty( $level ) ) {
		return $level;
	}

	// Bail if not logged in.
	if ( empty( $current_user->ID ) ) {
		return $level;
	}

	// Can only prorate if they already have a level.
	// In PMPro v3.0+, this will only grab a level in the same "one level per group" membership group.
	$clevel_id = pmproprorate_get_level_id_being_switched_from( $current_user->ID, $level->id );
	if ( empty( $clevel_id ) ) {
		return $level;
	}

	// Get the full level object.
	$clevel = pmpro_getSpecificMembershipLevelForUser( $current_user->ID, $clevel_id );
	if ( empty( $clevel ) ) {
		return $level;
	}

	// Check if the user should get a delayed downgrade.
	if ( pmprorate_isDowngrade( $clevel, $level ) ) {
		/*
			* Downgrade rule in a nutshell:
			* 1. Charge $0 now.
			* 2. Set up new subscription to start billing on the current subscription's next payment date.
			* 3. Set up delayed downgrade to downgrade the user's membership on the next payment date/expiration date.
			*
			* Note: For PMPro versions before 3.0, we need to call pmprorate_legacy_downgrade_set_up() to set up the downgrade.
			*/
		$level->initial_payment = 0;

		// If purchasing a subscription, make sure payment date stays the same.
		add_filter( 'pmpro_profile_start_date', 'pmprorate_set_startdate_to_next_payment_date', 10, 2 );

		// Set up the delayed downgrade.
		$pmprorate_is_downgrade = true;

		// For PMPro versions before 3.0, we need to call pmprorate_legacy_downgrade_set_up() to set up the downgrade.
		if ( ! class_exists( 'PMPro_Subscription' ) ) {
			pmprorate_legacy_downgrade_set_up( $clevel );
		}

		// Bail to avoid further proration logic.
		return $level;
	}

	// Getting the next payment date and most recent order has different logic for PMPro v2.x and v3.0+.
	if ( class_exists( 'PMPro_Subscription' ) ) {
		// Using PMPro v3.0+.
		// Get the user's current subscription.
		$current_subscriptions = PMPro_Subscription::get_subscriptions_for_user( get_current_user_id(), $clevel_id );

		// No prorating needed if they don't have a subscription.
		if ( empty( $current_subscriptions ) ) {
			return $level;
		}

		// Get the current subscription.
		$current_subscription = current( $current_subscriptions );

		// Get the last payment date and next payment date.
		$newest_orders = $current_subscription->get_orders( array( 'limit' => 1 ) );
		if ( empty( $newest_orders ) ) {
			return $level;
		}

		// Get the most recent order.
		$prev_order = current( $newest_orders );

		// Get the next payment date.
		$next_payment_date = pmprorate_trim_timestamp( $current_subscription->get_next_payment_date() );
	} else {
		// Using PMPro v2.x.
		// Get the last order.
		$prev_order = new MemberOrder();
		$prev_order->getLastMemberOrder( $current_user->ID, array( 'success', '', 'cancelled' ), $clevel_id );

		// No prorating needed if they don't have an order (were given the level by an admin/etc).
		if ( empty( $prev_order->timestamp ) ) {
			return $level;
		}

		// Get the last payment date.
		$next_payment_date = pmprorate_trim_timestamp( pmpro_next_payment( $current_user->ID ) );
	}

	// Get the most recent payment date and today's date.
	$last_payment_date = pmprorate_trim_timestamp( $prev_order->timestamp );
	$today = pmprorate_trim_timestamp( current_time( 'timestamp' ) );

	// Calculate the total number of days in the current payment period.
	$days_in_period = ceil( ( $next_payment_date - $last_payment_date ) / 3600 / 24 );

	// If the next payment date is not after the last payment date, bail.
	if ( $days_in_period <= 0 ) {
		return $level;
	}

	// Get the percentage of the period that is left.
	$days_passed = ceil( ( $today - $last_payment_date ) / 3600 / 24 );
	$per_passed = $days_passed / $days_in_period;        //as a % (decimal)
	$per_left   = max( 1 - $per_passed, 0 );

	// Get the credit for the remaining time on the old level.
	$credit = $prev_order->subtotal * $per_left;

	// If changing to a level with a different payment period, keep the "next payment date" the same.
	if ( pmprorate_have_same_payment_period( $clevel, $level ) ) {
		/*
			Upgrade with same billing period in a nutshell:
			1. Calculate the initial payment to cover the remaining time in the current pay period.
			2. Setup subscription to start on next payment date at the new rate.

			Proration equation:
			(a) What they should pay for new level = $level->billing_amount * $per_left.
			(b) What they should have paid for current payment period = $prev_order->subtotal * $per_passed.
			What they need to pay = (a) + (b) - (what they already paid)
			
			If the number is negative, this would technically require a credit be given to the customer,
			but we don't currently have an easy way to do that across all gateways so we just 0 out the cost.
			
			This is the method used in the code below.
			
			An alternative calculation that comes up with the same number (but may be easier to understand) is:
			(a) What they should pay for new level = $level->billing_amount * $per_left.
			(b) Their credit for cancelling early = $prev_order->subtotal * $per_left.
			What they need to pay = (a) - (b)
		*/
		$remaining_cost_for_new_level = $level->billing_amount * $per_left;
		$level->initial_payment = min( $level->initial_payment, round( $remaining_cost_for_new_level - $credit, 2 ) );
		
		//make sure payment date stays the same
		add_filter( 'pmpro_profile_start_date', 'pmprorate_set_startdate_to_next_payment_date', 10, 2 );			
	} else {
		/*
			Upgrade with different payment periods in a nutshell:
			1. Apply a credit to the initial payment based on the partial period of their old level.
			2. New subscription starts today with the initial payment and will renew one period from now based on the new level.
		*/
		$level->initial_payment = round( $level->initial_payment - $credit, 2 );
	}

	// Make sure payment is not negative.
	if ( $level->initial_payment < 0 ) {
		$level->initial_payment = 0;
	}

	// Return the prorated level.
	return $level;
}
add_filter( "pmpro_checkout_level", "pmprorate_pmpro_checkout_level", 10, 1 );

/**
 * Set the date that the first recurring payment will be charged.
 *
 * @param string $startdate The start date for the membership.
 * @param object $order The order that is being purchased
 */
function pmprorate_set_startdate_to_next_payment_date( $startdate, $order ) {
	global $current_user;

	// If using PMPro v2.x, we don't have the PMPro_Subscription class. Use old logic.
	if ( ! class_exists( 'PMPro_Subscription' ) ) {
		//use APIs to be more specific
		if( $order->gateway == 'stripe' ) {
			remove_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);
			add_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);
		} elseif( $order->gateway == 'paypalexpress' ) {
			remove_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);
			add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);
		}
		
		// Note. This is not MMPU-compatible for PMPro v2.x.
		$next_payment_date = pmpro_next_payment( $current_user->ID );
			
		if( !empty( $next_payment_date ) )
			$startdate = date( "Y-m-d", $next_payment_date ) . "T0:0:0";
		
		return $startdate;
	}

	// Get the level ID that the user is switching from.
	$clevel_id = pmproprorate_get_level_id_being_switched_from( $current_user->ID, $order->membership_id );

	// Get the user's current subscription for that level.
	$current_subscriptions = PMPro_Subscription::get_subscriptions_for_user( get_current_user_id(), $clevel_id );

	// If the user doesn't have a subscription, bail. This shouldn't ever happen though since we check for this earlier.
	if ( empty( $current_subscriptions ) ) {
		return $startdate;
	}

	// Return the next payment date.
	$current_subscription = current( $current_subscriptions );
	return $current_subscription->get_next_payment_date( 'Y-m-d H:i:s' );
	
}

/**
 * If the user is going to lose a level at checkout, have it keep the same start date.
 * Updated from what's in paid-memberships-pro/includes/filters.php.
 *
 * @param string $startdate The start date for the membership.
 * @param int $user_id The ID of the user.
 * @param object $level The level that the user is purchasing.
 */
function pmprorate_pmpro_checkout_start_date_keep_startdate( $startdate, $user_id, $level ) {
	// Check if the user is going to lose a level at checkout.
	$old_level_id = pmproprorate_get_level_id_being_switched_from( $user_id, $level->id );
	if ( ! empty( $old_level_id ) ) {
		global $wpdb;

		$sqlQuery = $wpdb->prepare( "
			SELECT startdate 
			FROM {$wpdb->pmpro_memberships_users} 
			WHERE user_id = %d
			AND status = %s 
			AND membership_id = %d
			ORDER BY id DESC 
			LIMIT 1",
			$user_id,
			'active',
			$old_level_id
		);

		$old_startdate = $wpdb->get_var( $sqlQuery );

		if ( ! empty( $old_startdate ) ) {
			$startdate = "{$old_startdate}";
		}
	}

	return $startdate;
}

/**
 * When checking out with a discount code, applies proration to the message displayed to users
 */
function pmprorate_applydiscountcode_return_js( $discount_code, $discount_code_id, $level_id, $code_level ) {
	if ( empty( $code_level ) ) {
		// there was an error, so just return
		return;
	}
	
	$code_level = pmprorate_pmpro_checkout_level( $code_level );
	?>
		jQuery('#pmpro_level_cost').html('<p><?php printf( esc_html__('The %s code has been applied to your order.', 'pmpro-proration' ), '<strong>' . esc_html( $discount_code ) . '</strong>' );?></p><p><?php echo pmpro_no_quotes(pmpro_getLevelCost($code_level), array('"', "'", "\n", "\r"))?><?php echo pmpro_no_quotes(pmpro_getLevelExpiration($code_level), array('"', "'", "\n", "\r"))?></p>');
	<?php
}

/**
 * add/remove hooks in init to make sure it runs after PMPro loads
 */
function pmprorate_pmpro_init() {
	remove_filter( "pmpro_checkout_start_date", "pmpro_checkout_start_date_keep_startdate", 10, 3 );    //remove the default PMPro filter
	add_filter( "pmpro_checkout_start_date", "pmprorate_pmpro_checkout_start_date_keep_startdate", 10, 3 );    //our filter works with ANY level
	add_action( "pmpro_applydiscountcode_return_js", "pmprorate_applydiscountcode_return_js", 10, 4 );
}

add_action( 'init', 'pmprorate_pmpro_init' );

/**
 * Get the level ID that would be switched from if a particular level is purchased.
 *
 * @since TBD
 *
 * @param int $user_id The ID of the user.
 * @param int $new_level_id The ID of the level that the user is trying to switch to.
 * @return int|null The ID of the level that the user is switching from, or null if it can't be determined.
 */
function pmproprorate_get_level_id_being_switched_from( $user_id, $new_level_id ) {
	// Validate types.
	$user_id = (int)$user_id;
	$new_level_id = (int)$new_level_id;

	// If using PMPro v3.0+, check if the user has another level in the same group.
	if ( function_exists( 'pmpro_get_group_id_for_level' ) ) {
		// Get the group ID for the level that we are switching to.
		$group_id = pmpro_get_group_id_for_level( $new_level_id );
		$group    = pmpro_get_level_group( $group_id );

		// Only switching from a level if users can have multiple levels from this group at once.
		if ( ! empty( $group->allow_multiple_selections ) ) {
			return null;
		}

		// Get other levels in the group of the level that we are switching to.
		$group_level_ids = array_map( 'intval', pmpro_get_level_ids_for_group( $group_id ) );

		// Get user's current levels.
		$user_levels     = pmpro_getMembershipLevelsForUser( $user_id );
		$user_level_ids  = array_map( 'intval', wp_list_pluck( $user_levels, 'id' ) );

		// Intersect the two arrays to see if the user has another level in the same group.
		$intersect = array_intersect( $user_level_ids, $group_level_ids );

		// If the user has another level in the same group, set that as the level that we want to switch from.
		return ! empty( $intersect ) ? array_shift( $intersect ) : null;
	}

	// If using PMPro v2.x, just choose a membership level that the user currently has. They should only have one.
	$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
	return ! empty( $user_levels ) ? (int)array_shift( $user_levels )->id : null;
}

/**
 * Helper function to get the cost per day of a billing setup.
 *
 * @since TBD
 *
 * @param float $billing_amount The amount that the user is billed.
 * @param int $cycle_number The number of billing periods in a billing setup.
 * @param string $cycle_period The period of time in a billing setup.
 */
function pmprorate_get_cost_per_day( $billing_amount, $cycle_number, $cycle_period ) {
	$cycle_period_days = null;
	switch( $cycle_period ) {
		case 'Day':
			$cycle_period_days = 1;
			break;
		case 'Week':
			$cycle_period_days = 7;
			break;
		case 'Month':
			$cycle_period_days = 30;
			break;
		case 'Year':
			$cycle_period_days = 365;
			break;
	}

	return $billing_amount / ( $cycle_number * $cycle_period_days );
}

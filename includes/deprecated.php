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

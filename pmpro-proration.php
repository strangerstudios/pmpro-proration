<?php
/*
Plugin Name: Paid Memberships Pro - Proration Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-proration/
Description: Custom Prorating Code for Paid Memberships Pro
Version: .2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Function to check if a level change is a downgrade.	

	Assumes levels with smaller initial payments are
	downgrades. Change this if this is not the case on your
	site.
*/
function pmprorate_isDowngrade($old, $new)
{
	$old_level = pmpro_getLevel($old);
	$new_level = pmpro_getLevel($new);

	if($old_level->initial_payment > $new_level->initial_payment)
		$r = true;
	else
		$r = false;

	$r = apply_filters("pmpro_is_downgrade", $r, $old, $new);

	return $r;
}

/*
	Update the initial payment amount at checkout per prorating rules.
*/
function pmprorate_pmpro_checkout_level($level)
{
  	//does the user have a level already?
	if(pmpro_hasMembershipLevel())
	{
		//get current level
		global $current_user;
		$clevel = $current_user->membership_level;
		
		//downgrading?		
		if(pmprorate_isDowngrade($clevel->id, $level->id))
		{					
			//downgrade, just $0 initial payment
			$level->initial_payment = 0;
			
			//remember the old level for later
			global $pmpro_checkout_old_level;
			$pmpro_checkout_old_level = $clevel;
			
			//return now
			return $level;
		}

		//get their payment date
		$morder = new MemberOrder();
		$morder->getLastMemberOrder($current_user->ID, array('success', '', 'cancelled'));
		
		//no order?
		if(empty($morder->timestamp))
			return $level;
		
		$payment_date = strtotime(date("Y-m-d", $morder->timestamp));			
		$payment_day = intval(date("j", $morder->timestamp));
					
		//when would the next payment be			
		$next_payment_date = strtotime(date("Y-m-d", $payment_date) . " + " . $clevel->cycle_number . " " . $clevel->cycle_period);
				
		//today
		$today = current_time("timestamp");
				
		//how many days in this period
		$days_in_period = ceil(($next_payment_date - $payment_date)/3600/24);
		
		//if no days in period (next payment should have happened already) return level with no change to avoid divide by 0
		if($days_in_period <= 0)
			return $level;
						
		//how many days have passed		
		$days_passed = ceil(($today - $payment_date)/3600/24);
									
		//what percentage
		$per_passed = $days_passed / $days_in_period;		//as a % (decimal)
		$per_left = 1 - $per_passed;
		
		/*
			Now figure out how to adjust the price.
			(a) What they should pay for new level = $level->billing_amount * $per_left.
			(b) What they should have paid for current level = $clevel->billing_amount * $per_passed.
			What they need to pay = (a) + (b) - (what they already paid)
			
			If the number is negative, this would technically require a credit be given to the customer,
			but we don't currently have an easy way to do that across all gateways so we just 0 out the cost.
			
			This is the method used in the code below.
			
			An alternative calculation that comes up with the same number (but may be easier to understand) is:
			(a) What they should pay for new level = $level->billing_amount * $per_left.
			(b) Their credit for cancelling early = $clevel->billing_amount * $per_left.
			What they need to pay = (a) - (b)
		*/
		$new_level_cost = $level->billing_amount * $per_left;
		$old_level_cost = $clevel->billing_amount * $per_passed;
		
		$level->initial_payment = min($level->initial_payment, round($new_level_cost + $old_level_cost - $morder->total, 2));
		
		//just in case we have a negative payment
		if($level->initial_payment < 0)
			$level->initial_payment = 0;								
	}
		
	return $level;
}
add_filter("pmpro_checkout_level", "pmprorate_pmpro_checkout_level");

/*
	Keep the same payment date.
*/
function pmprorate_pmpro_profile_start_date($startdate, $order)
{
	if(pmpro_hasMembershipLevel())
	{
		// Fetch the date of the most recent order for current user
		if(!empty($order->user_id))
			$last_recurring_order_datetime = pmprorate_pmpro_getLastRecurringOrderDatetime($order->user_id);
		else
			$last_recurring_order_datetime = pmprorate_pmpro_getLastRecurringOrderDatetime();
		
		if(!empty($last_recurring_order_datetime))
			$startdate = date("Y-m-d", strtotime(date("Y-m-d", $last_recurring_order_datetime) . " + " . $order->BillingFrequency . " " . $order->BillingPeriod)) . "T0:0:0";
	}

	return $startdate;
}
add_filter("pmpro_profile_start_date", "pmprorate_pmpro_profile_start_date", 10, 2);

/*
	Get the date of the last recurring order.
*/
function pmprorate_pmpro_getLastRecurringOrderDatetime($user_id = NULL)
{
	global $wpdb;

	// Fetch most recent order
	$lastorder = new MemberOrder();
	$lastorder->getLastMemberOrder($user_id);

	//no order? no current membership
	if(empty($lastorder))
		return false;

	//check gateways
	if($lastorder->gateway == "stripe")
	{
		//get subscription from Stripe API and look for current_period_start
		$gateway = new PMProGateway_stripe();
		$subscription = $gateway->getSubscription($lastorder);

		if(!empty($subscription) && !empty($subscription->current_period_start))
			return $subscription->current_period_start;
	}

	//check if this order is recurring and find the latest recurring order
	$check12 = 0;
	while(!pmprorate_pmpro_isOrderRecurring($lastorder) && $check12++ < 12)
	{
		$new_order_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $lastorder->user_id . "' AND status IN('', 'success') AND id < '" . $lastorder->id . "' ORDER BY timestamp DESC LIMIT 1");
		if(!empty($new_order_id))
			$lastorder = new MemberOrder($new_order_id);
		
		if(empty($lastorder) || empty($lastorder->id))
			return false;	//found no recurring orders for this member
	}

	//use latest recurring PMPro order to figure out next payment date
	if(!empty($lastorder) && !empty($lastorder->timestamp))
		return $lastorder->timestamp;

	return false;
}

function pmprorate_pmpro_isOrderRecurring( $order, $test_checkout = false ) {
	global $wpdb;
 
	//must have a subscription_transaction_id
	if( empty( $order->subscription_transaction_id ) ) {
		return false;
	}
 
	//check that we aren't processing at checkout
	if( $test_checkout && ! empty( $_REQUEST['submit-checkout'] ) )
		return false;
	
	//check for earlier orders with the same gateway, user_id, membership_id, and subscription_transaction_id
	$sqlQuery = "SELECT id FROM $wpdb->pmpro_membership_orders WHERE
								gateway = '" . esc_sql( $order->gateway ) . "' AND
								gateway_environment = '" . esc_sql( $order->gateway_environment ) . "' AND
								user_id = '" . esc_sql( $order->user_id) . "' AND
								membership_id = '" . esc_sql( $order->membership_id ) . "' AND
								subscription_transaction_id = '" . esc_sql( $order->subscription_transaction_id ) . "' AND
								timestamp < '" . date("Y-m-d", $order->timestamp) . "' ";
	if( ! empty( $order->id ) ) {
		$sqlQuery .= " AND id <> '" . esc_sql( $order->id ) . "' ";
	}
 
	$sqlQuery .= "LIMIT 1";
	$earlier_order = $wpdb->get_var( $sqlQuery );
		
	if ( empty( $earlier_order ) ) {
		return false;
	}
 
	//must be recurring
	return true;
}

/*
  Keep your old startdate.
  Updated from what's in paid-memberships-pro/includes/filters.php to run if the user has ANY level
*/
function pmprorate_pmpro_checkout_start_date_keep_startdate($startdate, $user_id, $level)
{				
	if(pmpro_hasMembershipLevel())  //<-- the line that was changed
	{
		global $wpdb;
		$sqlQuery = "SELECT startdate FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . esc_sql($user_id) . "' AND status = 'active' ORDER BY id DESC LIMIT 1";		
		$old_startdate = $wpdb->get_var($sqlQuery);
		
		if(!empty($old_startdate))
			$startdate = "'" . $old_startdate . "'";		
	}
	
	return $startdate;
}

//add/remove hooks in init to make sure it runs after PMPro loads
function pmprorate_pmpro_init()
{
	remove_filter("pmpro_checkout_start_date", "pmpro_checkout_start_date_keep_startdate", 10, 3);	//remove the default PMPro filter
	add_filter("pmpro_checkout_start_date", "pmprorate_pmpro_checkout_start_date_keep_startdate", 10, 3);	//our filter works with ANY level
}
add_action('init', 'pmprorate_pmpro_init');

/*
	After checkout, if the user downgraded, then revert to the old level and remember to change them to the new level later.
*/
function pmprorate_pmpro_after_checkout($user_id)
{
	global $pmpro_checkout_old_level, $wpdb;
	if(!empty($pmpro_checkout_old_level) && !empty($pmpro_checkout_old_level->next_payment))
	{
		$new_level = pmpro_getMembershipLevelForUser($user_id);
		
		//remember to update to this level later
		update_user_meta($user_id, "pmpro_change_to_level", array("date"=>$pmpro_checkout_old_level->next_payment, "level"=>$new_level->id));
		
		//change their membership level
		$wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET membership_id = '" . $pmpro_checkout_old_level->id . "' WHERE membership_id = '" . $new_level->id . "' AND user_id = '" . $user_id . "' AND status = 'active'");
	}
	else
		delete_user_meta($user_id, "pmpro_change_to_level");
}
add_filter('pmpro_after_checkout', 'pmprorate_pmpro_after_checkout');

/*
	Update confirmation message.
*/
function pmprorate_pmpro_confirmation_message($message, $invoice)
{	
	if(!empty($invoice) && !empty($invoice->user_id))
	{	
		$downgrading = get_user_meta($invoice->user_id, "pmpro_change_to_level", true);
			
		if(!empty($downgrading))
		{
			$dlevel = pmpro_getLevel($downgrading['level']);
		
			$message .= "<p>You will be downgraded to " . $dlevel->name . " on " . date(get_option("date_format"), strtotime($downgrading['date'], current_time('timestamp'))) . ".";
		}
	}
	
	return $message;
}
add_filter("pmpro_confirmation_message", "pmprorate_pmpro_confirmation_message", 10, 2);

/*
	Update account page.
*/
function pmprorate_the_content($content)
{
	global $current_user, $pmpro_pages;
		
	if(is_user_logged_in() && is_page($pmpro_pages['account']))
	{
		$downgrading = get_user_meta($current_user->ID, "pmpro_change_to_level", true);
				
		if(!empty($downgrading))
		{
			$downgrade_message = "<p><strong>Important Note:</strong> You will be downgraded to " . $downgrading['level']->name . " on " . date(get_option("date_format"), strtotime($downgrading['date'], current_time('timestamp'))) . ".";
			
			$content = $downgrade_message . $content;
		}
	}

	return $content;
}
add_filter("the_content", "pmprorate_the_content");

/*
	Check for level changes daily.
*/
function pmproproate_daily_check_for_membership_changes()
{
	global $wpdb;
	
	//make sure we only run once a day
	$today = date("Y-m-d", current_time('timestamp'));
		
	//get all users with scheduled level changes
	$level_changes = $wpdb->get_col("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmpro_change_to_level'");
	
	if(empty($level_changes))
		return;
		
	foreach($level_changes as $user_id)
	{
		//today?
		$change = get_user_meta($user_id, 'pmpro_change_to_level', true);
				
		if(!empty($change) && !empty($change['date']) && !empty($change['level']) && $change['date'] <= $today)
		{
			//get user's current level
			$clevel = pmpro_getMembershipLevelForUser($user_id);
		
			//change back
			if(!empty($clevel))
				$wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET membership_id = '" . $change['level'] . "' WHERE membership_id = '" . $clevel->id . "' AND user_id = '" . $user_id . "' AND status = 'active'");
				
			//delete user meta
			delete_user_meta($user_id, 'pmpro_change_to_level');
		}
	}
}
//hook to run when pmpro_cron_expire_memberships does
add_action('pmpro_cron_expire_memberships', 'pmproproate_daily_check_for_membership_changes');

/*
Function to add links to the plugin row meta
*/
function pmproproate_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-proration.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/proration-prorate-membership/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproproate_plugin_row_meta', 10, 2);

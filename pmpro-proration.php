<?php
/*
Plugin Name: Paid Memberships Pro - Proration Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-proration/
Description: Custom Prorating Code for Paid Memberships Pro
Version: .3.1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/**
 * Load the languages folder for translations.
 */
function pmprorate_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-proration', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmprorate_load_plugin_text_domain' );

/** 
 * Function to check if a level change is a downgrade.	
 *
 * Assumes levels with smaller initial payments are
 * downgrades. Change this if this is not the case on your
 * site.
 */
function pmprorate_isDowngrade( $old, $new ) {
	$old_level = is_object( $old ) ? $old : pmpro_getLevel( $old );
	$new_level = is_object( $new ) ? $new : pmpro_getLevel( $new );

	if ( $old_level->initial_payment > $new_level->initial_payment ) {
		$r = true;
	} else {
		$r = false;
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
 * @todo : TODO MMPU: getLastMemberOrder and pmpro_next_payment need to check based on level.
 */
function pmprorate_pmpro_checkout_level( $level ) {	
	// Bail if no level.
	if ( empty( $level ) ) {
		return $level;
	}
	
	// can only prorate if they already have a level
	if ( pmpro_hasMembershipLevel() ) {
		global $current_user;
		$clevel = $current_user->membership_level;
		
		$morder = new MemberOrder();
		$morder->getLastMemberOrder( $current_user->ID, array( 'success', '', 'cancelled' ) );

		// no prorating needed if they don't have an order (were given the level by an admin/etc)
		if ( empty( $morder->timestamp ) ) {
			return $level;
		}
		
		// different prorating rules if they are downgrading, upgrading with same billing period, or upgrading with a different billing period
		if ( pmprorate_isDowngrade( $clevel, $level ) ) {
			/*
				Downgrade rule in a nutshell:
				1. Charge $0 now.
				2. Allow their current membership to expire on their next payment date.
				3. Setup new subscription to start billing on that date.
				4. Other code in this plugin handles changing the user's level on the future date.
			*/
			$level->initial_payment = 0;
			global $pmpro_checkout_old_level;
			$pmpro_checkout_old_level = $clevel;
			$pmpro_checkout_old_level->next_payment = pmprorate_trim_timestamp( pmpro_next_payment( $current_user->ID ) );
			
			//make sure payment date stays the same
			add_filter( 'pmpro_profile_start_date', 'pmprorate_set_startdate_to_next_payment_date', 10, 2 );		
		} elseif( pmprorate_have_same_payment_period( $clevel, $level ) ) {
			/*
				Upgrade with same billing period in a nutshell:
				1. Calculate the initial payment to cover the remaining time in the current pay period.
				2. Setup subscription to start on next payment date at the new rate.
			*/
			$payment_date = pmprorate_trim_timestamp( $morder->timestamp );
			$next_payment_date = pmprorate_trim_timestamp( pmpro_next_payment( $current_user->ID ) );
			$today = pmprorate_trim_timestamp( current_time( 'timestamp' ) );

			$days_in_period = ceil( ( $next_payment_date - $payment_date ) / 3600 / 24 );

			//if no days in period (next payment should have happened already) return level with no change to avoid divide by 0
			if ( $days_in_period <= 0 ) {
				return $level;
			}
			
			$days_passed = ceil( ( $today - $payment_date ) / 3600 / 24 );
			$per_passed = $days_passed / $days_in_period;        //as a % (decimal)
			$per_left   = max( 1 - $per_passed, 0 );
			
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
			$level->initial_payment = min( $level->initial_payment, round( $new_level_cost + $old_level_cost - $morder->subtotal, 2 ) );

			//just in case we have a negative payment
			if ( $level->initial_payment < 0 ) {
				$level->initial_payment = 0;
			}
			
			//make sure payment date stays the same
			add_filter( 'pmpro_profile_start_date', 'pmprorate_set_startdate_to_next_payment_date', 10, 2 );			
		} else {
			/*
				Upgrade with different payment periods in a nutshell:
				1. Apply a credit to the initial payment based on the partial period of their old level.
				2. New subscription starts today with the initial payment and will renew one period from now based on the new level.
			*/
			$payment_date = pmprorate_trim_timestamp( $morder->timestamp );
			$next_payment_date = pmprorate_trim_timestamp( pmpro_next_payment( $current_user->ID ) );
			$today = pmprorate_trim_timestamp( current_time( 'timestamp' ) );

			$days_in_period = ceil( ( $next_payment_date - $payment_date ) / 3600 / 24 );

			//if no days in period (next payment should have happened already) return level with no change to avoid divide by 0
			if ( $days_in_period <= 0 ) {
				return $level;
			}
			
			$days_passed = ceil( ( $today - $payment_date ) / 3600 / 24 );
			$per_passed  = $days_passed / $days_in_period;        //as a % (decimal)			
			$per_left    = max( 1 - $per_passed, 0 );
			$credit      = $morder->subtotal * $per_left;			

			$level->initial_payment = round( $level->initial_payment - $credit, 2 );

			//just in case we have a negative payment
			if ( $level->initial_payment < 0 ) {
				$level->initial_payment = 0;
			}
		}		
	}

	return $level;
}

add_filter( "pmpro_checkout_level", "pmprorate_pmpro_checkout_level", 10, 1 );

/**
 * Set start date to the next payment date expected.
 */
function pmprorate_set_startdate_to_next_payment_date( $startdate, $order ) {
	global $current_user;
	
	//use APIs to be more specific
	if( $order->gateway == 'stripe' ) {
		remove_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);
		add_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);
	} elseif( $order->gateway == 'paypalexpress' ) {
		remove_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);
		add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);
	}
	
	//TODO MMPU: Needs to get next payment for this level in particular
	$next_payment_date = pmpro_next_payment( $current_user->ID );
		
	if( !empty( $next_payment_date ) )
		$startdate = date( "Y-m-d", $next_payment_date ) . "T0:0:0";
	
	return $startdate;
}

/**
 * Keep your old startdate.
 * Updated from what's in paid-memberships-pro/includes/filters.php to run if the user has ANY level
 */
function pmprorate_pmpro_checkout_start_date_keep_startdate( $startdate, $user_id, $level ) {
	if ( pmpro_hasMembershipLevel() )  //<-- the line that was changed
	{
		global $wpdb;

		$sqlQuery = $wpdb->prepare( "
			SELECT startdate 
			FROM {$wpdb->pmpro_memberships_users} 
			WHERE user_id = %d AND status = %s 
			ORDER BY id DESC 
			LIMIT 1",
			$user_id,
			'active'
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
 * After checkout, if the user downgraded, then revert to the old level and remember to change them to the new level later.
 */
function pmprorate_pmpro_after_checkout( $user_id ) {
	global $pmpro_checkout_old_level, $wpdb;
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
			$clevel = pmpro_getMembershipLevelForUser( $user_id );

			//change back
			if ( ! empty( $clevel ) ) {

				$wpdb->update( $wpdb->pmpro_memberships_users, array( 'membership_id' => $change['level'] ), array( 'membership_id' => $clevel->id, 'user_id' => $user_id, 'status' => 'active') );

			}

			//delete user meta
			delete_user_meta( $user_id, 'pmpro_change_to_level' );
		}
	}
}

//hook to run when pmpro_cron_expire_memberships does
add_action( 'pmpro_cron_expire_memberships', 'pmproproate_daily_check_for_membership_changes' );

/**
 * Mark the plugin as MMPU-incompatible.
 */
  function pmproprorate_mmpu_incompatible_add_ons( $incompatible ) {
	$incompatible[] = 'PMPro Prorations Add On';
	return $incompatible;
}
add_filter( 'pmpro_mmpu_incompatible_add_ons', 'pmproprorate_mmpu_incompatible_add_ons' );

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

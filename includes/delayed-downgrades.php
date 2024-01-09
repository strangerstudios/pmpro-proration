<?php

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

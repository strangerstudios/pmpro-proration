<?php

/**
 * The PMPro Prorations downgrade object.
 *
 * @since TBD
 */
class PMProrate_Downgrade {
	/**
	 * The ID of the group entry.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * The user ID for the downgrade.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * The level ID that the user will be downgraded from.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	protected $original_level_id;

	/**
	 * The level ID that the user will be downgraded to.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	protected $new_level_id;

	/**
	 * The ID of the order containing the downgrade asynchronous checkout data.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	protected $downgrade_order_id;

	/**
	 * The status of this group.
	 *
	 * 'pending' => The downgrade has not yet occured.
	 * 'downgraded_on_renewal' => The downgrade has been completed on a renwal payment.
	 * 'downgraded_on_expiration' => The downgrade has been completed when the original level expired.
	 * 'lost_original_level' => The user lost the original level before the downgrade could be completed.
	 * 'error' => There was an error processing the downgrade.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected $status;

	/**
	 * Get a downgrade object by ID.
	 *
	 * @since TBD
	 *
	 * @param int $downgrade The group ID to populate.
	 */
	public function __construct( $downgrade ) {
		global $wpdb;

		if ( is_int( $downgrade ) ) {
			$data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->pmprorate_downgrades} WHERE id = %d",
					$downgrade
				)
			);

			if ( ! empty( $data ) ) {
				$this->id                 = (int)$data->id;
				$this->user_id            = (int)$data->user_id;
				$this->original_level_id  = (int)$data->original_level_id;
				$this->new_level_id       = (int)$data->new_level_id;
				$this->downgrade_order_id = (int)$data->downgrade_order_id;
				$this->status             = $data->status;
			}
		}
	}

	/**
	 * Get the list of downgrades based on query arguments.
	 *
	 * @since TBD
	 *
	 * @param array $args The query arguments to use to retrieve downgrades.
	 *
	 * @return PMProrate_Downgrade[] The list of groups.
	 */
	public static function get_downgrades( $args = array() ) {
		global $wpdb;

		$sql_query = "SELECT id FROM {$wpdb->pmprorate_downgrades}";

		$prepared = array();
		$where    = array();
		$orderby  = isset( $args['orderby'] ) ? $args['orderby'] : '`id` DESC';
		$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : 100;

		// Detect unsupported orderby usage.
		if ( $orderby !== preg_replace( '/[^a-zA-Z0-9\s,`]/', ' ', $orderby ) ) {
			return [];
		}

		// Filter by ID.
		if ( isset( $args['id'] ) ) {
			$where[]    = 'id = %d';
			$prepared[] = $args['id'];
		}

		// Filter by user ID.
		if ( isset( $args['user_id'] ) ) {
			$where[]    = 'user_id = %d';
			$prepared[] = $args['user_id'];
		}

		// Filter by original level ID.
		if ( isset( $args['original_level_id'] ) ) {
			$where[]    = 'original_level_id = %d';
			$prepared[] = $args['original_level_id'];
		}

		// Filter by new level ID.
		if ( isset( $args['new_level_id'] ) ) {
			$where[]    = 'new_level_id = %d';
			$prepared[] = $args['new_level_id'];
		}

		// Filter by downgrade order ID.
		if ( isset( $args['downgrade_order_id'] ) ) {
			$where[]    = 'downgrade_order_id = %d';
			$prepared[] = $args['downgrade_order_id'];
		}

		// Filter by status.
		if ( isset( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = $args['status'];
		}

		// Maybe filter the data.
		if ( ! empty( $where ) ) {
			$sql_query .= ' WHERE ' . implode( ' AND ', $where );
		}

		// Add the order and limit.
		$sql_query .= " ORDER BY {$orderby} LIMIT {$limit}";

		// Prepare the query.
		if ( ! empty( $prepared ) ) {
			$sql_query = $wpdb->prepare( $sql_query, $prepared );
		}

		// Get the data.
		$downgrade_ids = $wpdb->get_col( $sql_query );
		if ( empty( $downgrade_ids ) ) {
			return array();
		}

		// Return the list of groups.
		$downgrades = array();
		foreach ( $downgrade_ids as $downgrade_id ) {
			$downgrade = new self( (int)$downgrade_id );
			if ( ! empty( $downgrade->id ) ) {
				$downgrades[] = $downgrade;
			}
		}
		return $downgrades;
	}

	/**
	 * Create a new downgrade.
	 *
	 * @since TBD
	 *
	 * @param int $user_id The user ID to be downgraded.
	 * @param int $original_level_id The level ID that the user will be downgraded from.
	 * @param int $new_level_id The level ID that the user will be downgraded to.
	 * @param int $downgrade_order_id The ID of the order containing the downgrade asynchronous checkout data.
	 *
	 * @return bool|PMProrate_Downgrade The new downgrade object or false if the downgrade could not be created.
	 */
	public static function create( $user_id, $original_level_id, $new_level_id, $downgrade_order_id ) {
		global $wpdb;

		// Validate the passed data.
		if (
			! is_numeric( $user_id ) || (int) $user_id <= 0 ||
			! is_numeric( $original_level_id ) || (int) $original_level_id <= 0 ||
			! is_numeric( $new_level_id ) || (int) $new_level_id < 0 ||
			! is_numeric( $downgrade_order_id ) || (int) $downgrade_order_id <= 0
		) {
			return false;
		}

		// Create the group in the database.
		$wpdb->insert(
			$wpdb->pmprorate_downgrades,
			array(
				'user_id'            => $user_id,
				'original_level_id'  => $original_level_id,
				'new_level_id'       => $new_level_id,
				'downgrade_order_id' => $downgrade_order_id,
				'status'             => 'pending',
			),
		);

		// Check if the insert failed. This could be the case if the entry already existed.
		if ( empty( $wpdb->insert_id ) ) {
			return false;
		}

		// Return the new group object.
		return new self( $wpdb->insert_id );
	}

	/**
	 * Magic getter to retrieve protected properties.
	 *
	 * @since TBD
	 *
	 * @param string $name The name of the property to retrieve.
	 * @return mixed The value of the property.
	 */
	public function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->$name;
		}
	}

	/**
	 * Magic isset to check protected properties.
	 *
	 * @since TBD
	 *
	 * @param string $name The name of the property to check.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name ) {
		if ( property_exists( $this, $name ) ) {
			return isset( $this->$name );
		}
		return false;
	}

	/**
	 * Update the downgrade status.
	 *
	 * @since TBD
	 *
	 * @param string $status The new status.
	 */
	public function update_status( $status ) {
		global $wpdb;

		// Make sure that the status is valid.
		if ( ! in_array( $status, array( 'pending', 'downgraded_on_renewal', 'downgraded_on_expiration', 'lost_original_level', 'error' ) ) ) {
			return;
		}

		// Update the status.
		$this->status = $status;
		$wpdb->update(
			$wpdb->pmprorate_downgrades,
			array(
				'status' => $this->status,
			),
			array(
				'id' => $this->id,
			),
		);

		// If the order is now in an error state, send an email to the admin.
		if ( $this->status === 'error' ) {
			$data = array(
				'display_name' => get_userdata( $this->user_id )->display_name,
				'sitename' => get_bloginfo( 'name' ),
				'edit_member_downgrade_url' => admin_url( 'admin.php?page=pmpro-member&user_id=' . $this->user_id . '&pmpro_member_edit_panel=pmprorate-downgrades' ),
			);
			$email_admin = new PMProEmail();
			$email_admin->template = 'delayed_downgrade_error_admin';
			$email_admin->email = get_bloginfo( 'admin_email' );
			$email_admin->data = $data;
			$email_admin->sendEmail();
		}
	}

	/**
	 * Process the downgrade.
	 *
	 * @since TBD
	 *
	 * @param MemberOrder|null $renewal_order The renewal order object if the downgrade is being processed on a renewal payment. Null if the downgrade is being processed on expiration.
	 *
	 * @return bool Whether the downgrade was processed.
	 */
	public function process( $renewal_order = null ) {
		global $pmpro_level;

		// Get the order witih the downgrade metadata.
		$downgrade_order = MemberOrder::get_order( $this->downgrade_order_id );
		if ( empty( $downgrade_order->id ) ) {
			// If we can't get the order with the downgrade metadata, bail.
			$this->update_status( 'error' );
			return false;
		}

		// We are downgrading. Set the $_REQUEST variables for the checkout that we are going to complete asynchronously.
		pmpro_pull_checkout_data_from_order( $downgrade_order );

		// Make sure that we have data for the level that we are downgrading to.
		if ( empty( $pmpro_level ) || empty( $pmpro_level->id ) ) {
			$this->update_status( 'error' );
			return false;
		}

		// If we are processing a renewal order, put it in the place of the downgrade order so that the checkout is completed for the renewal order.
		if ( ! empty( $renewal_order ) ) {
			$downgrade_order = $renewal_order;
		}

		// Set the memberhsip ID for the subscription and all orders in that subscription to the level that we are downgrading to.
		$subscription = $downgrade_order->get_subscription();
		if ( ! empty( $subscription ) ) {
			// Update the subscription.
			$subscription->set( 'membership_level_id', $pmpro_level->id );
			$subscription->save();

			// Update the orders in the subscription.
			$subscription_orders = $subscription->get_orders();
			foreach( $subscription_orders as $subscription_order ) {
				$subscription_order->membership_id = $pmpro_level->id;
				$subscription_order->saveOrder();
			}

			// Make sure that the order object is updated with the new membership level ID.
			$downgrade_order->membership_id = $pmpro_level->id;
		} else {
			// If this is not a subscription, just update the membership level ID on the order.
			$downgrade_order->membership_id = $pmpro_level->id;
			$downgrade_order->saveOrder();
		}

		// Prevent checkout emails from being sent.
		add_filter( 'pmpro_send_checkout_emails', '__return_false' );

		// Complete the checkout asynchronously.
		if (! pmpro_complete_async_checkout( $downgrade_order ) ) {
			// If the checkout failed, bail.
			$this->update_status( 'error' );
			return false;
		}

		// Remove the filter that prevents checkout emails from being sent.
		remove_filter( 'pmpro_send_checkout_emails', '__return_false' );

		// Mark this downgrade as completed.
		$this->update_status( 'downgraded_on_' . ( ! empty( $renewal_order ) ? 'renewal' : 'expiration' ) );
		
		// Prepare emails stating that the downgrade has been processed.
		$user = get_userdata( $this->user_id );
		$data = array(
			'display_name' => $user->display_name,
			'sitename' => get_bloginfo( 'name' ),
			'login_url' => wp_login_url(),
			'edit_member_downgrade_url' => admin_url( 'admin.php?page=pmpro-member&user_id=' . $user_id . '&pmpro_member_edit_panel=pmprorate-downgrades' ),
		);

		// Send email to user.
		$data['header_name'] = $user->display_name;
		$email = new PMProEmail();
		$email->template = 'delayed_downgrade_processed';
		$email->email = $user->user_email;
		$email->data = $data;
		$email->sendEmail();

		// Send email to the site admin.
		unset( $data['header_name'] );
		$email_admin = new PMProEmail();
		$email_admin->template = 'delayed_downgrade_processed_admin';
		$email_admin->email = get_bloginfo( 'admin_email' );
		$email_admin->data = $data;
		$email_admin->sendEmail();
		}

	/**
	 * Get downgrade text to show to the user.
	 *
	 * @since TBD
	 *
	 * @return string|false The downgrade text or false if the downgrade text could not be retrieved.
	 */
	public function get_downgrade_text() {
		// Get the name of the level that is being downgraded to.
		$downgrading_to_level = pmpro_getLevel( $this->new_level_id );
		$downgrading_to_level_name = empty( $downgrading_to_level ) ? sprintf( __( '[deleted level #%d]', 'pmpro-prorate' ), $this->new_level_id ) : $downgrading_to_level->name;

		// Get the order for this downgrade.
		$order = MemberOrder::get_order( $this->downgrade_order_id );
		if ( empty( $order ) ) {
			// If we don't have an order, then we're not going to be able to process the downgrade.
			$this->update_status( 'error' );
			return false;
		}

		// Get the next payment date for the user's current subscription.
		$subscription = $order->get_subscription();
		$subscription_next_payment_date = empty( $subscription ) ? null : $subscription->get_next_payment_date();

		// Get the expiration date for the user's current membership.
		$level = pmpro_getSpecificMembershipLevelForUser( $order->user_id, $order->membership_id );
		$expiration_date = ( empty( $level ) || empty( $level->enddate ) ) ? null : $level->enddate;

		// Generate the downgrade text and return.
		if ( empty( $subscription_next_payment_date ) && empty( $expiration_date ) ) {
			// If we don't have a next payment date for the subscription or an expiration date for the membership, then we don't know when the downgrade will be processed.
			return sprintf( __( 'Downgrading to %s.', 'pmpro-prorate' ), $downgrading_to_level_name );
		} else {
			// We have a date for the downgrade. Use the earlier of the next payment date or the expiration date.
			$downgrade_date = ( empty( $expiration_date) || $subscription_next_payment_date < $expiration_date ) ? $subscription->get_next_payment_date( 'date_format' ) : date_i18n( get_option( 'date_format' ), $expiration_date );
			return sprintf( __( 'Downgrading to %s on %s.', 'pmpro-prorate' ), $downgrading_to_level_name, $downgrade_date );
		}
	}
}

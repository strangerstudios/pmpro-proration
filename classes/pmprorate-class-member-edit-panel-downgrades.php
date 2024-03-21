<?php

class PMProrate_Member_Edit_Panel_Downgrades extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug = 'pmprorate-downgrades';
		$this->title = esc_html__( 'Delayed Downgrades', 'pmpro-prorate' );
	}

	/**
	 * Display the panel contents.
	 */
	protected function display_panel_contents() {
		// Get the user being edited.
		$user = self::get_user();

		// Get all downgrades for this user.
		$downgrade_query_args = array(
			'user_id' => $user->ID,
		);
		$downgrades = PMProrate_Downgrade::get_downgrades( $downgrade_query_args );

		// If there are no downgrades, display a message and return.
		if ( empty( $downgrades ) ) {
			?>
			<p><?php esc_html_e( 'There are no downgrades for this user.', 'pmpro-prorate' ); ?></p>
			<?php
			return;
		}

		// Display the downgrades in a table.
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'pmpro-prorate' ); ?></th>
					<th><?php esc_html_e( 'Downgrading From', 'pmpro-prorate' ); ?></th>
					<th><?php esc_html_e( 'Downgrading To', 'pmpro-prorate' ); ?></th>
					<th><?php esc_html_e( 'Order', 'pmpro-prorate' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pmpro-prorate' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $downgrades as $downgrade ) {
					// Get the level names to display.
					$downgrading_from_level = pmpro_getLevel( $downgrade->original_level_id );
					$downgrading_from_level_name = empty( $downgrading_from_level ) ? sprintf( esc_html__( '[deleted level #%d]', 'pmpro-prorate' ), $downgrade->original_level_id ) : $downgrading_from_level->name;
					$downgrading_to_level = pmpro_getLevel( $downgrade->new_level_id );
					$downgrading_to_level_name = empty( $downgrading_to_level ) ? sprintf( esc_html__( '[deleted level #%d]', 'pmpro-prorate' ), $downgrade->new_level_id ) : $downgrading_to_level->name;

					// Get the order object and link.
					$downgrade_order = new MemberOrder( $downgrade->downgrade_order_id );
					$downgrade_order_link = add_query_arg( array( 'page' => 'pmpro-orders', 'order' => $downgrade->downgrade_order_id ), admin_url( 'admin.php' ) );

					// Get the status text and class to show.
					switch ( $downgrade->status ) {
						case 'pending':
							$status_text = $downgrade->get_downgrade_text();
							$status_class = 'pmpro_tag pmpro_tag-has_icon pmpro_tag-alert';
							break;
						case 'downgraded_on_renewal':
							$status_text = esc_html__( 'Processed on renewal', 'pmpro-prorate' );
							$status_class = 'pmpro_tag pmpro_tag-has_icon pmpro_tag-success';
							break;
						case 'downgraded_on_expiration':
							$status_text = esc_html__( 'Processed on expiration', 'pmpro-prorate' );
							$status_class = 'pmpro_tag pmpro_tag-has_icon pmpro_tag-success';
							break;
						case 'lost_original_level':
							$status_text = esc_html__( 'Lost original level', 'pmpro-prorate' );
							$status_class = 'pmpro_tag pmpro_tag-has_icon pmpro_tag-error';
							break;
						case 'error':
							$status_text = esc_html__( 'Error', 'pmpro-prorate' );
							$status_class = 'pmpro_tag pmpro_tag-has_icon pmpro_tag-error';
							break;
					}
					?>
					<tr>
						<td><?php echo esc_html( $downgrade->id ); ?></td>
						<td><?php echo esc_html( $downgrading_from_level_name ); ?></td>
						<td><?php echo esc_html( $downgrading_to_level_name ); ?></td>
						<td><a href="<?php echo esc_url( $downgrade_order_link ); ?>"><?php echo esc_html( $downgrade_order->code ); ?></a></td>
						<td><span class="pmpro_downgrade_status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}
}

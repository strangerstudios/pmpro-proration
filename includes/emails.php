<?php
/**
 * Add proration-specific templates to the email templates.
 *
 * @since 1.0
 *
 * @param array $templates that can be edited.
 * @return array $templates that can be edited.
 */
function pmprorate_template_callback( $templates ) {
	$templates['delayed_downgrade_scheduled'] = array(
		'subject' => esc_html( sprintf( __( 'Your downgrade has been scheduled at %s', 'pmpro-prorate' ), get_option( 'blogname' ) ) ),
		'description' => esc_html__( 'Proration Downgrade Scheduled', 'pmpro-prorate' ),
		'body' => pmprorate_get_default_delayed_downgrade_scheduled_email_body(),
		'help_text' => esc_html__( 'This email is sent when a membership downgrade is scheduled. The !!pmprorate_downgrade_text!! placeholder variable can be used to display the details of the downgrade.', 'pmpro-prorate' ),
	);
	$templates['delayed_downgrade_scheduled_admin'] = array(
        'subject' => esc_html( sprintf( __( 'A downgrade has been scheduled at %s', 'pmpro-prorate' ), get_option( 'blogname' ) ) ),
        'description' => esc_html__( 'Proration Downgrade Scheduled (Admin)', 'pmpro-prorate' ),
        'body' => pmprorate_get_default_delayed_downgrade_scheduled_admin_email_body(),
        'help_text' => esc_html__( 'This email is sent when a membership downgrade is scheduled. The !!edit_member_downgrade_url!! placeholder variable can be used to show a link to the downgrades list.', 'pmpro-prorate' ),
    );
    $templates['delayed_downgrade_processed'] = array(
        'subject' => esc_html( sprintf( __( 'Your downgrade has been processed at %s', 'pmpro-prorate' ), get_option( 'blogname' ) ) ),
        'description' => esc_html__( 'Proration Downgrade Processed', 'pmpro-prorate' ),
        'body' => pmprorate_get_default_delayed_downgrade_processed_email_body(),
        'help_text' => esc_html__( 'This email is sent when a membership downgrade is processed.', 'pmpro-prorate' ),
    );
    $templates['delayed_downgrade_processed_admin'] = array(
        'subject' => esc_html( sprintf( __( 'A downgrade has been processed at %s', 'pmpro-prorate' ), get_option( 'blogname' ) ) ),
        'description' => esc_html__( 'Proration Downgrade Processed (Admin)', 'pmpro-prorate' ),
        'body' => pmprorate_get_default_delayed_downgrade_processed_admin_email_body(),
        'help_text' => esc_html__( 'This email is sent when a membership downgrade is processed. The !!edit_member_downgrade_url!! placeholder variable can be used to show a link to the downgrades list.', 'pmpro-prorate' ),
    );
    $templates['delayed_downgrade_error_admin'] = array(
        'subject' => esc_html( sprintf( __( 'There was an error processing a downgrade at %s', 'pmpro-prorate' ), get_option( 'blogname' ) ) ),
        'description' => esc_html__( 'Proration Downgrade Error (Admin)', 'pmpro-prorate' ),
        'body' => pmprorate_get_default_delayed_downgrade_error_admin_email_body(),
        'help_text' => esc_html__( 'This email is sent when there is an error processing a membership downgrade. The !!edit_member_downgrade_url!! placeholder variable can be used to show a link to the downgrades list.', 'pmpro-prorate' ),
    );
	
	return $templates;
}
add_filter( 'pmproet_templates', 'pmprorate_template_callback');

/**
 * Default email content for the delayed_downgrade_scheduled email template.
 *
 * @since 1.0
 *
 * @return string
 */
function pmprorate_get_default_delayed_downgrade_scheduled_email_body() {
	ob_start();
    ?>
    <p>!!pmprorate_downgrade_text!!</p>
    <p><?php esc_html_e( 'Log in to view your account here: !!login_url!!', 'pmpro-prorate' ); ?></p>
    <?php
	$body = ob_get_contents();
	ob_end_clean();
	return $body;
}

/**
 * Default email content for the delayed_downgrade_scheduled_admin email template.
 *
 * @since 1.0
 *
 * @return string
 */
function pmprorate_get_default_delayed_downgrade_scheduled_admin_email_body() {
    ob_start();
    ?>
    <p><?php esc_html_e( 'A downgrade for !!display_name!! has been scheduled at !!sitename!!.'); ?></p>
    <p><?php esc_html_e( "View the user's downgrade information here: !!edit_member_downgrade_url!!", 'pmpro-prorate' ); ?></p>
    <?php
    $body = ob_get_contents();
    ob_end_clean();
    return $body;
}

/**
 * Default email content for the delayed_downgrade_processed email template.
 *
 * @since 1.0
 *
 * @return string
 */
function pmprorate_get_default_delayed_downgrade_processed_email_body() {
    ob_start();
    ?>
    <p><?php esc_html_e( 'Your downgrade has been successfully processed.', 'pmpro-prorate' ); ?></p>
    <p><?php esc_html_e( 'Log in to view your account here: !!login_url!!', 'pmpro-prorate' ); ?></p>
    <?php
    $body = ob_get_contents();
    ob_end_clean();
    return $body;
}

/**
 * Default email content for the delayed_downgrade_processed_admin email template.
 *
 * @since 1.0
 *
 * @return string
 */
function pmprorate_get_default_delayed_downgrade_processed_admin_email_body() {
    ob_start();
    ?>
    <p><?php esc_html_e( 'A downgrade for !!display_name!! has been successfully processed at !!sitename!!.'); ?></p>
    <p><?php esc_html_e( "View the user's downgrade information here: !!edit_member_downgrade_url!!", 'pmpro-prorate' ); ?></p>
    <?php
    $body = ob_get_contents();
    ob_end_clean();
    return $body;
}

/**
 * Default email content for the delayed_downgrade_error_admin email template.
 *
 * @since 1.0
 *
 * @return string
 */
function pmprorate_get_default_delayed_downgrade_error_admin_email_body() {
    ob_start();
    ?>
    <p><?php esc_html_e( 'There was an error processing a downgrade for !!display_name!! at !!sitename!!.'); ?></p>
    <p><?php esc_html_e( "View the user's downgrade information here: !!edit_member_downgrade_url!!", 'pmpro-prorate' ); ?></p>
    <?php
    $body = ob_get_contents();
    ob_end_clean();
    return $body;
}

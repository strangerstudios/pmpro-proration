<?php

class PMPro_Email_Template_PMProRate_Delayed_Downgrade_Scheduled extends PMPro_Email_Template {

	/**
	 * The parent user.
	 *
	 * @var WP_User
	 */
	protected $user;

	/**
	 * The downgrade.
	 *
	 * @var PMProrate_Downgrade
	 */
	protected $downgrade;

	/**
	 * Constructor.
	 *
	 * @since 1.0.2
	 *
	 * @param WP_User $user The user downgrading.
	 * @param PMProrate_Downgrade $downgrade The downgrade object.
	 */
	public function __construct( WP_User $user, PMProrate_Downgrade $downgrade ) {
		$this->user = $user;
		$this->downgrade = $downgrade;
	}

	/**
	 * Get the email template slug.
	 *
	 * @since 1.0.2
	 *
	 * @return string The email template slug.
	 */
	public static function get_template_slug() {
		return 'delayed_downgrade_scheduled';
	}

	/**
	 * Get the "nice name" of the email template.
	 *
	 * @since 1.0.2
	 *
	 * @return string The "nice name" of the email template.
	 */
	public static function get_template_name() {
		return esc_html__( 'Proration Downgrade Scheduled', 'pmpro-proration' );
	}

	/**
	 * Get "help text" to display to the admin when editing the email template.
	 *
	 * @since 1.0.2
	 *
	 * @return string The "help text" to display to the admin when editing the email template.
	 */
	public static function get_template_description() {
		return esc_html__( 'This email is sent when a membership downgrade is scheduled.', 'pmpro-proration' );
	}

	/**
	 * Get the default subject for the email.
	 *
	 * @since 1.0.2
	 *
	 * @return string The default subject for the email.
	 */
	public static function get_default_subject() {
		return esc_html( sprintf( __( 'Your downgrade has been scheduled at %s', 'pmpro-proration' ), get_option( 'blogname' ) ) );
	}

	/**
	 * Get the default body content for the email.
	 *
	 * @since 1.0.2
	 *
	 * @return string The default body content for the email.
	 */
	public static function get_default_body() {
		return wp_kses_post( pmprorate_get_default_delayed_downgrade_scheduled_email_body() );
	}

	/**
	 * Get the email template variables for the email paired with a description of the variable.
	 *
	 * @since 1.0.2
	 *
	 * @return array The email template variables for the email (key => value pairs).
	 */
	public static function get_email_template_variables_with_description() {


		return array(
			'!!display_name!!' => esc_html__( 'The user\'s display name.', 'pmpro-proration' ),
			'!!pmprorate_downgrade_text!!' => esc_html__( 'The text of the downgrade.', 'pmpro-proration' ),
			'!!edit_member_downgrade_url!!' => esc_html__( 'The URL to edit the member\'s downgrade.', 'pmpro-proration' ),
		);
	}

	/**
	 * Get the email template variables for the email.
	 *
	 * @since 1.0.2
	 *
	 * @return array The email template variables for the email (key => value pairs).
	 */
	public function get_email_template_variables() {
		$user = $this->user;
		$downgrade = $this->downgrade;

		$email_template_variables = array(	
			'display_name' => $user->display_name,
			'pmprorate_downgrade_text' => $downgrade->get_downgrade_text(),
			'edit_member_downgrade_url' => admin_url( 'admin.php?page=pmpro-member&user_id=' . $user->ID . '&pmpro_member_edit_panel=pmprorate-downgrades' ),
		);
		return $email_template_variables;
	}

	/**
	 * Get the email address to send the email to.
	 *
	 * @since 1.0.2
	 *
	 * @return string The email address to send the email to.
	 */
	public function get_recipient_email() {
		return $this->user->user_email;
	}

	/**
	 * Get the name of the email recipient.
	 *
	 * @since 1.0.2
	 *
	 * @return string The name of the email recipient.
	 */
	public function get_recipient_name() {
		$user = $this->user;
		return empty( $user->display_name ) ? esc_html__( 'User', 'pmpro-proration' ) : $user->display_name;
	}

	/**
	 * Returns the arguments to send the test email from the abstract class.
	 *
	 * @since 1.0.2
	 *
	 * @return array The arguments to send the test email from the abstract class.
	 */
	public static function get_test_email_constructor_args() {
		global $current_user;

		//Create test downgrade.
		$test_downgrade = PMProrate_Downgrade::get_test_downgrade();
		return array( $current_user, $test_downgrade );
	}
}
/**
 * Register the email template.
 *
 * @since 1.0.2
 *
 * @param array $email_templates The email templates (template slug => email template class name)
 * @return array The modified email templates array.
 */
function pmpro_email_template_pmpro_proration_delayed_downgrade_scheduled( $email_templates ) {
	$email_templates['delayed_downgrade_scheduled'] = 'PMPro_Email_Template_PMProRate_Delayed_Downgrade_Scheduled';
	return $email_templates;
}
add_filter( 'pmpro_email_templates', 'pmpro_email_template_pmpro_proration_delayed_downgrade_scheduled' );

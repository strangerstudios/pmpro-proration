<?php
/**
 * Run any necessary upgrades to the DB.
 *
 * @since TBD
 */
function pmprorate_check_for_upgrades() {
	$db_version = get_option( 'pmprorate_db_version' );

	// If we can't find the DB tables, reset db_version to 0
	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmprorate_downgrades = $wpdb->prefix . 'pmprorate_downgrades';
	$table_exists = $wpdb->query("SHOW TABLES LIKE '" . $wpdb->pmprorate_downgrades . "'");
	if(!$table_exists)
		$db_version = 0;

	// Default options.
	if ( ! $db_version ) {
		pmprorate_db_delta();
		update_option( 'pmprorate_db_version', 1 );
	}
}

/**
 * Make sure the DB is set up correctly.
 *
 * @since TBD
 */
function pmprorate_db_delta() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmprorate_downgrades = $wpdb->prefix . 'pmprorate_downgrades';

	// pmprorate_downgrades
    $sqlQuery = "
		CREATE TABLE `" . $wpdb->pmprorate_downgrades . "` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) unsigned NOT NULL,
			`original_level_id` int(11) unsigned NOT NULL,
            `new_level_id` int(11) unsigned NOT NULL,
            `downgrade_order_id` bigint(20) unsigned NOT NULL,
			`status` varchar(32) NOT NULL DEFAULT 'pending',
			PRIMARY KEY (`id`),
			KEY `user_id` (`user_id`),
            KEY `downgrade_order_id` (`downgrade_order_id`)
		);
	";
	dbDelta( $sqlQuery );
}

// Check if the DB needs to be upgraded.
if ( is_admin() || defined('WP_CLI') ) {
	pmprorate_check_for_upgrades();
}
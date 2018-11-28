=== Paid Memberships Pro: Proration Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, members, memberships, prorated, prorate, proration, upgrade, downgrade
Requires at least: 3.0
Tested up to: 4.9.4
Stable tag: .3.1

Simple proration for membership upgrades and downgrades to maintain a member's payment date and adjust initial payment at membership checkout.

== Description ==
When a member chooses to upgrade, they are charged a pro-rated amount for the new membership level immediately and the member's current payment date is maintained.

When a member chooses to downgrade, the initial payment is $0 and the downgrade is delayed until the next payment date. The member's current payment date is maintained.

Downgrades are defined as having an initial payment less than the current level, but can be altered via filters. It assumes that the level's initial payment is equal to billing amount.

A limitation of this code is that if a member upgrades twice within one pay period, only the last payment will be considered with the prorating. This could be handled by summing the total of all orders within the pay period, but this could cause conflicts on sites that have multiple unrelated orders (pmpro-addon-packages or similar customizations) and payments on edge dates might be accidentally included or not included in the sum.

== Installation ==

1. Upload the `pmpro-proration` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==
= .3.1 =
* BUG FIX: Added a check to the filter of pmpro_checkout_level to bail if no level.

= .3 =
* IMPORTANT NOTE: The logic and math for prorating has been updated. See the changelog below and our blog post here (https://www.paidmembershipspro.com/proration-add-on-update-v-3/) for more information.
* BUG FIX/ENHANCEMENT: Now using the subtotal (pretax amount) when calculating credits for prorating.
* BUG FIX/ENHANCEMENT: Added pmprorate_trim_timestamp function and using it so proration calculations are more consistent day to day.
* BUG FIX/ENHANCEMENT: Remove unneeded quotes for startdate in pmpro_checkout_start_date filter
* BUG FIX/ENHANCEMENT: Added pmprorate_have_same_payment_period function and using it when deciding which proration option to use.
* BUG FIX/ENHANCEMENT: The logic for how the profile start date is adjusted has been changed when checking out for a level with a different pay period. In these cases, the start date is not adjusted. By default, the new subscription starts after one pay period (based on the new level the user is checking out for).
* ENHANCEMENT: WordPress code style
* ENHANCEMENT: Wrap database queries in $wpdb->prepare()
* ENHANCEMENT: Use date_i18n() consistently
* ENHANCEMENT: Use wpdb->update() in place of raw SQL
* ENHANCEMENT: Make translation ready

= .2 =
* Downgrade function updated to assume level order based on initial payments. Be sure to edit this to your needs.
* Fixed issue where the existing subscription date was not being used.

= .1 =
* First version.
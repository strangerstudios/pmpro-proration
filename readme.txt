=== Paid Memberships Pro: Proration Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, members, memberships, prorated, prorate, proration, upgrade, downgrade
Requires at least: 3.0
Tested up to: 6.7
Stable tag: 1.0.1

Simple proration for membership upgrades and downgrades to maintain a member's payment date and adjust initial payment at membership checkout.

== Description ==
When a member chooses to upgrade, they are charged a pro-rated amount for the new membership level immediately and the member's current payment date is maintained.

When a member chooses to downgrade, the initial payment is $0 and the downgrade is delayed until the next payment date. The member's current payment date is maintained.

Downgrades are defined as having an average subscription cost per day less than the current level, but can be altered via filters.

For sites that are not yet upgraded to PMPro v3.0, a limitation of this code is that if a member upgrades twice within one pay period, only the last payment will be considered with the prorating. This could be handled by summing the total of all orders within the pay period, but this could cause conflicts on sites that have multiple unrelated orders (pmpro-addon-packages or similar customizations) and payments on edge dates might be accidentally included or not included in the sum.
For sites that are using PMPro v3.0+, prorated amounts are calculated based on the user's active subscriptions.

== Installation ==

1. Upload the `pmpro-proration` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==
= 1.0.1 - 2025-02-05 =
* BUG FIX/ENHANCEMENT: Now setting profile start dates directly on level objects for sites running PMPro v3.4+ to avoid conflicts with custom code. #29 (@dparker1005)
* BUG FIX: Fixed the `!!edit_member_downgrade_url!!` email template variable generating an incorrect URL. #27 (@dwanjuki) 
* BUG FIX: Fixed incorrect text domains for localized strings. #31 (@andrewlimaza)

= 1.0 - 2024-03-21 =
* FEATURE: Now fully supporting delayed downgrades for PMPro v3.0+. #24 (@dparker1005)
* ENHANCEMENT: Improved proration accurancy by using the subscriptions table in PMPro v3.0+ to calculate the prorated amount. #24 (@dparker1005)
* ENHANCEMENT: Added localization support. (@dparker1005)
* BUG FIX/ENHANCEMENT: Now showing prorated level cost after applying a discount code. #4 (@TravisHardman)
* BUG FIX/ENHANCEMENT: Improving the accuracy of the `pmprorate_isDowngrade()` and `pmprorate_have_same_payment_period()` functions. #21 (@dparker1005)
* BUG FIX/ENHANCEMENT: When using PMPro v3.0+, prorations now only happen between levels in the same "one level per user" level group. #24 (@dparker1005)
* BUG FIX: Fixed issue where users would not keep their old membership level when downgrading. #14 (@dparker1005)
* BUG FIX: Fixed issue where users could be charged more than the original level cost if checking out for a level with a different billing period and previous recurring orders are missing. #11 (@dparker1005)
* BUG FIX: Fixed issue where users could receive a free initial payment if upgrading to a level with the same billing period and previous recurring orders are missing. #11 (@dparker1005)
* BUG FIX: Fixed issue where subscriptions purchased during a downgrade would charge 1 payment period from the downgrade. Instead, the first recurring payment is now charged on the "next payment date" for the old subscription. (@ideadude)
* REFACTOR: Moved code into separate files for clarity. #23 (@dparker1005)
* DEPRECATED: Deprecated old functions that are not called. #22 (@dparker1005)

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
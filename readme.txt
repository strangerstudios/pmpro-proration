=== Paid Memberships Pro: Proration Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, members, memberships, prorated, prorate, proration, upgrade, downgrade
Requires at least: 3.0
Tested up to: 4.5
Stable tag: .2

Simple proration for membership upgrades and downgrades to maintain a member's pamynet date and adjust initial payment at membership checkout.

== Description ==
When a member chooses to upgrade, they are charged a pro-rated amount for the new membership level immediately and the member's current payment date is maintained.

When a membership choosed to downgrade, the initial payment is $0 and the downgrade is delayed until the next payment date. The member's current payment date is maintained.

Downgrades are defined as having an initial payment less than the current level, but can be alterred via filters. It assumes that the level's initial payment is equal to billing amount.

A limitation of this code is that if a member upgrades twice within one pay period, only the last payment will be considered with the prorating. This could be handled by summing the total of all orders within the pay period, but this could cause conflicts on sites that have multiple unrelated orders (pmpro addon packages or similar customizations) and payments on edge dates might be accidentally included or not included in the sum.

== Installation ==

1. Upload the `pmpro-proration` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog == 
= .2 =
* Downgrade function updated to assume level order based on initial payments. Be sure to edit this to your needs.
* Fixed issue where the existing subscription date was not being used.

= .1 =
* First version.
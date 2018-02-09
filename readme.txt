=== BP XProfile Relationship Field ===
Contributors: Offereins
Tags: buddypress, xprofile, profile, field, type, relationship, relation, objects
Requires at least: 4.5, BP 2.7
Tested up to: 4.9, BP 3.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds a relationship profile field type to connect members to other objects

== Description ==

Use the Relationship profile field type to connect your members to other (WordPress) objects like:

* Post Types
* Taxonomies
* Users
* Roles
* Comments
* and custom items that can be added through plugin filters

This plugin is inspired by the Relationship type connector in Pods.

This plugin requires BP 2.7 to be functional!

== Installation ==

If you download BP XProfile Relationship Field manually, make sure it is uploaded to "/wp-content/plugins/bp-xprofile-relationship-field/".

Activate BP XProfile Relationship Field in the "Plugins" admin panel using the "Activate" link. If you're using WordPress Multisite, you can choose to activate BP XProfile Relationship Field network wide for full integration with all of your sites.

== Changelog ==

= 1.2.0 =
* Updated field type to use the selection method's native field type markup, which fixes a bug in BP 2.9
* Updated field option handling to match upcoming BP 3.0
* Added filter to group queries fetching field data to parse relationship field data

= 1.1.2 =
* Changed post object options to result from WP_Query which does not suppress filters
* Changed field edit input option filter naming to align with BP's equivalents

= 1.1.1 =
* Improve field display value rendering by using the field type's `display_field()` method
* Minor overall fixes

= 1.1.0 =
* Plugin now requires BP 2.7

= 1.0.3 =
* Added filtering of bp_get_member_field_data() and bp_get_profile_field_data()

= 1.0.2 =
* Undo class renaming. See https://buddypress.trac.wordpress.org/changeset/10653

= 1.0.1 =
* Compatibility with BP 2.6
* Fixed show/hide admin field type options

= 1.0.0 =
* Initial release

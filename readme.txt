=== Planning Center Church Integrator ===
Contributors: Sagitarisandy
Tags: planning center, church, events, sermons, groups
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.21

Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them via shortcodes.

== Description ==

This plugin connects to the Planning Center API using a Personal Access Token (Application ID + Secret), caches responses with WordPress transients, and provides:

* [pcc_events limit="10"]
* [pcc_sermons limit="10"]
* [pcc_groups limit="20"]

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin
3. Go to Settings -> Planning Center and enter your credentials
4. Add shortcodes to your pages

== Frequently Asked Questions ==

= Where do I get credentials? =
Create a Planning Center Personal Access Token (Application ID + Secret).

== Changelog ==

= 1.0.0 =
Initial release.

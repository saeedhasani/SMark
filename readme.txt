=== SMark ===
Contributors: saeedhasani
Tags: marketing, seo, ai, content
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO, content, email marketing, social media, backlink, keyword research, and project workflow tools for WordPress.

== Description ==

SMark is a multi-purpose WordPress plugin that brings SEO, content, email marketing, social media, backlink management, keyword research, competitor analysis, and project workflow tools into one admin workspace.

The plugin helps WordPress site owners and marketing teams manage project setup, keyword workflows, content planning, backlink tracking, email campaign sending, and campaign performance review without leaving the WordPress dashboard.

== Installation ==

1. Upload the `smark` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Does SMark require any extra PHP extensions? =

Some features may require common PHP extensions (for example `zip` for reading `.xlsx` files). If a feature needs an extension, it should show an error message when unavailable.

== Changelog ==

= 1.0.2 =
* Added public campaign tracking URLs for email opens and clicks.
* Improved email open tracking with proper transparent 1x1 pixel responses.
* Inferred opens from tracked clicks when remote images are blocked by mail clients.
* Grouped campaign performance activity by recipient for clearer reporting.
* Added support for regular SMTP email accounts alongside Gmail accounts.
* Added provider-specific email account setup text and Gmail app-password guidance.
* Expanded SMTP account support with ports 25 and 2525 and a no-encryption option.
* Redirected successful project setup saves back to the SMark dashboard.
* Added SMark trademark and brand usage policy.
* Clarified public release licensing and brand usage terms.

= 1.0.1 =
* Add automatic plugin updates from public GitHub releases.

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.2 =
Improves campaign tracking, adds regular SMTP account support beside Gmail, clarifies campaign performance reporting, and documents SMark brand usage terms.

= 1.0.1 =
Adds automatic updates from public GitHub releases.

= 1.0.0 =
Initial public release.

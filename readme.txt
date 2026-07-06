=== SMark ===
Contributors: saeedhasani
Tags: marketing, seo, ai, content
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.7
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

= 1.0.7 =
* Added modal-based editing for saved email accounts.
* Displayed daily account usage as sent / daily limit in the Email Accounts table.
* Color-coded account usage so remaining capacity and exhausted capacity are easier to scan.
* Replaced the campaign sender multi-select with a checkbox dropdown that starts with the default account selected.
* Allowed campaigns to select multiple sender accounts and send through them in order.
* Switched campaign sending to the next selected sender account when the current account reaches its daily capacity.
* Added capacity warnings when the selected audience is larger than the remaining capacity of the selected sender accounts.
* Blocked sending with a clear capacity error when the selected sender accounts cannot cover the selected audience.
* Added an All audience option for campaign segment selection.
* Improved English-only header alignment on email marketing account, contact, and campaign screens.

= 1.0.6 =
* Reduced the email open tracking grace window from 120 seconds to 10 seconds.
* Continued filtering scanner, bot, preview, and prefetch open requests.
* Allowed Gmail and similar privacy proxy image loads to count after the grace window, so real Gmail opens can be recorded.
* Kept clicks separate from opens so click activity no longer creates artificial open badges.
* Added privacy-proxy context to ignored-open debug logs.

= 1.0.5 =
* Made campaign open tracking more conservative to reduce false opens.
* Ignored immediate post-send open pixel requests during the grace window.
* Excluded suspected proxy, scanner, bot, preview, and prefetch requests from open metrics.
* Stopped treating clicks as inferred opens in reports and activity rows.
* Required signed tracking tokens for open and click tracking requests.

= 1.0.4 =
* Normalized uploaded SMark packages into the canonical `smark` plugin directory when SMark is already active.
* Reduced accidental duplicate installations from GitHub source-code zip folders.
* Prevented non-canonical duplicate SMark folders from loading beside the canonical plugin.
* Added an Update URI plugin header for stable WordPress update identity.
* Clarified that the release asset zip is the correct WordPress install package.

= 1.0.3 =
* Fixed WordPress admin update failures caused by GitHub archive folder validation.
* Prepared downloaded update packages before WordPress checks for a valid plugin.
* Detected the plugin folder by `smark.php` instead of relying on GitHub-generated folder names.
* Normalized update packages into the expected `smark` directory.
* Preferred release asset zip packages named `smark.zip` or `smark-plugin.zip` when available.
* Added clearer updater errors for invalid or unmovable packages.

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

= 1.0.7 =
Adds safer multi-account email sending, visible account capacity usage, account editing in a modal, and capacity warnings before campaigns are sent.

= 1.0.6 =
Balances open tracking accuracy by using a shorter 10-second grace window while allowing Gmail proxy opens after that window.

= 1.0.5 =
Improves email campaign reporting accuracy by reducing false open events from mail-provider proxies, scanners, and immediate image fetches.

= 1.0.4 =
Adds safeguards so manual SMark uploads replace the canonical plugin more reliably and duplicate source-code folders do not load beside it.

= 1.0.3 =
Fixes WordPress admin update installation failures for GitHub release packages.

= 1.0.2 =
Improves campaign tracking, adds regular SMTP account support beside Gmail, clarifies campaign performance reporting, and documents SMark brand usage terms.

= 1.0.1 =
Adds automatic updates from public GitHub releases.

= 1.0.0 =
Initial public release.

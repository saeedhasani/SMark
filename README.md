# SMark

SMark is a comprehensive WordPress plugin for SEO, content, email marketing, social media, backlink management, keyword research, competitor analysis, and project workflow tools.

It brings the day-to-day marketing workspace into WordPress: project setup, content planning, keyword workflows, backlink tracking, campaign sending, campaign performance review, and connected-service management live under one admin experience.

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- Some features may require common PHP extensions such as `zip` for reading `.xlsx` files.

## Installation

1. Download or clone this repository into `wp-content/plugins/smark`.
2. Activate **SMark** from the WordPress admin plugins screen.
3. Configure project settings and any connected services from the SMark admin pages.

## Architecture Notes

SMark is designed so site-specific settings, credentials, tokens, and connected accounts are stored in the WordPress database, not hardcoded in plugin files.

Some features communicate with the central SMark Core service for shared API access, token accounting, and managed integrations. The default central endpoint points to `saeedhasani.com` and can be customized through WordPress filters where supported.

## Security Notes

- Gmail app passwords are stored encrypted using WordPress salts.
- Search Console tokens are stored encrypted using WordPress salts.
- Debug logs are disabled by default and gated behind debug settings.
- Browser console logging is removed for public release.

## Version

Current public release: `1.0.9`

## Current Release Highlights

### SMark 1.0.9

Version 1.0.9 adds Agent Settings to Project Settings and activates the Offer Agent workflow for Create Offer automation.

- Add Agent Settings cards for Daily Guide automation setup.
- Add configurable Offer Agent inputs for product, audience type, and strategy.
- Support random selection for Offer Agent inputs when configured.
- Send the configured Offer Agent context to SMark Core for AI offer generation.
- Save generated offers at the top of the Offers table.

### SMark 1.0.8

Version 1.0.8 expands email audience management with reusable contact lists, tags, and Include/Exclude campaign targeting.

- Contacts can now be organized with lists and tags from the Contacts screen.
- The system `All` list and daily `Received Email Today` tag are available for campaign targeting.
- Campaign messages now use Include and Exclude audience pickers for lists, tags, system tags, and individual contacts.
- Audience picker modals include live contact search, selected-contact counts, and capped contact result previews.
- Campaign recipient resolution expands Include selections and subtracts Exclude selections before sending.
- The campaign email body now uses the classic WordPress editor for richer formatting, HTML, links, and visual editing.

### SMark 1.0.7

Version 1.0.7 improves email campaign sender management, account maintenance, and capacity safety.

### SMark 1.0.6

Version 1.0.6 refines campaign open tracking after live Gmail testing.

- The open tracking grace window is now 10 seconds instead of 120 seconds, reducing false opens without missing fast real opens as aggressively.
- Scanner, bot, preview, and prefetch requests are still excluded from open metrics.
- Gmail and other privacy proxy image loads are no longer rejected solely because they come through a proxy; after the grace window, they can count as opens.
- Campaign reports still keep click events separate from open events, so a click no longer creates an artificial open badge.
- Debug logging now includes whether an ignored open request looked like a privacy proxy, making future tracking audits easier.

### SMark 1.0.5

Version 1.0.5 makes campaign open tracking more conservative so reports do not mark emails as opened simply because a mail provider, proxy, or scanner fetched the tracking pixel.

- Open pixel requests are ignored during the initial post-send grace window.
- Known proxy, scanner, bot, preview, and prefetch requests are excluded from open metrics.
- Click events no longer create an inferred open event or open badge.
- Tracking requests now require a signed token before open or click events are recorded.
- Existing reports also filter stored scanner/proxy open events when calculating open rate.

### SMark 1.0.4

Version 1.0.4 adds safeguards for manual plugin uploads.

- Uploaded SMark packages are normalized into the canonical `smark/` folder when SMark is already active.
- Non-canonical duplicate folders, such as GitHub source-code folders, do not load beside the canonical plugin.
- The plugin header now includes an Update URI for a more stable plugin identity in WordPress.
- Release downloads should use the `smark.zip` asset, which is packaged with the correct root folder for WordPress.

### SMark 1.0.3

Version 1.0.3 is a hotfix for WordPress admin updates.

- The GitHub updater now prepares the downloaded package before WordPress validates the plugin archive.
- Update packages are detected by the presence of `smark.php`, so GitHub-generated folder names no longer break plugin validation.
- Release asset zip files named `smark.zip` or `smark-plugin.zip` are preferred when available, with the GitHub tag archive kept as a fallback.
- Update failures now return clearer SMark-specific errors when the downloaded package is invalid or cannot be moved into the expected plugin folder.

### SMark 1.0.2

Version 1.0.2 focuses on email campaign reliability, account flexibility, and public-release documentation.

- Email open tracking now uses public tracking URLs and returns a proper 1x1 pixel response, making campaign opens easier to record across recipients and mail clients.
- Campaign click activity can now imply an open event for the same recipient, so performance reports better reflect real engagement when image loading is blocked.
- Campaign performance activity is grouped by recipient, which makes the reporting view easier to scan and reduces noisy duplicate event rows.
- Email account setup now supports standard SMTP mailboxes in addition to Gmail accounts.
- SMTP configuration supports more common ports and a no-encryption option for mail providers that require it.
- Gmail-specific help remains available when Gmail is selected, including app-password guidance.
- Successful first project setup now returns the user to the main SMark dashboard.
- Public-release documentation now includes SMark trademark and brand usage terms.

## Updates

SMark can receive automatic updates from public GitHub releases. Publish each new version as a GitHub release using a tag like `v1.0.1`, `v1.0.2`, or `v1.1.0`.

## License

GPLv2 or later. See [LICENSE.txt](LICENSE.txt).

## Brand and Trademarks

The SMark name, logo, and related branding are trademarks of Saeed Hasani.

The plugin code is licensed under GPLv2 or later, but the SMark brand is protected separately. Modified, forked, repackaged, or redistributed versions must not use the SMark name, logo, or branding in a way that suggests they are official, endorsed, or maintained by the SMark project unless written permission is granted.

See [TRADEMARKS.md](TRADEMARKS.md).

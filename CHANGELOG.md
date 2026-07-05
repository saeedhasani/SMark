# Changelog

## Unreleased

## 1.0.5

### Conservative email open tracking

- Restored a post-send grace window for open pixels so immediate mail-provider fetches do not count as recipient opens.
- Ignored suspected bot, scanner, proxy, preview, and prefetch open requests.
- Filtered stored scanner/proxy open events out of open-rate calculations.
- Stopped inferring opens from clicks in metrics and campaign activity rows.
- Required signed tracking tokens for open and click tracking requests.

## 1.0.4

### Manual install replacement safeguards

- Normalized uploaded SMark packages into the canonical `smark` plugin directory when SMark is already active.
- Allowed uploaded GitHub source-code packages to be prepared before WordPress installs them, reducing accidental duplicate SMark folders.
- Added a runtime guard so non-canonical duplicate SMark folders do not load beside the canonical `smark/smark.php` plugin.
- Added an `Update URI` plugin header for stable update identity.
- Documented that the release asset zip is the correct WordPress install package.

## 1.0.3

### WordPress updater reliability

- Prepared GitHub update packages before WordPress runs plugin archive validation during AJAX and bulk plugin updates.
- Detected the real SMark plugin source directory by checking for `smark.php` instead of relying on GitHub archive folder names.
- Normalized downloaded update packages into the expected `smark` plugin directory before installation.
- Preferred release asset zip packages named `smark.zip` or `smark-plugin.zip` when available.
- Added clearer SMark-specific errors for invalid or unmovable update packages.

## 1.0.2

### Email campaign tracking

- Added a public campaign tracking handler so open and click tracking can work through front-end URLs, including unauthenticated recipient requests.
- Improved open tracking responses to return a transparent 1x1 pixel image.
- Removed the default open-tracking grace delay while keeping the grace-period filter available for sites that need it.
- Kept scanner/bot filtering protections available for campaign performance calculations.
- Inferred campaign opens from tracked clicks, which improves reporting when recipients block remote images but still click campaign links.

### Campaign performance reporting

- Grouped campaign activity by campaign and recipient hash so each recipient has a clearer performance row.
- Preserved sent, opened, and clicked activity in the campaign metrics pipeline.
- Reduced duplicate event noise in campaign activity views.

### Email accounts and SMTP

- Added support for standard SMTP email accounts alongside Gmail accounts.
- Added an account-provider selector to switch the setup form between generic email and Gmail.
- Expanded SMTP port support to include 25 and 2525 in addition to 465 and 587.
- Added a no-encryption SMTP option for providers that require plain connections.
- Kept Gmail-specific app-password guidance visible only for Gmail account setup.
- Required a custom SMTP host for generic email accounts while preserving `smtp.gmail.com` as the Gmail default.

### Project setup

- Redirected successful project setup saves back to the SMark dashboard.
- Removed repeated setup warning text after a successful save flow.

### Public release documentation

- Added SMark trademark and brand usage policy.
- Clarified that the plugin code is GPLv2-or-later while the SMark name, logo, and related branding are protected separately.
- Updated public readme metadata for the 1.0.2 stable release.

## 1.0.1

- Add automatic plugin updates from public GitHub releases.

## 1.0.0

- Initial public release.

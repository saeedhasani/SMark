# Changelog

## Unreleased

## 1.0.7

### Email account management

- Added modal-based editing for saved email accounts so account details can be updated without leaving the Email Accounts screen.
- Preserved existing encrypted account passwords when editing an account and leaving the password field blank.
- Added an Edit action beside Delete in the saved accounts table.
- Displayed daily account usage as `sent / daily limit` in the Email Accounts table.
- Color-coded daily usage so active capacity remains green and exhausted account capacity turns red.
- Added simple hover tooltips for the sent-today number and daily-limit number.

### Campaign sender capacity

- Added multi-account campaign sender selection using a checkbox dropdown instead of a native multi-select field.
- Kept the default sender selected by default while allowing additional accounts to be checked from the dropdown.
- Stored selected sender accounts as an ordered list while preserving compatibility with older single-sender campaign messages.
- Rotated campaign sends across selected sender accounts in order.
- Switched to the next selected sender account when the current sender reaches its daily capacity.
- Recorded sender account IDs on sent and failed campaign events for more accurate daily capacity tracking.
- Counted today's sent events per sender account, including older events where the sender can be inferred from the campaign message.
- Blocked campaign sending when selected sender accounts do not have enough remaining daily capacity for the selected audience.
- Added a live capacity warning when the selected audience exceeds the remaining capacity of selected sender accounts.

### Campaign audience and UI

- Added an `All` option to campaign segment selection so campaigns can target every subscribed contact.
- Improved English-only left alignment for the Email Accounts list header, Contacts header actions, and Campaign Message form header.
- Kept capacity warning calculations based on unique selected recipients, including segment and individual-contact combinations.

## 1.0.6

### Balanced email open tracking

- Reduced the open tracking grace window from 120 seconds to 10 seconds after live Gmail testing.
- Continued filtering scanner, bot, preview, and prefetch open requests.
- Allowed Gmail and similar privacy proxy image loads to count as opens after the grace window instead of rejecting them solely by proxy user-agent.
- Kept click tracking separate from open tracking so clicks do not create inferred open events or open badges.
- Added privacy-proxy context to ignored-open debug logs for easier investigation.

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

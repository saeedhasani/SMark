# Changelog

## Unreleased

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

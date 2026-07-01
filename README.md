# SMark

SMark is a comprehensive WordPress plugin for SEO, content, email marketing, social media, backlink management, keyword research, competitor analysis, and project workflow tools.

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

Current public release: `1.0.2`

## Updates

SMark can receive automatic updates from public GitHub releases. Publish each new version as a GitHub release using a tag like `v1.0.1`, `v1.0.2`, or `v1.1.0`.

## License

GPLv2 or later. See [LICENSE.txt](LICENSE.txt).

## Brand and Trademarks

The SMark name, logo, and related branding are trademarks of Saeed Hasani.

The plugin code is licensed under GPLv2 or later, but the SMark brand is protected separately. Modified, forked, repackaged, or redistributed versions must not use the SMark name, logo, or branding in a way that suggests they are official, endorsed, or maintained by the SMark project unless written permission is granted.

See [TRADEMARKS.md](TRADEMARKS.md).

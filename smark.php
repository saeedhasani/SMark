<?php
/**
 * Plugin Name: SMark
 * Description: SEO, content, email marketing, social media, backlink, keyword research, and project workflow tools for WordPress.
 * Version: 1.0.8
 * Author: Saeed Hasani
 * Author URI: https://saeedhasani.com
 * Update URI: https://github.com/saeedhasani/SMark
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smark
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * Public release note:
 * The Author and Author URI fields intentionally identify the plugin author
 * and should remain public distribution metadata.
 *
 * Changelog:
 * Version 1.0.8 - Email audience lists, tags, and campaign targeting
 * - Add contact lists and tags with assignment controls on the Contacts screen
 * - Add system All list and daily Received Email Today system tag
 * - Replace campaign segment/contact selectors with Include and Exclude audience pickers
 * - Add live contact search, selected-contact counts, and capped contact results in audience pickers
 * - Replace the campaign body textarea with the classic WordPress editor
 * Version 1.0.7 - Email sender capacity and account editing
 * - Add modal-based editing for saved email accounts
 * - Show daily sent usage beside each email account limit
 * - Add multi-account sender selection for campaigns with checkbox dropdown controls
 * - Rotate campaign sends across selected sender accounts when daily capacity is reached
 * - Warn users when the selected audience exceeds remaining sender capacity
 * - Add an All audience segment option for campaign messages
 * - Improve English layout alignment in email marketing screens
 * Version 1.0.6 - Balanced email open tracking
 * - Reduce the open tracking grace window to 10 seconds
 * - Keep scanner, bot, preview, and prefetch open filtering
 * - Allow Gmail/Apple privacy proxy image loads after the grace window so real Gmail opens can be counted
 * - Keep click events separate from open events
 * Version 1.0.5 - Conservative email open tracking
 * - Ignore immediate/proxy/scanner open pixel requests to reduce false opens
 * - Stop inferring opens from clicks in campaign reports
 * - Require signed tracking tokens for open and click tracking requests
 * Version 1.0.4 - Manual install replacement safeguards
 * - Normalize uploaded SMark packages into the canonical smark folder when SMark is already active
 * - Prevent duplicate non-canonical SMark folders from loading beside the canonical plugin
 * - Add an Update URI header for stable plugin identity
 * Version 1.0.3 - WordPress updater reliability
 * - Prepare GitHub release packages before WordPress validates the plugin archive
 * - Detect the SMark plugin folder by smark.php instead of relying on GitHub archive folder names
 * - Prefer release asset zip packages when available
 * Version 1.0.2 - Email tracking and SMTP account improvements
 * - Improve email open tracking with public tracking URLs and 1x1 pixel responses
 * - Infer opens from tracked clicks and group campaign activity by recipient
 * - Support regular SMTP email accounts alongside Gmail accounts
 * - Redirect successful project setup saves back to the SMark dashboard
 * Version 1.0.1 - GitHub release updater
 * - Add automatic plugin updates from public GitHub releases
 * Version 1.0.0 - Initial public release
 * - Add Email Accounts to the Email Marketing hub
 * - Add a Gmail account management page with SMTP settings and daily send limits
 * - Updated plugin version for cache busting
 * Version 2.19.95 - Email Marketing hub
 * - Add Email Marketing to the dashboard main features
 * - Add an Email Marketing admin page using the SEO center styling
 * - Updated plugin version for cache busting
 * Version 2.19.94 - SEO hub technical SEO item
 * - Add a Technical SEO item to the SEO management center without a destination link
 * - Updated plugin version for cache busting
 * Version 2.19.93 - Daily Guide backlink acquisition reminder
 * - Add a Daily Guide item when no backlink has been marked acquired today
 * - Track acquired backlink timestamps in Backlinks Management
 * - Updated plugin version for cache busting
 * Version 2.19.92 - Backlinks status color coding
 * - Color-code Backlinks Management status fields by current status
 * - Updated plugin version for cache busting
 * Version 2.19.91 - Daily Guide Rank Math reminder visibility
 * - Show the Rank Math keyword reminder whenever the Rank Math keyword check is available
 * - Updated plugin version for cache busting
 * Version 2.19.90 - Daily Guide Rank Math keyword reminder
 * - Add a Daily Guide item when Rank Math focus keywords are missing from Keyword Research
 * - Updated plugin version for cache busting
 * Version 2.19.89 - Persistent admin notifications
 * - Keep SMark notifications visible until the user closes them manually
 * - Add circular top-left close controls to internal notification UIs
 * - Updated plugin version for cache busting
 * Version 2.19.88 - Backlinks opportunities filtering hardened
 * - Filter Error and Noting rows after candidate selection before rendering New Opportunities
 * - Fetch extra candidates so excluded rows do not reduce the visible suggestions
 * - Updated plugin version for cache busting
 * Version 2.19.87 - Backlinks opportunities exclude bad labels
 * - Hide Error and Noting analyzed rows from New Opportunities
 * - Updated plugin version for cache busting
 * Version 2.19.86 - Backlinks analysis-type filter added
 * - Add an analysis-type filter to backlinks management controls
 * - Support filtering by comment, profile, error, no-label, and unanalyzed backlinks
 * - Updated plugin version for cache busting
 * Version 2.19.85 - Backlinks opportunities label colors synced
 * - Reuse the main backlinks table label colors and pill styling in New Opportunities
 * - Keep opportunity labels display-only while matching the main table appearance
 * - Updated plugin version for cache busting
 * Version 2.19.84 - Backlinks opportunities labels display-only inline
 * - Render opportunity table domain labels as display-only badges without remove controls
 * - Place opportunity labels inline beside the domain instead of below it
 * - Updated plugin version for cache busting
 * Version 2.19.83 - Backlinks opportunities table labels + RTL header
 * - Right-align the New Opportunities table header in RTL
 * - Show backlink type labels beside domains in New Opportunities like the main table
 * - Updated plugin version for cache busting
 * Version 2.19.82 - Backlinks opportunities title selector fix
 * - Fix New Opportunities title styles by targeting the separate opportunities card scope
 * - Updated plugin version for cache busting
 * Version 2.19.81 - Backlinks filter grouping + opportunities cleanup
 * - Group target-page filter input and filter button to keep spacing aligned
 * - Remove duplicate empty-state text under New Opportunities until a page is selected
 * - Updated plugin version for cache busting
 * Version 2.19.80 - Backlinks spacing + opportunities header polish
 * - Match target-page filter spacing with adjacent action button spacing
 * - Make New Opportunities heading hierarchy match Backlinks Display
 * - Place the guidance text under the heading and offset the search field slightly from the top
 * - Updated plugin version for cache busting
 * Version 2.19.79 - Backlinks layout polish + Persian fixes
 * - Tighten target-page filter spacing in Backlinks controls
 * - Move New Opportunities into a separate card below the backlinks card
 * - Fix Persian UI strings in Backlinks opportunities interactions
 * - Updated plugin version for cache busting
 * Version 2.19.78 - Backlinks layout + opportunities
 * - Let backlinks header controls wrap onto new rows instead of horizontal scrolling
 * - Add Backlinks Display heading beside top action buttons
 * - Add New Opportunities section with page search and 5 suggested backlink rows
 * - Updated plugin version for cache busting
 * Version 2.19.77 - Backlinks multi-target pages
 * - Allow multiple target pages per backlink row with removable tags
 * - Store backlink target pages in multi-value format and migrate legacy single values
 * - Updated plugin version for cache busting
 * Version 2.19.76 - Backlinks target page autocomplete + filter
 * - Replace Backlinks target page dropdowns with search-style autocomplete inputs
 * - Add target page filter field to Backlinks Management filters
 * - Updated plugin version for cache busting
 * Version 2.19.75 - Backlinks target page column
 * - Move Backlinks Management comment column to the end of the table
 * - Add a target page column with selectable WordPress pages/posts/content types
 * - Updated plugin version for cache busting
 * Version 2.19.74 - Backlinks breadcrumb fix
 * - Set Backlinks Management breadcrumb to Dashboard → SEO Management → Backlinks Management
 * - Updated plugin version for cache busting
 * Version 2.19.71 - Exact live metric URL matching
 * - Prefer page_link_url when matching the project's result inside top 100 live SERP rows
 * - Fix RD/Backlinks "Ours" values to use the exact ranking page URL instead of host-only fallback
 * - Updated plugin version for cache busting
 * Version 2.19.70 - Keyword Research backlink and referring-domain columns
 * - Added Referring Domains and Backlinks columns beside Live Rank
 * - Fetch and store project URL metrics plus top-10 maximum metrics through SMark Core / Semrush
 * - Updated plugin version for cache busting
 * Version 2.19.69 - Keyword Research live rank via central Semrush
 * - Added Live Rank column to Keyword Research with fetch/update actions
 * - Fetch top 100 keyword results through SMark Core and match the project website rank
 * - Persist live rank position and timestamp in project keyword rows
 * - Updated plugin version for cache busting
 * Version 2.19.68 - Search Console broker header fix
 * - Remove BOM output from SMark Core OpenAI App file to prevent REST redirect/header failures
 * - Updated plugin version for cache busting
 * Version 2.19.67 - Search Console connect initiation stability
 * - Force clean JSON responses for Project Settings Search Console connect AJAX
 * - Validate broker start URL and require JSON parsing on the client for clearer failures
 * - Updated plugin version for cache busting
 * Version 2.19.66 - Central keyword fetch response compatibility
 * - Accept central keyword fetch responses in nested, direct, and wrapped JSON formats
 * - Tolerate extra output around central JSON bodies to prevent false invalid-response errors
 * - Updated plugin version for cache busting
 * Version 2.19.65 - Daily Guide gap transfer smart action
 * - Implement Smart action for `gap_transfer` to move one Keyword Gap keyword into Keyword Research
 * - Updated plugin version for cache busting
 * Version 2.19.64 - Smart action modal stability
 * - Do not auto-close the Smart action modal when the action is not implemented (keeps the message visible)
 * - Updated plugin version for cache busting
 * Version 2.19.63 - Smart action UX + error stages
 * - Ensure Smart action modal spinner always animates
 * - Return stage codes for Daily Guide smart action failures and show them in the modal
 * - Updated plugin version for cache busting
 * Version 2.19.62 - Keyword Gap action UI
 * - Reduce “Use”/“Not suitable” button widths in Keyword Gap modal
 * - Keep actions cell height consistent after marking not suitable
 * - Render “Not suitable” as red text label after marking, and remove it after “Use”
 * - Updated plugin version for cache busting
 * Version 2.19.61 - Hide token config errors
 * - Map central sync token errors to a generic central availability message in Content Management
 * - Updated plugin version for cache busting
 * Version 2.19.60 - Remove central token error on write button
 * - Central mark consume no longer hard-fails when central sync token is not configured
 * - Map token-missing errors to generic central availability message
 * - Updated plugin version for cache busting
 * Version 2.19.59 - Content Management strict central charging
 * - Make “Write content” mark charging strictly consume from central before proceeding (no local fallback)
 * - Clear pending mark debt on successful central consume to prevent false “insufficient” blocks
 * - Updated plugin version for cache busting
 * Version 2.19.58 - Project Settings mark display
 * - Show raw central mark balance in Project Settings (no pending/cache adjustment) to match central DB
 * - Updated plugin version for cache busting
 * Version 2.19.57 - Mark sync hardening
 * - Unify central sync token fallback in Project Settings and mark flows
 * - Add pending mark reconciliation to central on Project Settings load for faster balance sync
 * - Improve central mark balance/consume endpoint fallback behavior and timeout strategy
 * - Updated plugin version for cache busting
 * Version 2.19.56 - Keyword Gap "Not suitable" button
 * - Add "Not suitable" action under "Use" and highlight rows lightly in red for reviewed/unsuitable keywords
 * - Updated plugin version for cache busting
 * Version 2.19.55 - Keyword Gap mark consume fallback
 * - Fallback to local project mark credits when central sync token is missing/forbidden (prevents Keyword Gap Finder submit errors)
 * - Updated plugin version for cache busting
 * Version 2.19.54 - Mark credit UI stability
 * - Fix Project Settings cached mark timestamp localization (prevents UI overwriting decremented credits)
 * - Updated plugin version for cache busting
 * Version 2.19.53 - Mark credit decrement visibility
 * - Prevent stale central balance reads from restoring Mark credits after a recent consume
 * - Include cached mark timestamp for safer client-side display reconciliation
 * - Updated plugin version for cache busting
 * Version 2.19.52 - Mark balance consistency
 * - Require successful reserve when central is unreachable (prevents uncharged operations)
 * - Show effective mark balance (central minus pending) in Project Settings and avoid JS overwrites
 * - Updated plugin version for cache busting
 * Version 2.19.51 - Offline-stable mark charging
 * - Cache mark balance in wp_options for Content Management (works even if projects table cannot be altered)
 * - Defer central mark consumption on DNS/timeout and reconcile later when reachable
 * - Updated plugin version for cache busting
 * Version 2.19.50 - Content Management stability + button spinner
 * - Handle central mark consume DNS/timeout errors without showing raw cURL messages (fallback to local mark when possible)
 * - Sync mark balance into local project row from Project Settings (best-effort)
 * - Show a small spinner on “Write content” while charging marks
 * - Updated plugin version for cache busting
 * Version 2.19.49 - Content Management mark consume sync
 * - Use central sync token fallbacks (option/site-option/constant) for mark consumption
 * - Retry mark consumption without central id when central id mismatch triggers false "insufficient" errors
 * - Sync local project mark column with central remaining credits (best-effort)
 * - Updated plugin version for cache busting
 * Version 2.19.48 - Daily Guide smart action continues with AI prompt
 * - Show loading modal on “Smart action”
 * - For “Some keywords still don’t have a linked page”: fetch top 10 SERP URLs + headings, call Prompt Bank (pick_best_subtitles), send to Gemini, and show results in a modal
 * - Updated plugin version for cache busting
 * Version 2.19.47 - Daily Guide smart action (no-page keywords)
 * - Implement smart action for “Some keywords still don’t have a linked page”: pick a random no-link keyword, create a blog draft, and add it to Content Management
 * - Updated plugin version for cache busting
 * Version 2.19.46 - Keyword delete also removes Content Management create items
 * - When deleting a keyword from Keyword Research, also remove its Content Management create-item entry and delete the related draft/post if created
 * - Updated plugin version for cache busting
 * Version 2.19.45 - Keyword Research breadcrumb + synced delete + Daily Guide smart button
 * - Set Keyword Research breadcrumb to Dashboard → SEO Management → Keyword Research
 * - Deleting a keyword now also deletes its related WordPress post/page (if found) and removes it from Content Management
 * - Add “Smart action” button next to Daily Guide items (placeholder for upcoming automation)
 * - Updated plugin version for cache busting
 * Version 2.19.44 - Daily Guide keyword page-link reminder
 * - If some Keyword Research items have no linked page, show a Daily Guide reminder with a direct link to the filtered view
 * - Updated plugin version for cache busting
 * Version 2.19.43 - Keyword Gap breadcrumb + SEO Optimization footer layout
 * - Set breadcrumb to Dashboard → SEO Management → Keyword Gap
 * - Adjust SEO Optimization page layout to avoid oversized main section while keeping footer at bottom
 * - Updated plugin version for cache busting
 * Version 2.19.30 - Semrush proxy stability
 * - Add caching + one retry in central Semrush proxy to reduce transient DNS/timeout failures
 * - Updated plugin version for cache busting
 * Version 2.19.29 - Keyword Gap Semrush error details
 * - If central Semrush proxy fails and no local key exists, return the central error message for faster debugging
 * - Updated plugin version for cache busting
 * Version 2.19.28 - Keyword Gap Semrush proxy fix
 * - Move Semrush proxy endpoint to SMark Core (/wp-json/smark-core/v1/tools/semrush/proxy)
 * - Always try central proxy first; fall back to local Semrush key if configured
 * - Updated plugin version for cache busting
 * Version 2.19.27 - Keyword Gap Semrush via central
 * - Route Keyword Gap Semrush fetch through central proxy (no Semrush key required on client sites)
 * - Added REST proxy endpoint on central site (token-protected) at /wp-json/smark-core/v1/tools/semrush/proxy
 * - Updated plugin version for cache busting
 * Version 2.19.26 - Content Management breadcrumb
 * - Set breadcrumb to Dashboard → SEO Management → Content Management
 * - Updated plugin version for cache busting
 * Version 2.19.25 - Content Management mark costs
 * - Remove "free" wording from mark tooltips/messages
 * - Charge 1 mark for "Review for editing" action (with badge)
 * - Updated plugin version for cache busting
 * Version 2.19.24 - Keyword bank stats stability
 * - Cache keyword bank count locally and fall back to last known good value on DNS/timeout errors
 * - Retry keyword bank count using alternate base hosts to reduce cURL resolve failures
 * - Updated plugin version for cache busting
 * Version 2.19.23 - Search Console refresh reliability
 * - Force-refresh Search Console access token on 401 responses (prevents repeated reconnect prompts when expiry metadata is missing)
 * - Avoid overwriting freshly-refreshed tokens when persisting selected Search Console property
 * - Updated plugin version for cache busting
 * Version 2.19.22 - Search Console refresh + property match
 * - Refresh Search Console access tokens via central server when local client secret is not available
 * - Use correct Search Console property format (URL-prefix trailing slash / domain property fallback)
 * - Updated plugin version for cache busting
 * Version 2.19.21 - Central mark validation
 * - Use central project mark credits for Rank Math missing-keyword fetch (avoids local mark mismatches)
 * - Updated plugin version for cache busting
 * Version 2.19.20 - Projects table resolution
 * - Improved projects table discovery to avoid mark/credit mismatches when both SMARK_projects and smark_projects exist
 * - Updated plugin version for cache busting
 * Version 2.19.19 - Central keyword fetch fallback
 * - Fetch Rank Math missing-keyword data via central REST endpoint (works even when SMark Core is not installed locally)
 * - Consume/refund mark credits locally during keyword fetch
 * - Updated plugin version for cache busting
 * Version 2.19.18 - Rank Math gap error details
 * - Added clearer, step-by-step error messages for “Review & add keyword” in Rank Math gap modal
 * - Added error codes/statuses to Rank Math gap AJAX responses for easier debugging
 * - Updated plugin version for cache busting
 * Version 2.19.17 - Search Console token refresh + messaging
 * - Refresh Search Console access tokens even when client secret is not stored locally
 * - Updated expired-access message to instruct reconnect from Project Management
 * - Updated plugin version for cache busting
 * Version 2.19.16 - Search Console OAuth return redirect
 * - Fixed OAuth broker callback redirecting back to the broker site instead of the originating site
 * - Updated plugin version for cache busting
 * Version 2.19.15 - Content Management mark wording
 * - Changed tooltip/notification wording from SMark to Free Mark
 * - Updated plugin version for cache busting
 * Version 2.19.14 - Content Management write-content credit
 * - Added 1-SMark credit charge (with tooltip + badge) on “Write content” button
 * - Updated plugin version for cache busting
 * Version 2.19.13 - Content Management footer layout fix
 * - Prevent the version footer from overlapping the table when the table is tall
 * - Updated plugin version for cache busting
 * Version 2.19.12 - Rank Math gap modal actions
 * - Right-aligned Rank Math gap modal headers and widened the actions column
 * - Added "Review & add keyword" flow to fetch keyword data from SMark Core keyword bank and insert into the project keywords table
 * Version 2.19.11 - Rank Math missing keywords modal
 * - Moved the Rank Math check button between Add Keywords and Search
 * - Added a modal listing Rank Math focus keywords that are missing from the project sheet (with per-keyword action button placeholder)
 * Version 2.19.10 - Rank Math check button + accurate gap
 * - Added a "Check Rank Math keywords" button to run the comparison on-demand
 * - Updated the Rank Math gap calculation to use normalized focus keywords (including comma-separated values)
 * Version 2.19.9 - Rank Math coverage notice persists
 * - Keep the Rank Math keyword coverage banner visible with an OK / warning / error state instead of hiding it when there are no missing keywords
 * Version 2.19.8 - Daily Guide publish link to Keyword Research
 * - Updated the Daily Guide "No content published today" action button to open Keyword Research for keyword selection and content planning
 * Version 2.19.7 - Linked keyword research title to feature page
 * - Made "تحقیق کلمات کلیدی" title clickable, linking directly to keyword research feature
 * - Removed separate "Open Keyword Research" link button
 * - Added CSS styling for title link hover effects
 * - Updated plugin version for cache busting
 * Version 2.19.6 - Removed all checkboxes from SEO Optimization page
 * - Removed checkbox inputs and indicators from all task items
 * - Removed checkbox-related JavaScript handlers
 * - Removed checkbox-related CSS styles
 * - Updated plugin version for cache busting
 * Version 2.19.5 - Fixed SEO Optimization task toggle flex direction
 * - Changed RTL (Persian) to use normal row direction (checkbox on right)
 * - Changed LTR (English) to use row-reverse (checkbox on left)
 * - Updated plugin version for cache busting
 * Version 2.19.4 - Fixed SEO Optimization keyword research section styling
 * - Updated keyword research task styling to match metadata update section
 * - Applied consistent card-like appearance with proper padding and background
 * - Updated plugin version for cache busting
 * Version 2.19.3 - Fixed SEO Optimization step header flex direction
 * - Changed flex-direction: row-reverse to apply only for English (LTR) version
 * - Removed row-reverse from RTL (Persian) version for proper layout
 * - Updated plugin version for cache busting
 * Version 2.19.2 - Fixed SEO Optimization keyword research section right padding
 * - Removed right padding from strategy card task items in RTL layout
 * - Task items in strategy card now align flush with right edge like content card
 * - Updated plugin version for cache busting
 * Version 2.19.1 - Fixed SEO Optimization keyword research section styling
 * - Changed keyword research task to match metadata update section style
 * - Removed link-only styling and restored checkbox functionality
 * - Fixed right padding spacing issue in strategy card for RTL layout
 * - Updated plugin version for cache busting
 * Version 2.19.0 - Removed checkboxes and related operations from keyword research task
 * - Completely removed checkbox functionality for keyword_map task
 * - Task now displays as a simple link button without any checkbox operations
 * - Updated plugin version for cache busting
 * Version 2.18.9 - Updated SEO Strategy keyword map section
 * - Changed "ساخت نقشه کلمات کلیدی" to "تحقیق کلمات کلیدی" with direct link
 * - Removed checkboxes from keyword map task (now a button/link)
 * - Removed "مشاهده تحقیق کلمات کلیدی" link from bottom
 * - Fixed right padding spacing issue in strategy card
 * - Updated plugin version for cache busting
 * Version 2.18.8 - Removed sections from SEO Strategy & Discovery step
 * - Removed "Align personas & journeys" task from Strategy & Discovery
 * - Removed "SERP landscape review" task from Strategy & Discovery
 * - Removed executive notes section from Strategy & Discovery step only
 * - Updated plugin version for cache busting
 * Version 2.18.7 - Added ranking trend column to database
 * - Added ranking_trend column to store trend status (decrease/increase)
 * - Trend is calculated and saved automatically when rankings are fetched
 * - Existing records are updated with trend values during migration
 * - Trend data available for use in other modules
 * - Updated plugin version for cache busting
 * Version 2.18.6 - Added color coding for keyword rankings
 * - Red color: when ranking trend is upward (3-month < 1-month) or 1-month is 0
 * - Green color: for all other cases (downward trend or stable)
 * - Added visual indicators to help quickly identify ranking performance
 * - Updated plugin version for cache busting
 * Version 2.18.5 - Changed ranking display to show 0 instead of dash when no data
 * - When no ranking data found in Search Console, display 0 instead of "—"
 * - Updated database storage to save 0 instead of null when no data available
 * - Improved ranking display logic to always show numbers (0.0) when data has been fetched
 * - Updated plugin version for cache busting
 * Version 2.18.4 - Fixed ranking display and data persistence
 * - Changed ranking separator from "—»" to arrow (→ for LTR, ← for RTL)
 * - Fixed ranking data persistence after page refresh (data now loads from database)
 * - Improved ranking data validation to handle null, empty, and zero values correctly
 * - Updated plugin version for cache busting
 * Version 2.18.3 - Fixed notification auto-hide and loading spinner icon display
 * - Added auto-hide functionality for success notifications (3 seconds)
 * - Fixed loading spinner icon (dashicons-spin) display with !important styles
 * - Improved notification animation and transition effects
 * - Updated plugin version for cache busting
 * Version 2.18.2 - Fixed ranking icon display with !important styles
 * - Added CSS styles with !important for ranking fetch and update icons
 * - Fixed dashicons font-family and display properties for proper icon rendering
 * - Updated plugin version for cache busting
 * Version 2.18.1 - Added keyword ranking feature and removed Tools Integration from main menu
 * - Removed Tools Integration menu item from main navigation (page still accessible)
 * - Added ranking column to keyword research table with Search Console integration
 * - Implemented 1-month and 3-month average ranking display
 * - Added fetch and update ranking functionality from Google Search Console
 * - Added database migration for ranking columns (rank_1month_avg, rank_3month_avg)
 * - Updated plugin version for cache busting
 * Version 2.18.0 - Unified Plugin Logs module header and footer
 * - Added consistent header and footer to Plugin Logs module matching Social Media and Tools Integration
 * - Added breadcrumb navigation with language selector
 * - Added version footer with plugin version display
 * - Implemented RTL support for Persian language
 * - Added layout fixes for proper footer positioning
 * - Updated plugin version for cache busting
 * Version 2.17.99 - Enhanced Search Console OAuth integration
 * - Added Client ID management in Tools Integration module
 * - Improved OAuth error logging and debugging
 * - Updated Client ID retrieval to use Tools Integration module
 * - Enhanced token exchange error handling
 * - Updated plugin version for cache busting
 * Version 2.17.98 - Fixed button icon alignment in Tools Integration
 * - Fixed vertical alignment of icons in buttons (Save, Check, Copy)
 * - Icons now properly centered and aligned with button text
 * - Updated plugin version for cache busting
 * Version 2.17.97 - Fixed Tools Integration page header styling
 * - Made header fonts bold (font-weight: 600) to match Social page
 * - Updated spacing and layout to match Social page styling
 * - Enhanced breadcrumb and language selector styling
 * - Updated plugin version for cache busting
 * Version 2.17.96 - Updated project management UI
 * - Removed Setup Instructions section from project settings modal
 * - Improved Search Console connection status display with glass effect
 * - Status box and connect button now properly aligned in disconnected state
 * Version 2.17.95 - Created Tools Integration module for managing external tool connections
 * - Added new Tools Integration module with Search Console configuration
 * - Moved Client Secret management from project-management to tools-integration
 * - Client Secret now configured once and used for all projects
 * - Added Search Console card in Tools Integration page
 * - Updated project-management to link to Tools Integration for Client Secret setup
 * - Updated plugin version for cache busting
 *
 * Version 2.17.94 - Added secure Client Secret storage in database
 * - Client Secret now stored securely in database (wp_options) with encryption
 * - Added UI in project settings for entering Client Secret
 * - Removed requirement for wp-config.php or functions.php modifications
 * - Client Secret encrypted using WordPress authentication keys
 * - Added AJAX handlers for saving and checking Client Secret status
 * - Updated plugin version for cache busting
 *
 * Version 2.17.93 - Enhanced Search Console setup instructions for Client Secret
 * - Added detailed instructions for configuring Client Secret in setup UI
 * - Added visual guide showing where to find Client Secret in Google Console
 * - Improved error messaging for missing Client Secret
 * - Updated plugin version for cache busting
 *
 * Version 2.17.92 - Integrated Search Console logging with SMarkLogger
 * - Replaced all error_log() calls with SMarkLogger for Search Console operations
 * - Added comprehensive logging for OAuth flow, token exchange, and connection checks
 * - Added detailed logging for token refresh operations
 * - All Search Console logs now visible in Plugin Logs module
 * - Updated plugin version for cache busting
 *
 * Version 2.17.91 - Fixed Search Console connection status check after OAuth callback
 * - Added automatic connection status refresh after successful OAuth callback
 * - Enhanced logging for debugging connection issues
 * - Improved error handling in connection verification
 * - Fixed status display not updating after successful authentication
 * - Updated plugin version for cache busting
 *
 * Version 2.17.90 - Fixed Search Console redirect URI display and validation
 * - Added critical warning about copying full URL (not just query string)
 * - Enhanced UI with visual examples of correct vs incorrect URL format
 * - Added console logging and alert before OAuth redirect
 * - Improved URL display with better formatting and copy functionality
 * - Updated plugin version for cache busting
 *
 * Version 2.17.89 - Enhanced Search Console OAuth redirect URI handling
 * - Improved redirect URI generation to ensure HTTPS and proper formatting
 * - Added detailed logging for debugging redirect URI issues
 * - Enhanced UI with copy-to-clipboard functionality for redirect URI
 * - Added troubleshooting guide in setup instructions
 * - Updated plugin version for cache busting
 *
 * Version 2.17.88 - Fixed Search Console OAuth redirect URI mismatch error
 * - Improved redirect URI handling to use full URL
 * - Added detailed setup instructions in UI
 * - Enhanced error messages for redirect_uri_mismatch
 * - Added redirect URI display for easy Google Cloud Console configuration
 * - Updated plugin version for cache busting
 *
 * Version 2.17.87 - Added Search Console connection feature to project management
 * - Added Search Console OAuth 2.0 integration for each project
 * - Added connection status display and connect button in project settings
 * - Implemented automatic token refresh functionality
 * - Updated plugin version for cache busting
 *
 * Version 2.17.86 - Fixed keyword bank checkbox display for existing project keywords
 * - Added disabled checked checkboxes for keywords already in project
 * - Prevented re-selection of keywords that are already added to project
 * - Updated bank modal to show project keyword status dynamically
 *
 * Version 2.17.85 - Enhanced border visibility in sticky header
 * - Added outline and multiple border styles to ensure borders remain visible
 * - Added specific override for data-table styles to prevent border removal
 *
 * Version 2.17.84 - Fixed border disappearing issue in sticky header
 * - Added !important flags to border styles to prevent override
 * - Border now remains visible when header becomes sticky during scroll
 *
 * Version 2.17.83 - Added border to sticky header in keyword bank table
 * - Added visible border around header row for better visual separation
 * - Header now clearly distinguishable from table rows when scrolling
 *
 * Version 2.17.82 - Added sticky header to keyword bank table
 * - Made table header sticky when scrolling in the bank modal
 * - Header remains visible at top when scrolling through keywords
 *
 * Version 2.17.81 - Fixed dashicons font display in page link column
 * - Added !important flags to CSS styles for proper icon rendering
 * - Ensured dashicons font family is properly applied
 *
 * Version 2.17.80 - Added page link column to keyword research table
 * - Added page link status checking for keywords in Rank Math
 * - Added icons to show connection status (green for found, red for not found/not connected)
 * - Only checks unchecked keywords to optimize performance
 * - Added REST API endpoint for remote site keyword checking
 * - Updated database schema to include page_link_status and page_link_url columns
 *
 * Version 2.17.79 - Fixed table column order in RTL/Persian version
 * - Added dir="rtl" attribute to table for proper RTL column ordering
 * - Fixed column order so "کلمه کلیدی" appears first from right in Persian version
 * - Updated plugin version for cache busting
 *
 * Version 2.17.78 - Updated keyword research table for RTL/Persian version
 * - Reversed table headers order in Persian version (right to left)
 * - Centered table headers and cells content in Persian version
 * - Changed delete button text to "حذف کلمه کلیدی" in Persian version
 * - Updated plugin version for cache busting
 *
 * Version 2.17.70 - Changed "Add Selected Keywords" button color to green
 * - Changed button class from btn-primary to btn-success
 * - Added btn-success CSS class with green gradient styling
 * - Button now has distinct green color to differentiate from blue upload button
 * - Updated plugin version for cache busting
 *
 * Version 2.17.69 - Fixed checkbox column display in keyword bank modal
 * - Improved updateBankModalFooter function to properly show/hide checkbox column
 * - Fixed header column display using removeAttr and table-cell
 * - Updated plugin version for cache busting
 *
 * Version 2.17.68 - Added checkbox selection and bulk add feature to keyword bank
 * - Added checkbox column to keyword bank table (visible when project is selected)
 * - Added "Add Selected Keywords" button in modal footer
 * - Users can now select multiple keywords and add them to project at once
 * - Updated plugin version for cache busting
 *
 * Version 2.17.67 - UI improvements for keyword research page
 * - Fixed "Add keywords" button position in RTL (Persian) version - now appears on the left
 * - Changed bank box help text from "کلمات را از مخزن مرکزی خود انتخاب کنید." to "کلمات کلیدی را به بانک مرکزی اضافه کنید."
 * - Updated plugin version for cache busting
 *
 * Version 2.17.66 - Fixed footer layout to match social media page structure
 * - Removed justify-content from CSS, letting JavaScript handle it
 * - Simplified JavaScript footer layout fix to match social media exactly
 * - Footer now properly positioned at bottom using same structure as social media
 * - Updated plugin version for cache busting
 *
 * Version 2.17.65 - Fixed footer positioning to bottom of page
 * - Added justify-content: space-between to keyword research page
 * - Added flex-shrink: 0 to footer to prevent shrinking
 * - Enhanced JavaScript footer layout fix with min-height settings
 * - Added additional timeout for layout fix to ensure it works
 * - Footer now properly positioned at bottom like social media page
 * - Updated plugin version for cache busting
 *
 * Version 2.17.64 - Removed upload button from keywords card
 * - Confirmed removal of upload keywords button from project keywords card header
 * - Only "Add keywords" button remains in the action buttons section
 * - Updated plugin version for cache busting
 *
 * Version 2.17.63 - UI improvements for keyword research page
 * - Removed upload keywords button from project keywords card
 * - Changed "Actions" to "عملیات" in Persian version
 * - Moved footer to bottom of page (same as social media page)
 * - Added footer layout fix JavaScript
 * - Updated plugin version for cache busting
 *
 * Version 2.17.62 - Fixed keywords card alignment and spacing
 * - Added !important to keywords-card styles to override default card styles
 * - Ensured proper width, max-width, and box-sizing for alignment
 * - Fixed overflow to visible for proper content display
 * - Keywords card now perfectly aligned with project selection card
 * - Updated plugin version for cache busting
 *
 * Version 2.17.61 - Aligned keywords card styling with project selection card
 * - Applied same padding, border-radius, and box-shadow to keywords card
 * - Matched header styling (font size, color, spacing) with project selection card
 * - Removed extra padding from keywords card body
 * - Ensured proper alignment with right column section
 * - Updated plugin version for cache busting
 *
 * Version 2.17.60 - Fixed search button position in RTL mode
 * - Changed HTML order for bank search bar in RTL mode (button first, then input)
 * - Removed flex-direction row-reverse from bank search bar CSS
 * - Search button now appears on the left side in Persian/Farsi version
 * - Updated plugin version for cache busting
 *
 * Version 2.17.59 - Removed Actions column from bank keywords table
 * - Removed Actions column header from bank modal table
 * - Removed Actions column cells from bank table rendering
 * - Removed event handler for add keyword button in bank table
 * - Removed CSS styles for bank table actions column
 * - Updated plugin version for cache busting
 *
 * Version 2.17.58 - Improved modal scrolling and spacing
 * - Reduced modal padding from 40px to 20px (top/bottom)
 * - Removed overflow from modal-body to prevent double scrollbar
 * - Made bank-results table scrollable instead of entire modal body
 * - Improved flex layout for better scroll behavior
 * - Updated plugin version for cache busting
 *
 * Version 2.17.57 - Fixed modal visibility and spacing
 * - Increased modal padding from 24px to 40px (top/bottom) for better visibility
 * - Added max-height to modal dialog to prevent overflow
 * - Made modal body scrollable for long content
 * - Ensured header and footer are always visible
 * - Updated plugin version for cache busting
 *
 * Version 2.17.56 - Reduced font size for bank stats numbers
 * - Reduced font size of bank stats numbers and dates from 18px to 11px
 * - Numbers and dates now appear smaller than their labels for better visual hierarchy
 * - Updated plugin version for cache busting
 *
 * Version 2.17.55 - Added flex-direction for bank modal header
 * - Added bank-modal-header CSS rule with flex-direction: row-reverse
 * - Updated plugin version for cache busting
 *
 * Version 2.17.54 - Added separate class for bank modal header
 * - Added bank-modal-header class to bank modal header
 * - Updated CSS to use specific class instead of generic modal-header
 * - Prevents RTL flex-direction from affecting upload modal header
 * - Updated plugin version for cache busting
 *
 * Version 2.17.53 - Removed inline flex-direction styles from JavaScript
 * - Removed inline flex-direction: row-reverse from modal headers in JavaScript
 * - Removed CSS selectors targeting inline flex-direction styles
 * - Updated plugin version for cache busting
 *
 * Version 2.17.52 - Removed deprecated speak property
 * - Removed all instances of deprecated CSS speak property
 * - Updated plugin version for cache busting
 *
 * Version 2.17.51 - Removed flex-direction from Upload Modal Header
 * - Removed flex-direction: row-reverse from upload modal header CSS
 * - Updated plugin version for cache busting
 *
 * Version 2.17.50 - Removed Inline Styles from Modal Headers
 * - Removed inline flex-direction style from upload modal header
 * - RTL layout now handled entirely by CSS and JavaScript
 * - Updated plugin version for cache busting
 *
 * Version 2.17.49 - Applied RTL Header Layout to Both Modals
 * - Added inline style for bank modal header in RTL mode
 * - Added JavaScript to force flex-direction for bank modal header
 * - Both modals now have reversed headers in RTL mode
 * - Updated plugin version for cache busting
 *
 * Version 2.17.48 - Separated RTL Styles for Each Modal
 * - Separated RTL styles for bank modal and upload modal headers
 * - Each modal now has its own specific RTL header style
 * - Prevents double reverse issue when both modals use same class
 * - Updated plugin version for cache busting
 *
 * Version 2.17.47 - Added JavaScript to Force RTL Header Layout
 * - Added JavaScript to directly set flex-direction on modal header
 * - Ensures header layout is reversed even if CSS doesn't apply
 * - Updated plugin version for cache busting
 *
 * Version 2.17.46 - Strengthened Upload Modal Header RTL Styles
 * - Enhanced CSS selectors for modal header flex-direction
 * - Added attribute selectors for inline styles
 * - Ensured flex properties are properly applied
 * - Updated plugin version for cache busting
 *
 * Version 2.17.45 - Fixed Upload Modal Header Layout in RTL
 * - Swapped title and close button positions in upload modal header for RTL
 * - Added inline style and CSS for flex-direction: row-reverse
 * - Updated plugin version for cache busting
 *
 * Version 2.17.44 - Centered Upload Area in RTL Mode
 * - Centered upload-area box in upload modal for RTL mode
 * - Centered upload-hint text
 * - Updated plugin version for cache busting
 *
 * Version 2.17.43 - Added Inline RTL Styles to Upload Modal
 * - Added inline direction: rtl and text-align: right to upload modal elements
 * - Applied RTL styles directly in PHP based on current language
 * - Ensures RTL works even if CSS doesn't load properly
 * - Updated plugin version for cache busting
 *
 * Version 2.17.42 - Enhanced RTL Direction for Upload Modal
 * - Applied direction: rtl to all elements in upload modal
 * - Strengthened CSS selectors with !important
 * - Fixed text direction for all Persian text in upload modal
 * - Updated plugin version for cache busting
 *
 * Version 2.17.41 - Fixed RTL Direction for Upload Modal Text
 * - Added direction: rtl to modal-body in upload modal
 * - Ensured all text elements have RTL direction
 * - Updated plugin version for cache busting
 *
 * Version 2.17.40 - Added RTL Support for Upload Modal
 * - Removed close button from upload modal footer
 * - Added RTL class to upload modal when page is RTL
 * - Right-aligned and RTL direction for all text in upload modal
 * - Updated plugin version for cache busting
 *
 * Version 2.17.39 - Moved Upload Button to Modal Footer
 * - Removed close button from modal footer (X button in header is sufficient)
 * - Moved upload keywords button from empty state to modal footer
 * - Updated plugin version for cache busting
 *
 * Version 2.17.38 - Improved Empty State Layout in Modal
 * - Removed database icon from empty state
 * - Centered empty state content (title, text, button) in RTL mode
 * - Right-aligned subtitle text with RTL direction
 * - Updated plugin version for cache busting
 *
 * Version 2.17.37 - Fixed Modal Icons and Text Alignment
 * - Fixed dashicons font family in modal empty state and buttons
 * - Right-aligned empty state subtitle text in RTL mode
 * - Applied !important to ensure icon styles override
 * - Updated plugin version for cache busting
 *
 * Version 2.17.36 - Improved RTL Modal Styling
 * - Applied Vazirmatn font to all modal text in RTL mode
 * - Right-aligned placeholder text in search field
 * - Fixed search icon vertical alignment with text
 * - Updated plugin version for cache busting
 *
 * Version 2.17.35 - Fixed RTL Modal Header Layout
 * - Added JavaScript to apply RTL class to modal when page is RTL
 * - Enhanced CSS selectors to ensure modal header reverses correctly
 * - Fixed title and close button positions in RTL mode
 * - Updated plugin version for cache busting
 *
 * Version 2.17.34 - Added RTL Support for Keyword Bank Modal
 * - Swapped title and close button positions in RTL mode
 * - Reversed search bar layout (button left, input right) in RTL
 * - Made table direction RTL for Persian version
 * - Right-aligned empty state text in modal
 * - Updated plugin version for cache busting
 *
 * Version 2.17.33 - Made Keyword Bank Accessible Without Project Selection
 * - Removed project requirement check for opening keyword bank modal
 * - Keyword bank is now accessible regardless of project selection
 * - Updated plugin version for cache busting
 *
 * Version 2.17.32 - Improved Keyword Bank Card Styling
 * - Centered all text in keyword bank card
 * - Made button full width like "Create New Project" button
 * - Improved plus icon alignment and styling
 * - Removed "here" (اینجا) from bank description text
 * - Updated plugin version for cache busting
 *
 * Version 2.17.31 - Updated Keyword Bank Card Layout
 * - Changed bank card structure: title and subtitle at top, button below
 * - Changed button text from "Add from bank" to "Add to bank"
 * - Updated bank description text to include "here" (اینجا)
 * - Updated plugin version for cache busting
 *
 * Version 2.17.30 - Added Empty State for Keyword Research Module
 * - Added empty state card that displays when no project is selected
 * - Keywords card now only shows when a project is selected
 * - Matched behavior with Social Media module
 * - Updated plugin version for cache busting
 *
 * Version 2.17.29 - Fixed Change Project Button Functionality
 * - Added event handler for change-project-btn to return to project selector
 * - Fixed project selection reset when changing project
 * - Updated plugin version for cache busting
 *
 * Version 2.17.28 - Fixed Dashicons in Selected Project Display
 * - Added !important to all dashicons styles in project-badge and change-project-btn
 * - Fixed icon display issues when project is selected
 * - Updated plugin version for cache busting
 *
 * Version 2.17.27 - Enhanced Project Selection Display
 * - Added project-badge with change project button
 * - Updated JavaScript to show/hide project selector and selected project display
 * - Added project name display in selected project section
 * - Updated plugin version for cache busting
 *
 * Version 2.17.26 - Applied Social Media Project Selection Style to Keyword Research
 * - Changed project selection card structure to match Social Media page
 * - Added project-selector with or-divider layout
 * - Applied same styling and animations from Social Media page
 * - Added missing translations (or, select_or_create_project, etc.)
 * - Updated plugin version for cache busting
 *
 * Version 2.17.25 - Added !important to All Dashicons Styles
 * - Added !important to all dashicons CSS properties to ensure they override other styles
 * - Fixed icon display issues by forcing dashicons font-family and display properties
 * - Updated plugin version for cache busting
 *
 * Version 2.17.24 - Enhanced Dashicons Loading and Styling
 * - Added admin_head hook to ensure dashicons loads
 * - Added comprehensive CSS styles for all dashicons elements
 * - Fixed icon display issues for buttons and language selector
 * - Updated plugin version for cache busting
 *
 * Version 2.17.23 - Fixed Dashicons Display Issue
 * - Added explicit dashicons enqueue for keyword research page
 * - Added proper CSS styles for dashicons to ensure they display correctly
 * - Fixed translation icon visibility issue
 * - Updated plugin version for cache busting
 *
 * Version 2.17.22 - Fixed Dashboard Translation and Translation Icon
 * - Changed "SMark Dashboard" to use translation function
 * - Added "داشبورد" translation for Persian language
 * - Fixed translation icon display by adding proper CSS styles
 * - Updated plugin version for cache busting
 *
 * Version 2.17.21 - Moved Upload Keywords Button
 * - Moved "Upload Keywords" button from breadcrumb to action buttons section
 * - Button now appears next to "Add Keywords" button in Project Keywords card
 * - Updated plugin version for cache busting
 *
 * Version 2.17.20 - Updated Keyword Research Page
 * - Changed Persian title from "کیورد ریسرچ" to "تحقیق کلمات کلیدی"
 * - Updated subtitle to use "کلمات کلیدی" instead of "کیورد"
 * - Removed left padding to match Social Media page layout
 * - Applied exact styling from Social Media page
 * - Added footer matching Social Media page design
 * - Fixed font family for RTL mode to use Vazirmatn
 *
 * Version 2.17.19 - Extended Auto-Save to All Fields
 * - Added auto-save functionality for all form fields (visual_text, caption, source, content_link, published_link)
 * - Auto-save now works for any field change, not just headline
 * - Improved field value tracking system
 * - Updated plugin version for cache busting
 *
 * Version 2.17.18 - Fixed Auto-Save Feature Implementation
 * - Fixed headline comparison logic to properly detect changes
 * - Reduced debounce delay from 1.5s to 1s for faster auto-save
 * - Improved trimming of values for accurate comparison
 * - Added check to prevent auto-save for suggestions
 * - Updated plugin version for cache busting
 *
 * Version 2.17.17 - Added Auto-Save Feature to Social Media Item Editing
 * - Implemented live auto-save for headline field when editing items
 * - Added debouncing (1.5 seconds) to prevent excessive save requests
 * - Added visual status indicator for save status (saving/saved/error)
 * - Auto-save only works for existing items, not new items or suggestions
 * - Manual save button still works and closes modal as before
 * - Updated plugin version for cache busting
 *
 * Version 2.17.16 - Updated SEO Footer to Match Social Media
 * - Removed footer action buttons (Reset Progress and Back to Dashboard)
 * - Added version footer matching Social Media design
 * - Updated footer styles to exactly match Social Media footer
 * - Improved page layout with flexbox for proper footer positioning
 *
 * Version 2.17.15 - Fixed SEO Page Font Family
 * - Updated font-family for SEO page to match Social Media exactly
 * - Added Vazirmatn font with system font fallbacks for RTL mode
 * - Applied font-family to all elements in RTL mode for consistency
 *
 * Version 2.17.14 - Matched SEO Header Styles with Social Media
 * - Updated SEO page header styles to exactly match Social Media header
 * - Fixed responsive padding for header (30px 15px on mobile)
 * - Ensured consistent styling across all header elements
 *
 * Version 2.17.13 - Fixed SEO Page Class Application
 * - Removed smark-seo-optimization-page class from body element
 * - Class now only applied to internal wrapper div for proper padding
 * - Updated CSS selectors to use smark-plugin-page for body styles
 * - Improved page layout and padding consistency
 *
 * Version 2.17.12 - Updated SEO Header to Match Social Media
 * - Removed progress wrapper from SEO header to match Social Media design
 * - Added language selector to SEO breadcrumb section
 * - Updated SEO header structure to be identical to Social Media header
 * - Added AJAX handler for language selection in SEO section
 * - Improved consistency across plugin features
 *
 * Version 2.17.11 - Added SEO Optimization Hub Styling
 * - Introduced new SEO Optimization main feature card with localized texts
 * - Built dedicated SEO Optimization Hub page with progress tracking workflow
 * - Matched header and background styling with Social Media designer for consistency
 *
 * Version 2.17.10 - Updated Canva Template Button Behavior
 * - Changed button to open Canva template link in new window instead of filling field
 * - Improved user experience for quick access to Canva templates
 *
 * Version 2.17.9 - Fixed Canva Template Copy Button in Social Media
 * - Changed Canva template retrieval to use project_id instead of project_name
 * - Fixed issue where renamed projects couldn't retrieve their Canva template
 * - Added AJAX handler to get Canva template from Project Management
 * - Canva template now properly retrieved and auto-filled in content link field
 * - Works correctly for all projects including "مانامهاجرت" (PRJ-00001)
 *
 * Version 2.17.8 - Fixed Publication Date Not Saving in Competitor Analysis
 * - Fixed issue where publication date was showing as "Invalid Date" in saved pages
 * - Added data-published-date attribute to store original date format in JavaScript
 * - Enhanced date parsing in PHP to convert from any format to MySQL format
 * - Publication dates now properly saved to database and displayed correctly
 *
 * Version 2.17.7 - Fixed {title} Placeholder in Visual Text GPT Prompt
 * - Added headline/title parameter to Visual Text AJAX request
 * - Added {title} and {headline} placeholder support in Visual Text handler
 * - Visual Text prompts now properly replace {title} with actual item headline
 * - Manual fallback replacement added for {title} and {headline} placeholders
 *
 * Version 2.17.6 - Fixed Placeholder Replacement in GPT Prompts
 * - Added support for both {content} and {source} placeholders
 * - Added backup manual replacement for placeholders that weren't replaced
 * - Enhanced logging to track placeholder replacement status
 * - Fixed issue where {source} placeholder was not being replaced with actual URL
 * - All three GPT buttons now properly replace placeholders before sending to ChatGPT
 *
 * Version 2.17.5 - CRITICAL FIX: GPT Button Variable Name Error
 * - Fixed "Uncaught ReferenceError: selectedProject is not defined" error
 * - Changed selectedProject to selectedProjectName in GPT Title button handler
 * - Fixed same issue in Caption GPT button handler
 * - GPT buttons now work correctly and AJAX requests are sent
 * - Server-side logs will now be properly generated
 *
 * Version 2.17.4 - Enhanced Debug Mode for GPT Feature
 * - Added extensive console logging to track button clicks
 * - Logs source URL, project name, AJAX URL, and nonce values
 * - Enhanced error details in AJAX error handler
 * - Shows complete error response for easier debugging
 * - Makes it easy to identify where the process stops
 *
 * Version 2.17.3 - Comprehensive Logging with SMarkLogger
 * - Integrated SMarkLogger throughout Social Media GPT feature
 * - Added detailed logging to Prompt Bank get_prompt_by_key() method
 * - Logs now saved to features/plugin-logs/logs/smark-plugin.log
 * - Diagnostic checks: table existence, prompt status, total prompts
 * - All errors, warnings, and debug info now properly tracked
 * - Makes it easy to diagnose Prompt Bank connection issues
 *
 * Version 2.17.2 - Social Media: Added Debug Logging for GPT Feature
 * - Added comprehensive error logging to AJAX handler
 * - Added console logging to JavaScript for debugging
 * - Improved error messages to show actual error reason
 * - Added check for Prompt Bank class availability
 * - Better fallback handling with error logging
 *
 * Version 2.17.1 - Social Media: Fixed GPT Button Hanging Issue
 * - Fixed issue where "Create Title with GPT" button would get stuck in loading state
 * - Added 30-second timeout to all GPT-related AJAX requests
 * - Improved popup blocker detection with better error handling
 * - Enhanced error messages for timeout and connection issues
 * - Fixed all three GPT buttons: Title, Visual Text, and Caption
 * - Button now properly resets to original state in all scenarios (success/error/timeout)
 * - Users can now retry if popup blocker prevents window from opening
 *
 * Version 2.17.0 - Competitor Analysis: Added Archived Pages Tab
 * - Added new "Archived" tab to competitor profile modal for reviewed pages
 * - Reviewed pages now automatically move from "Saved Pages" to "Archived Pages" tab
 * - Updated ajax_get_saved_pages() to show only non-reviewed pages (is_reviewed = 0)
 * - Added ajax_get_archived_pages() handler to fetch reviewed pages (is_reviewed = 1)
 * - Enhanced UI: Reviewed pages fade out from saved list with smooth animation
 * - Archived tab displays pages with gray styling indicating reviewed status
 * - Added Persian and English translations for "Archived" and "No archived pages found"
 * - Improved tab switching to support three tabs: New Pages, Saved Pages, Archived Pages
 *
 * Version 2.16.0 - Competitor Analysis: Switched to Project ID-Based System
 * - Changed competitor items filtering from project_name to project_id
 * - Added project_id column to wp_smark_competitor_items table
 * - Implemented automatic project_id sync for existing competitor items
 * - Updated JavaScript to store and use project object {id, name} instead of just name
 * - Fixed issue where items weren't showing after project name changes
 * - Enhanced create_project() to auto-generate project_id for new projects
 * - Updated AJAX handlers to work with project_id instead of project_name
 * - Competitor items now remain linked to projects even if project name changes
 * - Improved robustness and data consistency across the Competitor Analysis feature
 *
 * Version 2.15.4 - Fixed "No project selected" Error When Editing/Adding Items
 * - Fixed JavaScript bug where selectedProject was null instead of using selectedProjectName
 * - Updated all references from deprecated selectedProject to selectedProjectName
 * - Items can now be added and edited without "No project selected" error
 * - Improved project context tracking across Social Media feature
 *
 * Version 2.15.3 - Fixed RTL Breadcrumb Arrow Direction in Project Management
 * - Added CSS transform to flip breadcrumb separator in RTL mode (Persian)
 * - Breadcrumb arrow now shows ‹ (left-pointing) in Persian, matching Social Media page
 * - Improved consistency across all RTL pages
 *
 * Version 2.15.2 - Cache Bust for Recent ID Changes
 * - Bumped SMARK_VERSION to force reload of JS/CSS on admin pages
 * - No functional changes; ensures users receive latest fixes for project_id handling
 *
 * Version 2.15.1 - Enhanced Project ID Auto-Generation and Table Integration
 * - Auto-generates project_id for existing projects without ID
 * - Added project_id column to social_media and social_media_suggestions tables
 * - Automatically populates project_id in existing records based on project_name
 * - New items and suggestions now include project_id when created
 * - Fixed issue where projects showed N/A instead of generated ID
 *
 * Version 2.15.0 - Added Unique Project ID System
 * - Added unique, unchangeable project_id field to all projects (format: PRJ-XXXXX)
 * - Project ID automatically generated for new and existing projects
 * - Project ID displayed in project cards with gradient badge
 * - Project ID shown in project settings modal (read-only field)
 * - Project ID included in project selection dropdowns across features
 * - Project names can now be changed while maintaining consistent project_id
 * - Removed unique constraint from project_name to allow name changes
 * - Updated database schema with project_id column
 * - Enhanced UI/UX with beautiful ID badges and styling
 * - Updated Social Media feature to display project IDs
 *
 * Version 2.14.35 - Enhanced Project Management with Editable Name and Canva Template
 * - Made project name editable in project settings modal
 * - Added Canva Template field to project settings
 * - Stores Canva template link in project database
 * - New AJAX handlers for updating project name and Canva template
 * - Improved project settings UI with input fields
 * - Updated plugin version for cache busting
 *
 * Version 2.14.34 - Added Canva Template Copy Button in Social Media
 * - Removed "Optional. Design reference link or original file." help text
 * - Added "Copy Canva Template" button with official Canva icon
 * - Button shows comprehensive instructions for using Share as Template feature
 * - Beautiful Canva-branded gradient (cyan to purple)
 * - Smart validation to check if link is from Canva
 * - Updated plugin version for cache busting
 *
 * Version 2.14.33 - Fixed Prompt Bank Search Box Width and Background
 * - Reduced search box width to 400px (approximately one-third) for better aesthetics
 * - Fixed background gradient to extend across entire page height
 * - Improved visual consistency and layout design
 * - Enhanced responsive behavior for mobile devices
 * - Updated plugin version for cache busting
 *
 * Version 2.14.32 - Improved Prompt Bank Button Hover Effects and Layout
 * - Removed color change on button hover, kept only upward movement
 * - Moved search box to header replacing title position for better organization
 * - Enhanced layout consistency across all prompt bank buttons
 * - Improved responsive design for mobile devices
 * - Updated plugin version for cache busting
 *
 * Version 2.14.31 - Added Global Variables Section to Prompt Bank
 * - Added global variables documentation box to Prompt Bank page
 * - Standardized variable usage across all prompts ({title}, {content}, {source}, {brand_name}, {language})
 * - Created beautiful UI matching existing design with purple gradient badges
 * - Added bilingual support for variable descriptions
 * - Improved prompt consistency and developer experience
 * - Updated plugin version for cache busting
 *
 * Version 2.14.13 - Added Visual Text GPT Button and Prompt
 * - Added "Create Text with GPT" button below visual text field
 * - Created new prompt in Prompt Bank: social_media_visual_text
 * - New prompt generates compelling text for social media visuals
 * - Button opens ChatGPT with prompt from Prompt Bank
 * - Supports placeholders: {content}, {language}, {brand_name}
 * - Added AJAX handler: SMARK_get_visual_text_prompt
 * - Styled button with ChatGPT green color scheme
 * - Full integration with Prompt Bank for easy editing
 * - Automatic fallback if prompt not found in bank
 *
 * Version 2.14.12 - Fixed Suggestions Table Migration and Plugin Logs Access
 * - Added migrate_suggestions_table() method to ensure all columns exist
 * - Checks and adds missing columns: visual, caption, visual_text, etc.
 * - Fixed "Unknown column 'visual'" error in suggestions update
 * - Fixed Plugin Logs access denied error
 * - Plugin Logs now accessible via hidden submenu (null parent)
 * - Added admin_init hook for proper page access handling
 * - Migration runs automatically on Social Media feature initialization
 *
 * Version 2.14.11 - Enhanced Suggestion Update Debugging
 * - Added existence check before updating suggestion
 * - Added detailed logging for suggestion ID validation
 * - Improved error messages to show if suggestion not found
 * - Better debugging to identify the root cause of update failures
 *
 * Version 2.14.10 - Fixed Suggestion Update Error and Added Debugging
 * - Added error logging for suggestion update operations
 * - Improved error messages to show actual database errors
 * - Added debugging logs for suggestion ID and headline
 * - Fixed error detection in wpdb->update() result checking
 * - Now shows detailed error message instead of generic "Failed to update"
 *
 * Version 2.14.9 - Made Suggestions Editable with Update Functionality
 * - Suggestions are now fully editable (all fields can be modified)
 * - Added update_suggestion AJAX handler for updating suggestions
 * - Changed "Transfer to Items" button to "Update Suggestion" when editing
 * - Suggestions can now be updated directly without transferring to items
 * - Added updateSuggestion() JavaScript function
 * - Form fields remain enabled when viewing/editing suggestions
 * - Added translations for "Update Suggestion" in English and Persian
 * - After update, suggestions table is reloaded to show changes
 *
 * Version 2.14.8 - Fixed Suggestion View and Update Error
 * - Fixed "Error in updating item" when viewing suggestions
 * - Suggestions are now read-only and cannot be edited directly
 * - Added hidden fields for suggestion_id and is_viewing_suggestion
 * - When viewing suggestion, "Update" button changes to "Transfer to Items"
 * - Form fields are disabled (read-only) when viewing suggestions
 * - Clicking save button now transfers suggestion instead of trying to update
 * - Added proper form reset when closing modal
 * - Fixed modal state management between items and suggestions
 *
 * Version 2.14.7 - Integrated Prompt Bank with Social Media Feature
 * - Connected Social Media "Create Attractive Title" button to Prompt Bank
 * - Connected Social Media "Create Title with GPT" button to Prompt Bank
 * - Both features now read prompts from wp_smark_prompt_bank table
 * - Added generate_attractive_title_with_custom_prompt() method to Gemini App
 * - Prompts are now centrally managed and can be edited in Prompt Bank
 * - Automatic fallback to old method if prompt not found in bank
 * - Placeholders {content}, {language}, {brand_name} automatically replaced
 * - Project language automatically detected and converted (fa/en -> Persian/English)
 *
 * Version 2.14.6 - Fixed Prompt Bank Edit Form Population Issue
 * - Fixed modal form not displaying prompt data when editing
 * - Changed form reset logic to occur before AJAX call instead of after
 * - Added console logging for debugging form population
 * - Added null/undefined checks in populateForm function
 * - Modal now shows data correctly when editing existing prompts
 *
 * Version 2.14.5 - Added Prompt Bank Feature and Updated Plugin Logs
 * - Created new Prompt Bank feature for centralized AI prompt management
 * - Added complete CRUD operations for prompts with search functionality
 * - Implemented placeholder system for dynamic prompt values
 * - Added three default prompts for social media, headline analysis, and content summarization
 * - Created beautiful UI matching Social Media feature design
 * - Moved Plugin Logs from sidebar menu to Basic Features section in dashboard
 * - Added Prompt Bank card to Basic Features section
 * - Added page header to Prompt Bank (matching Social Media style)
 * - Full RTL support and bilingual translations (English/Persian)
 * - Database table wp_smark_prompt_bank with comprehensive fields
 * - Static method get_prompt_by_key() for easy integration with other features
 *
 * Version 2.14.4 - Synchronized GPT Button with Attractive Title Prompt
 * - Created shared prompt generation system between GPT button and attractive title button
 * - Added get_attractive_title_prompt() method in Gemini App for consistent prompt generation
 * - Implemented AJAX endpoint to get the exact same prompt used in title generation
 * - Updated GPT button to use server-side prompt generation instead of client-side
 * - Ensured both buttons use identical prompts with same language and project settings
 * - Added proper error handling and validation for prompt generation
 *
 * Version 2.14.2 - Fixed GPT Button Auto-fill Functionality
 * - Added comprehensive ChatGPT auto-fill script that runs on ChatGPT page
 * - Implemented localStorage-based prompt sharing between windows
 * - Enhanced cross-origin communication with postMessage API
 * - Added automatic send button triggering after prompt filling
 * - Improved selector targeting for different ChatGPT interface versions
 * - Fixed prompt auto-fill to work reliably across different browsers
 *
 * Version 2.14.1 - Enhanced GPT Button Functionality
 * - Updated GPT button to open https://chatgpt.com/ instead of chat.openai.com
 * - Added automatic prompt filling in ChatGPT input field
 * - Enhanced cross-origin communication to fill textarea automatically
 * - Added multiple selector fallbacks for different ChatGPT interface versions
 * - Improved user experience with automatic text insertion
 *
 * Version 2.14.0 - Added GPT Button to Social Media Feature
 * - Added "ساخت عنوان با GPT" (Create Title with GPT) button next to existing attractive title button
 * - Button opens ChatGPT in new tab with headline text for easy copying
 * - Added proper styling with ChatGPT's signature green color scheme
 * - Enhanced user experience with loading states and notifications
 * - Updated plugin version for cache busting
 *
 * Version 2.13.99 - Fixed MAX_TOKENS Error in Gemini API
 * - Fixed "Invalid response from Gemini" error caused by MAX_TOKENS finish reason
 * - Reduced maxOutputTokens from 1024 to 512 to prevent token limit issues
 * - Added MAX_TOKENS handling with retry mechanism using shorter prompts
 * - Shortened Gemini prompts to reduce token usage
 * - Enhanced response parsing for different Gemini API response structures
 * - Added comprehensive logging for MAX_TOKENS scenarios
 *
 * Version 2.13.98 - Moved Logs to Plugin Directory
 * - Moved log files from wp-content to plugin directory
 * - Created logs directory inside SMark Plugin folder
 * - Added security protection for logs directory (.htaccess, index.php)
 * - Enhanced log file information display with size and modification date
 * - Improved log management interface with better file info
 * - Added README documentation for logs directory
 *
 * Version 2.13.97 - Added Dedicated Plugin Logging System
 * - Created SMarkLogger class for comprehensive plugin logging
 * - Added dedicated log file in wp-content/smark-logs/
 * - Enhanced Gemini API error logging and debugging
 * - Added plugin logs management interface in Gemini App
 * - Improved error tracking and troubleshooting capabilities
 * - Added log viewing, refreshing, and clearing functionality
 *
 * Version 2.13.96 - Enhanced Gemini Connection and Debugging
 * - Added comprehensive debugging for Gemini API calls
 * - Fixed global instance usage in social media feature
 * - Added test function for Gemini connection
 * - Enhanced error logging and response structure analysis
 * - Improved connection between social media and Gemini App
 *
 * Version 2.13.95 - Fixed Gemini Response Parsing for Title Generation
 * - Fixed "Invalid response from Gemini" error in social media title generation
 * - Improved response parsing logic with better structure handling
 * - Added safety filter checks before response parsing
 * - Enhanced error handling for API responses and empty candidates
 * - Removed duplicate response parsing conditions
 * - Added comprehensive debugging for response structure analysis
 * - Fixed connection between social media and Gemini App features
 *
 * Version 2.13.94 - Added Project Language Support to Title Generation
 * - Integrated project language from project management feature
 * - Updated Gemini prompt to generate titles in project language
 * - Added get_project_language method to retrieve brand language
 * - Enhanced title generation to respect project language settings
 * - Improved multilingual support for title generation
 *
 * Version 2.13.93 - Enhanced Title Generation Behavior in Social Media
 * - Changed title generation to append new titles instead of replacing existing ones
 * - Added "عنوان جدید:" prefix for newly generated titles
 * - Improved user experience by preserving existing content
 * - Enhanced title field management with proper line breaks
 *
 * Version 2.13.92 - Fixed Social Media Gemini App Integration
 * - Added proper class loading for SMarkGeminiApp in social media feature
 * - Fixed missing class dependency issue in title generation
 * - Ensured proper integration between social media and Gemini App features
 * - Resolved "Class not found" error in title generation functionality
 *
 * Version 2.13.91 - Removed All Console Logs from Social Media Feature
 * - Removed all 132 browser logging statements
 * - Cleaned up debugging code from production JavaScript file
 * - Improved browser console performance
 * - Reduced JavaScript file noise in developer tools
 *
 * Version 2.13.90 - Enhanced Gemini Response Parsing for Better Compatibility
 * - Added support for multiple Gemini response structures
 * - Enhanced error handling for different API response formats
 * - Added safety filter detection and handling
 * - Improved debugging with detailed response logging
 * - Fixed "Invalid response from Gemini" error in title generation
 *
 * Version 2.13.89 - Fixed Gemini Prompt Engineering and URL-Based Title Generation
 * - Redesigned prompt with professional engineering structure
 * - Changed from content extraction to direct URL analysis by Gemini
 * - Added structured requirements and clear output format
 * - Enhanced generation config with optimal parameters
 * - Improved title generation quality with better prompting
 *
 * Version 2.13.88 - Applied Exact Social Media Page Structure to Competitor Analysis
 * - Applied exact CSS flexbox structure from social media page
 * - Set display: flex and flex-direction: column on main page wrapper
 * - Applied same padding and layout structure as social media page
 * - Fixed JavaScript to work with proper flexbox structure
 * - Footer now properly positions at bottom using margin-top: auto
 *
 * Version 2.13.87 - Fixed Competitor Analysis Footer Positioning
 * - Added exact CSS rule for .smark-competitor-analysis-page.wrap padding
 * - Fixed HTML structure to match social media page exactly
 * - Corrected JavaScript to target proper content wrapper
 * - Removed unnecessary smark-main-content wrapper
 * - Fixed sticky footer positioning to work properly
 *
 * Version 2.13.86 - Fixed Competitor Analysis Page Structure and Footer
 * - Applied social media page HTML structure to competitor analysis page
 * - Added smark-main-content wrapper for proper flexbox layout
 * - Enhanced CSS rules to hide WordPress footer and set proper background
 * - Fixed sticky footer positioning to match social media page behavior
 * - Improved page layout consistency across all plugin features
 *
 * Version 2.13.85 - Fixed Competitor Analysis Page Layout
 * - Override WordPress admin padding-left for competitor analysis page
 * - Set padding-left to 0px !important to allow full-width layout
 * - Enhanced page styling control for better user experience
 *
 * Version 2.13.84 - Added Footer to Competitor Analysis Page
 * - Implemented footer design similar to social media page
 * - Added sticky footer functionality for competitor analysis page
 * - Enhanced layout with proper flexbox positioning
 * - Added version information display in footer
 * - Improved page layout consistency across plugin features
 *
 * Version 2.13.83 - Enhanced Project Management Brand Language Feature
 * - Added brand language selection dropdown in project settings modal
 * - Implemented AJAX functionality to save project brand language preferences
 * - Added database field for storing brand language per project
 * - Enhanced JavaScript with proper error handling and debugging
 * - Added RTL support and improved button styling
 *
 * Version 2.13.82 - Updated Plugin Version for Cache Busting
 * - Increased version number to prevent caching issues after translation updates
 * - Fixed Persian translation for "SMark Plugin" footer text in project management
 *
 * Version 2.13.79 - Improved Gemini Prompt Engineering for Title Generation
 * - Redesigned prompt structure with professional engineering approach
 * - Changed from sending extracted content to sending URL for Gemini analysis
 * - Added structured requirements and clear output format specifications
 * - Enhanced generation config with optimal temperature and token settings
 * - Improved prompt clarity and specificity for better title generation
 *
 * Version 2.13.78 - Refactored Social Media to Use Gemini App Feature
 * - Moved title generation logic from social media to Gemini App feature
 * - Social media now calls Gemini App method instead of direct API calls
 * - Centralized Gemini API configuration in Gemini App feature
 * - Improved architecture with proper separation of concerns
 * - All Gemini interactions now go through Gemini App with its settings
 *
 * Version 2.13.77 - Enhanced Social Media Gemini API Debugging
 * - Added comprehensive error logging for Gemini API responses
 * - Fixed model selection to use dynamic model from settings
 * - Enhanced error handling with detailed response structure logging
 * - Improved debugging capabilities for API connection issues
 *
 * Version 2.13.76 - Fixed Social Media Attractive Title Gemini App Integration
 * - Fixed API key configuration issue in social media attractive title generation
 * - Added automatic saving of generated titles to Gemini App database
 * - Integrated social media plugin with Gemini App record creation
 * - Resolved "API Key not configured" error in social media feature
 * - Added save_to_gemini_app() method for proper database integration
 *
 * Version 2.13.75 - Fixed Gemini App Page Class Duplication
 * - Removed smark-gemini-app-page class from body element to prevent duplication
 * - Class now only applies to wrap element for better semantic structure
 * - Updated JavaScript to handle class detection on wrap element instead of body
 * - Improved code maintainability and eliminated redundant styling
 *
 * Version 2.13.74 - Removed Padding from Gemini App Page
 * - Removed padding: 20px from .smark-gemini-app-page CSS rule
 * - Improved page layout spacing and alignment
 *
 * Version 2.13.73 - Fixed Gemini App Breadcrumb CSS to Match Social Media Style
 * - Updated breadcrumb CSS structure to exactly match Social Media feature
 * - Fixed language selector positioning and styling
 * - Ensured consistent breadcrumb layout between Gemini and Social Media features
 * - Improved visual consistency across all SMark Plugin pages
 *
 * Version 2.13.72 - Fixed Gemini App Breadcrumb Implementation
 * - Removed JavaScript-based breadcrumb manipulation in gemini-app.js
 * - Implemented breadcrumb directly in PHP matching Social Media feature style
 * - Added language selector to breadcrumb-right section
 * - Fixed breadcrumb structure with breadcrumb-left and breadcrumb-right divs
 * - Improved code maintainability and consistency across features
 *
 * Version 2.13.6 - Fixed JavaScript Global Issues
 * - Limited all JavaScript files to only run on their respective pages
 * - Fixed $(document).ajaxSuccess that was affecting all WordPress AJAX calls
 * - Limited $(document).on event handlers to specific pages
 * - Resolved WP Rocket cache clearing and other admin functionality issues
 *
 * Version 2.13.5 - Fixed Global CSS Issues
 * - Removed global CSS overrides that were affecting all WordPress pages
 * - Fixed #wpcontent, .wrap, #wpfooter, and .notice CSS overrides
 * - Limited admin.js to only affect SMark pages
 * - Resolved WP Rocket cache clearing and other admin issues
 *
 * Version 2.13.4 - Temporarily Disabled Keyword Research
 * - Temporarily disabled Keyword Research feature to test layout issues
 * - Investigating potential conflicts with WordPress admin layout
 * - Testing if Keyword Research is causing dashboard problems
 *
 * Version 2.13.3 - Fixed Global Padding Issue
 * - Removed #wpcontent { padding: 0 !important; } from social media and competitor analysis
 * - Fixed excessive top spacing affecting all WordPress admin pages
 * - Removed unnecessary CSS overrides that caused layout issues
 * - Restored proper WordPress admin layout spacing
 *
 * Version 2.13.2 - Override WordPress Admin Bar Height
 * - Added CSS override for --wp-admin--admin-bar--height: 15px !important
 * - Added scroll-padding-top override for better layout
 * - Enhanced page positioning and admin bar compatibility
 *
 * Version 2.13.1 - Improved Social Media Page Layout
 * - Added padding-top: 15px !important to social media page
 * - Enhanced page visibility and layout positioning
 * - Improved user experience for social media feature
 *
 * Version 2.13.0 - Fixed Items and Suggestions Loading
 * - Added JSON parsing for items and suggestions AJAX responses
 * - Fixed "Failed to load suggestions: Unknown error" issue
 * - Enhanced error handling for all AJAX calls in social media
 * - Resolved table rendering issues for project data
 *
 * Version 2.12.9 - Fixed JSON Parsing with String Cleaning
 * - Added response string cleaning with trim() before JSON parsing
 * - Fixed "Unexpected token" error in JSON.parse
 * - Enhanced error logging for debugging malformed responses
 * - Resolved SyntaxError in project loading completely
 *
 * Version 2.12.8 - Fixed JSON Response Parsing
 * - Added automatic JSON parsing for string responses
 * - Fixed "Response type: string" issue in project loading
 * - Enhanced error handling for JSON parsing failures
 * - Resolved dropdown loading issue completely
 *
 * Version 2.12.7 - Fixed Response Parsing Logic
 * - Enhanced response parsing with comprehensive debugging
 * - Added detailed logging for response structure analysis
 * - Improved error handling for different response formats
 * - Fixed "Invalid response format" error in project loading
 *
 * Version 2.12.6 - Enhanced JavaScript Debugging for Project Loading
 * - Added comprehensive debugging to identify why loadProjects() is not executing
 * - Enhanced console logging to track JavaScript initialization
 * - Added checks for smarkSocialMedia object availability
 * - Improved troubleshooting for AJAX call issues
 *
 * Version 2.12.5 - Fixed Project Dropdown Response Handling
 * - Fixed JavaScript response parsing for project loading
 * - Added support for multiple response formats (direct array, success/data structure)
 * - Resolved issue where projects were not displaying in dropdown despite successful AJAX
 * - Cleaned up debug code and optimized implementation
 *
 * Version 2.12.4 - Enhanced Debugging for Project Loading
 * - Added comprehensive debugging to identify project loading issues
 * - Enhanced error logging for AJAX requests and database queries
 * - Added automatic test project creation for troubleshooting
 * - Improved JavaScript console logging for better diagnostics
 *
 * Version 2.12.3 - Fixed Initialization Order Issue
 * - Fixed plugin initialization order to prevent conflicts
 * - SMarkSocialMedia now initializes before SMarkKeywordResearch
 * - Ensures projects table is created by Social Media feature first
 * - Removed debug code and cleaned up implementation
 *
 * Version 2.12.2 - Enhanced Project Loading with Auto-Creation
 * - Added automatic test project creation when no projects exist
 * - Enhanced database table existence checks
 * - Improved error handling and logging for project loading
 *
 * Version 2.12.1 - Debug Social Media Project Loading Issue
 * - Added comprehensive debugging to project loading functionality
 * - Enhanced error logging for AJAX project requests
 * - Added table existence checks and automatic table creation
 * - Improved JavaScript console logging for troubleshooting
 *
 * Version 2.12.0 - Added Keyword Research feature
 * - New Keyword Research workspace with project selection and keyword bank integration
 * - Bulk CSV/XLS(X) uploads with automatic parsing and database import
 * - Modal-driven keyword assignment from the central bank to per-project tables
 * - Dashboard card added under Main Features with Live status styling
*
 * Version 2.11.0 - Enhanced Social Media Page and Fixed Action Buttons
 * - Added plugin version footer to social media page (matching main dashboard style)
 * - Removed excessive empty space at bottom of social media page
 * - Fixed page height to auto instead of 100vh to eliminate unnecessary white space
 * - Enhanced RTL action buttons alignment in suggestions table
 * - Improved overall page layout and user experience
 *
 * Version 2.10.24 - Fixed Social Media Page Layout and Footer
 * - Added plugin version footer to social media page (matching main dashboard style)
 * - Removed excessive empty space at bottom of social media page
 * - Fixed page height to auto instead of 100vh to eliminate unnecessary white space
 * - Improved overall page layout and user experience
 *
 * Version 2.10.23 - Fixed Action Buttons Alignment in RTL Mode
 * - Fixed action buttons (View, Transfer, Delete) alignment in operations column
 * - Buttons now properly align to the right side of the column in Persian/RTL mode
 * - Improved visual consistency for Persian language users
 *
 * Version 2.10.22 - Fixed Source Field URL Display Issue
 * - Fixed competitor analysis to send full page URL instead of website name
 * - Source field now displays complete page URL (e.g., https://example.com/page) instead of just domain name
 * - Improved data accuracy when transferring items from competitor analysis to social media
 *
 * Version 2.10.20 - Source Field Integration Completed
 * - Confirmed source field is fully integrated across all components:
 *   - Database tables (both items and suggestions)
 *   - Modal interface with readonly field and help text
 *   - JavaScript handling for form population and submission
 *   - AJAX handlers for add/update operations
 *   - Transfer logic from Competitor Analysis to Social Media
 * - Source field automatically populated when transferring from Competitor Analysis
 * - Complete translation support in both English and Persian
 * - Updated plugin version for cache busting
 *
 * Version 2.10.19 - Fixed Button Labels and Added Color Coding
 * - Fixed "undefined" button labels by adding missing strings to JavaScript localization
 * - Added color coding for suggestion action buttons:
 *   - Blue for "View" (Ù…Ø´Ø§Ù‡Ø¯Ù‡) button
 *   - Green for "Transfer to Items" (Ø§Ù†ØªÙ‚Ø§Ù„ Ø¨Ù‡ Ø¢ÛŒØªÙ…â€ŒÙ‡Ø§) button
 *   - Red for "Delete" (Ø­Ø°Ù) button
 * - Enhanced button styling with gradient backgrounds and hover effects
 * - Updated plugin version for cache busting
 *
 * Version 2.10.18 - Enhanced Project Suggestions Functionality
 * - Localized "View" button text to Persian
 * - Added popup functionality for viewing suggestion details using same modal as main items
 * - Added "Transfer to Items" and "Delete" buttons for suggestions
 * - Implemented AJAX handlers for suggestion operations (view, transfer, delete)
 * - Added comprehensive translation support for all new suggestion features
 * - Updated plugin version for cache busting
 *
 * Version 2.10.17 - Fixed Notification Z-Index Issue
 * - Increased notification z-index to 9999999 to display above modals
 * - Reduced modal overlay z-index to 999998 to prevent conflicts
 * - Fixed notification visibility issue in both social media and competitor analysis features
 * - Updated plugin version for cache busting
 *
 * Version 2.10.16 - Fixed Table Creation and Enhanced Error Handling
 * - Fixed table creation with IF NOT EXISTS clause
 * - Added automatic table existence check on admin init
 * - Enhanced nonce verification with better error messages
 * - Added comprehensive POST data logging
 * - Updated plugin version for cache busting
 *
 * Version 2.10.15 - Enhanced Server-Side Debugging for Send to Social Media
 * - Added comprehensive server-side debug logging for send to social media functionality
 * - Added table existence check before database insert
 * - Enhanced error tracking for page and competitor item retrieval
 * - Updated plugin version for cache busting
 *
 * Version 2.10.14 - Enhanced Send to Social Media Debugging
 * - Added comprehensive debug logging to JavaScript for send to social media functionality
 * - Improved error handling and response logging
 * - Added project name validation and logging
 * - Updated plugin version for cache busting
 *
 * Version 2.10.13 - Fixed Send to Social Media Functionality
 * - Added debug logging to competitor analysis send to social media feature
 * - Fixed AJAX response handling for better error tracking
 * - Updated plugin version for cache busting
 *
 * Version 2.10.12 - Fixed Title Alignment in Persian RTL Version
 * - Removed flex-direction: row-reverse from card headers to properly align titles to right
 * - Removed unnecessary order properties for cleaner CSS
 * - Updated plugin version for cache busting
 *
 * Version 2.10.11 - Fixed CSS Issues in Social Media Feature
 * - Removed overflow: hidden from .right-column to prevent content clipping
 * - Fixed title alignment for card headers in Persian RTL version
 * - Updated plugin version for cache busting
 *
 * Version 2.10.10 - Improved Project Suggestions Section UI
 * - Added proper spacing between Project Items and Project Suggestions sections
 * - Fixed title alignment for Project Suggestions section (right-aligned in Persian)
 * - Localized "No suggestions found" text to Persian
 * - Updated plugin version for cache busting
 *
 * Version 2.10.9 - Added Project Suggestions Section to Social Media Feature
 * - Added new "Project Suggestions" section below "Project Items" in Social Media feature
 * - Styled exactly like the existing "Project Items" section
 * - Added bilingual translations (English/Persian) for new section
 * - Allows jumping to suggestions from other features
 * - Updated plugin version for cache busting
 *
 * Version 2.10.8 - Final Table Layout Adjustments Based on User Feedback
 * - Added center alignment for ID column in competitors table
 * - Removed white-space: nowrap from all saved pages table columns for better text flow
 * - Applied width: 100% to saved pages table for full width utilization
 * - Removed max-width: 80px constraints from date and operations columns
 * - Removed text-align: center from saved pages table columns for natural alignment
 * - Improved table responsiveness and content display
 *
 * Version 2.10.7 - Separated Table Classes for Better CSS Management
 * - Renamed main competitors table class from "data-table" to "competitors-table"
 * - Renamed new pages table class from "data-table" to "new-pages-table"
 * - Renamed saved pages table class from "data-table" to "saved-pages-table"
 * - Updated all CSS selectors to use specific table class names
 * - Improved CSS organization and eliminated conflicts between different tables
 * - Each table now has its own dedicated styling without interference
 *
 * Version 2.10.6 - Updated Plugin Version Constant for Cache Busting
 * - Updated SMARK_VERSION constant from 2.9.9 to 2.10.5 to match current plugin version
 * - Ensures CSS and JS files are properly refreshed and not served from cache
 * - Fixes potential caching issues that could prevent table layout changes from appearing
 *
 * Version 2.10.5 - Final Table Layout Optimization
 * - Confirmed removal of width: 100% from .data-table for proper table sizing
 * - Verified max-width: 100px for page-title column implementation
 * - Ensured white-space: nowrap removal from title column for better text flow
 * - Final optimization of table column spacing in competitor analysis saved pages
 *
 * Version 2.10.4 - Fixed Table Width Issue and Optimized Title Column
 * - Removed width: 100% from .data-table to prevent forced table expansion
 * - Set max-width: 100px for #saved_pages_results .page-title for compact title display
 * - Removed white-space: nowrap from page-title to allow text wrapping if needed
 * - Fixed table layout issues that were causing excessive column spacing
 * - Improved table responsiveness and space utilization
 *
 * Version 2.10.3 - Direct Table Cell Targeting with Max-Width Constraints
 * - Added direct targeting of tr and td elements in saved pages table using nth-child selectors
 * - Set specific max-width for each column: title (200px), type (50px), dates (80px), URL (150px), operations (80px)
 * - Applied white-space: nowrap and text-align: center for compact columns
 * - Added overflow: hidden and text-overflow: ellipsis for URL column
 * - Changed table-layout to auto to respect max-width constraints
 * - Eliminated excess spacing by directly controlling each table cell
 *
 * Version 2.10.2 - Ultra-Compact Table Layout with Precise Column Widths
 * - Set precise fixed widths for all table columns to eliminate excess spacing
 * - Optimized column widths: type (50px), dates (80px), operations (80px), URL (150px), title (200px)
 * - Reduced padding to 8px 4px for all cells to minimize internal spacing
 * - Decreased overall table minimum width from 600px to 500px
 * - Applied table-layout: fixed for consistent column sizing
 * - Eliminated all unnecessary white space between and within columns
 *
 * Version 2.10.1 - Dynamic Column Sizing for Better Space Utilization
 * - Changed table layout from fixed to auto for dynamic column sizing
 * - Replaced fixed widths with max-widths to allow columns to shrink based on content
 * - Optimized column sizing: operations (max-width: 100px), dates (max-width: 90px), type (max-width: 70px)
 * - Increased URL column max-width to 200px and title to 300px for better content display
 * - Reduced overall table minimum width from 800px to 600px
 * - Added white-space: nowrap to prevent text wrapping in compact columns
 * - Improved space efficiency by allowing columns to fit their content exactly
 *
 * Version 2.10.0 - Optimized Table Column Spacing in Competitor Analysis
 * - Reduced excessive spacing between table columns in saved pages section
 * - Decreased table cell padding from 16px to 8px 4px for more compact layout
 * - Optimized column widths: operations (120pxâ†’100px), URL (180pxâ†’150px), title (300pxâ†’250px)
 * - Reduced date columns width from 100px to 90px and type column from 80px to 70px
 * - Decreased overall table minimum width from 1000px to 800px
 * - Improved table readability and space efficiency
 *
 * Version 2.9.9 - Fixed Button Labels and Table Layout Issues
 * - Fixed button labels showing "undefined" instead of proper text
 * - Changed operations buttons layout from horizontal to vertical (stacked)
 * - Optimized table column widths to prevent horizontal scrolling
 * - Fixed page title and URL column widths with proper text truncation
 * - Improved table layout with fixed table-layout for better control
 * - Enhanced user experience with proper button sizing and spacing
 * - Fixed JavaScript translation issues for button labels
 *
 * Version 2.9.8 - Added Operations Column and Social Media Integration
 * - Added operations column to saved pages table with "Mark as Reviewed" and "Send to Social" buttons
 * - Implemented page review functionality with visual feedback (grayed out text for reviewed pages)
 * - Added integration with social media feature to send competitor pages as suggestions
 * - Updated database schema to track page review status and timestamps
 * - Added new AJAX endpoints for marking pages as reviewed and sending to social media
 * - Enhanced user experience with proper button states and loading indicators
 * - Added comprehensive translations for new functionality in both English and Persian
 * - Created social media suggestions table for storing competitor analysis suggestions
 *
 * Version 2.9.7 - Cleaned Up Redundant UI Elements in Competitor Profile
 * - Removed redundant "Saved Pages" button from saved pages tab
 * - Removed redundant "Saved Pages" header from table
 * - Simplified user interface by removing unnecessary elements
 * - Improved user experience with cleaner, more focused design
 * - Pages now load automatically when switching to saved pages tab
 * - Removed redundant CSS and JavaScript code
 *
 * Version 2.9.6 - Added Saved Pages Section to Competitor Profile
 * - Added new tab system to competitor profile modal with "New Pages" and "Saved Pages" tabs
 * - Created AJAX endpoint to retrieve saved pages for each competitor
 * - Added comprehensive saved pages display with discovery date information
 * - Enhanced user experience by allowing users to view previously saved pages
 * - Added proper tab navigation with smooth transitions
 * - Implemented empty state for when no pages are saved
 * - Added new translations for saved pages functionality in both English and Persian
 * - Fixed issue where users couldn't see what pages were previously saved
 *
 * Version 2.9.5 - Fixed Multiple Fetch Issue in Competitor Analysis
 * - Fixed issue where websites could only be fetched once
 * - Removed automatic filtering of already saved pages during fetch
 * - Users can now fetch pages multiple times from the same competitor website
 * - Improved save logic to only save new pages (duplicates are handled during save)
 * - Enhanced user experience with better notification messages
 * - Added new translation for "all pages already saved" in both English and Persian
 * - Fixed blue notification box appearing when no new content is available
 *
 * Version 2.9.4 - Fixed Competitor Analysis Notification Logic
 * - Fixed blue notification box appearing even when no pages are found
 * - Modified fetch pages logic to only show success notification when pages are actually found
 * - Updated PHP AJAX handler to return appropriate response based on whether pages were found
 * - Improved handling of no results case to not show misleading success messages
 * - Fixed page saving logic to only save when user explicitly clicks "Save Pages" button
 * - Added proper database field mapping for page type and published date
 * - Enhanced JavaScript to capture and send page type information when saving
 * - Added new translation keys for "no new pages found" in both English and Persian
 *
 * Version 2.9.3 - Fixed JavaScript Breaking Event Handlers
 * - Removed problematic HTML replacement code that was breaking event handlers
 * - Fixed project selection button functionality
 * - Simplified undefined text fix to only target text nodes
 * - Removed CSS overlay solution that could interfere with UI
 *
 * Version 2.9.2 - Direct Text Replacement Solution for Undefined
 * - Added CSS solution to overlay Persian text on undefined values
 * - Implemented direct HTML text replacement for all undefined occurrences
 * - Added text node replacement for dynamic content
 * - Simplified JavaScript approach with global text replacement
 *
 * Version 2.9.1 - Enhanced Undefined Text Fix with Multiple Solutions
 * - Added comprehensive JavaScript function to fix undefined text in real-time
 * - Enhanced database cleanup function to handle all undefined variations
 * - Added fallback text handling for translation failures
 * - Implemented multiple timing strategies to ensure undefined text is replaced
 * - Added error logging for database cleanup operations
 *
 * Version 2.9.0 - Fixed Undefined Website URL Display in Persian
 * - Fixed "undefined" text appearing in Persian competitor analysis table
 * - Added comprehensive undefined value handling in JavaScript and PHP
 * - Implemented database cleanup function to convert existing undefined values to NULL
 * - Enhanced website URL validation to properly display Persian "ØªØ¹Ø±ÛŒÙ Ù†Ø´Ø¯Ù‡" instead of "undefined"
 * - Improved data sanitization for both website_url and website_name fields
 *
 * Version 2.8.9 - Hidden WP Rocket Cache Notifications
 * - Added CSS rules to hide WP Rocket "Cache cleared" notifications on all SMark Plugin pages
 * - Implemented JavaScript solution to automatically remove WP Rocket notifications
 * - Enhanced user experience by removing distracting cache notifications from plugin interface
 * - Applied changes to all feature pages: Competitor Analysis, Social Media, Headline Analyzer, Gemini App, and Google Docs Converter
 *
 * Version 2.8.8 - Enhanced Undefined Value Handling in Competitor Analysis
 * - Improved PHP backend to clean up undefined/null values from database
 * - Enhanced JavaScript handling for null, undefined, and empty values
 * - Added comprehensive null checking in get_item and get_project_items functions
 * - Fixed persistent "undefined" text appearing in Persian interface
 * - Better data sanitization before sending to frontend
 *
 * Version 2.8.7 - Fixed Persian Translation for Undefined Website URLs
 * - Fixed "undefined" text appearing in Persian competitor analysis table
 * - Added proper Persian translation "ØªØ¹Ø±ÛŒÙ Ù†Ø´Ø¯Ù‡" for undefined website URLs
 * - Added English translation "Not defined" for undefined website URLs
 * - Improved JavaScript handling of null/undefined website URL values
 * - Enhanced user experience in Persian language interface
 *
 * Version 2.8.6 - Critical Bug Fix for Save Pages Functionality
 * - Fixed sanitize_url() function error that prevented saving pages
 * - Replaced non-existent sanitize_url() with WordPress standard esc_url_raw()
 * - Resolved red error notification box issue in Competitor Analysis
 * - Pages now save correctly to database when clicking "Save Pages" button
 * - Fixed AJAX error that caused save operations to fail silently
 *
 * Version 2.8.5 - Fixed Persian Language Issues and Save Functionality
 * - Fixed "undefined" text appearing in Persian interface
 * - Added proper Persian translations for "Post", "Page", and "N/A"
 * - Fixed save pages functionality with improved error handling
 * - Added debug logging for troubleshooting save operations
 * - Enhanced URL extraction from table rows for better data collection
 * - Improved error messages in both English and Persian
 *
 * Version 2.8.4 - Enhanced Competitor Profile with Save Functionality
 * - Changed "Competitor Profile" title to "Profile" for better UX
 * - Added "Save Pages" button to store fetched pages in database
 * - Implemented duplicate page prevention in save functionality
 * - Enhanced modal footer with save button that appears after fetching
 * - Added comprehensive save pages AJAX handler with validation
 * - Improved user feedback with success/error messages for save operations
 *
 * Version 2.8.3 - Updated Competitor Analysis Button and Functionality
 * - Changed "Fetch" button to "Competitor Profile" button
 * - Updated modal title and functionality to show competitor profile
 * - Enhanced duplicate page prevention in database
 * - Improved user experience with better button labeling
 *
 * Version 2.8.2 - Fixed Language Selector and Button Styling
 * - Fixed language selector styling to match Social Media feature exactly
 * - Updated language dropdown appearance and hover effects
 * - Ensured consistent button styling across all features
 *
 * Version 2.8.1 - Updated Competitor Analysis UI Styling
 * - Updated Competitor Analysis page header and breadcrumb styling to match Social Media feature
 * - Improved card layouts and grid system for better consistency
 * - Enhanced project selection card styling with proper spacing and shadows
 * - Updated empty state card design to match Social Media feature
 * - Improved RTL support for all card components
 * - Enhanced responsive design for mobile devices
 * - Updated notification system styling for better user experience
 *
 * Version 2.8.0 - Added Competitor Analysis Feature
 * - Added new Competitor Analysis feature to track competitor websites
 * - Implemented project management system for organizing competitors
 * - Added content fetching functionality (RSS feeds and sitemaps)
 * - Created time range selection for content discovery
 * - Added comprehensive UI with RTL support and bilingual interface
 * - Implemented database tables for competitor items and fetched content
 * - Added AJAX endpoints for all competitor management operations
 * - Updated main dashboard to include Competitor Analysis feature card
 *
 * Version 2.7.0 - Fixed score update issue in social media items
 * - Fixed updateItem function to properly extract and save score from analysis results
 * - Updated PHP backend to handle score parameter in item updates
 * - Score now properly updates in database when re-analyzing and saving items
 */

// Prevent direct access (using WPINC instead of ABSPATH)
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SMARK_CANONICAL_PLUGIN_BASENAME')) {
    define('SMARK_CANONICAL_PLUGIN_BASENAME', 'smark/smark.php');
}

if (
    function_exists('plugin_basename') &&
    plugin_basename(__FILE__) !== SMARK_CANONICAL_PLUGIN_BASENAME &&
    file_exists(dirname(__DIR__) . '/' . SMARK_CANONICAL_PLUGIN_BASENAME)
) {
    if (function_exists('deactivate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__), true);
    }
    return;
}

// Define plugin constants
define('SMARK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMARK_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SMARK_VERSION', '1.0.8');
define('SMARK_PLUGIN_FILE', __FILE__);

require_once SMARK_PLUGIN_PATH . 'includes/class-smark-github-updater.php';

if (is_admin() && class_exists('SMark_GitHub_Updater', false)) {
    new SMark_GitHub_Updater(SMARK_PLUGIN_FILE, SMARK_VERSION, 'saeedhasani', 'SMark');
}

/**
 * Main SMark Plugin Class
 */
class SMarkPlugin {
    const CAP_ACCESS = 'smark_access';
    const CAP_MANUAL_AI = 'smark_manual_ai_access';
    const OPTION_MANUAL_AI_ENABLED = 'smark_manual_ai_enabled';
    const OPTION_MANUAL_AI_ALLOWED_USER_IDS = 'smark_manual_ai_allowed_user_ids';
    const OPTION_MANUAL_AI_WEBSITE = 'smark_manual_ai_website';
    const OPTION_CENTRAL_BASE_URL = 'smark_central_base_url';
    const DEFAULT_CENTRAL_BASE_URL = 'https://saeedhasani.com';
    const CENTRAL_SERPER_SEARCH_PATH = '/wp-json/smark-core/v1/tools/serper/search';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'remove_duplicate_submenu'), 999);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_head', array($this, 'output_admin_menu_icon_css'));
        add_action('wp_ajax_smark_save_language', array($this, 'ajax_save_language'));
        add_action('wp_ajax_smark_daily_guide_smart_action', array($this, 'ajax_daily_guide_smart_action'));
        add_action('wp_ajax_smark_save_signalhire_contact_search_settings', array($this, 'ajax_save_signalhire_contact_search_settings'));
        add_action('wp_ajax_smark_dashboard_offer_products_save', array($this, 'ajax_dashboard_offer_products_save'));
        add_action('init', array($this, 'check_database_schema'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('map_meta_cap', array($this, 'map_meta_cap'), 10, 4);
        // TEMP DEBUG disabled after investigation

        // Plugin activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add admin init hook to ensure tables exist (only on plugin pages)
        add_action('load-toplevel_page_smark-dashboard', array($this, 'ensure_tables_exist'));
        add_action('load-smark_page_smark-dashboard-page', array($this, 'ensure_tables_exist'));
        add_filter('admin_body_class', array($this, 'add_smark_body_class'));
        add_action('admin_init', array($this, 'enforce_admin_page_access'));
        add_action('admin_menu', array($this, 'maybe_hide_menus'), 9999);
        add_filter('all_plugins', array($this, 'filter_plugins_list_for_unauthorized'));
    }

    public function enforce_admin_page_access() {
        if (!is_admin()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
        if (!isset($_GET['page'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
        $page = sanitize_key(wp_unslash($_GET['page']));
        if ($page === '') {
            return;
        }

        if (strpos($page, 'smark') !== 0) {
            return;
        }

        if (!current_user_can(self::CAP_ACCESS)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'), 403);
        }
    }

    public function maybe_hide_menus() {
        if (!is_admin()) {
            return;
        }

        if (current_user_can(self::CAP_ACCESS)) {
            return;
        }

        // Hide SMark and SMark Core menus for unauthorized users.
        remove_menu_page('smark-dashboard');
        remove_submenu_page('smark-dashboard', 'smark-dashboard-page');

        remove_menu_page('smark-core-dashboard');
        remove_submenu_page('smark-core-dashboard', 'smark-core-dashboard-page');
    }

    public function filter_plugins_list_for_unauthorized($all_plugins) {
        if (!is_admin() || !is_array($all_plugins)) {
            return $all_plugins;
        }

        if (current_user_can(self::CAP_ACCESS)) {
            return $all_plugins;
        }

        // Hide SMark plugins from Plugins list for unauthorized users.
        $self = plugin_basename(__FILE__);
        if (isset($all_plugins[$self])) {
            unset($all_plugins[$self]);
        }

        // Also hide SMark-Core if installed on this site.
        if (isset($all_plugins['SMark-Core/SMark-Core.php'])) {
            unset($all_plugins['SMark-Core/SMark-Core.php']);
        }

        return $all_plugins;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations (avoid load_plugin_textdomain() per Plugin Check guidance).
        $this->load_textdomain_files();
        $this->cleanup_languages_directory();

        // Initialize plugin functionality
        $this->init_hooks();
    }

    private function load_textdomain_files() {
        $domain = 'smark';
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $mofile = $domain . '-' . $locale . '.mo';

        $global_mo = trailingslashit((string) WP_LANG_DIR) . 'plugins/' . $mofile;
        if (is_readable($global_mo)) {
            load_textdomain($domain, $global_mo);
            return;
        }

        $local_mo = trailingslashit(plugin_dir_path(__FILE__)) . 'languages/' . $mofile;
        if (is_readable($local_mo)) {
            load_textdomain($domain, $local_mo);
        }
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function cleanup_languages_directory() {
        $languages_dir = trailingslashit(plugin_dir_path(__FILE__)) . 'languages';

        if (!is_dir($languages_dir)) {
            return;
        }

        foreach (array('.gitkeep', '.gitignore', '.DS_Store', 'Thumbs.db') as $hidden_file) {
            $path = trailingslashit($languages_dir) . $hidden_file;
            if (is_file($path)) {
                wp_delete_file($path);
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add hooks here as the plugin grows
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('smark/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_api_status'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('smark/v1', '/users', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_api_users'),
            'permission_callback' => array($this, 'rest_can_central_manage'),
        ));

        register_rest_route('smark/v1', '/manual-ai/permissions', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_api_manual_ai_permissions'),
            'permission_callback' => array($this, 'rest_can_central_manage'),
        ));
    }

    /**
     * REST API: Status endpoint
     */
    public function rest_api_status() {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'plugin' => 'SMark',
            'version' => SMARK_VERSION
        ), 200);
    }

    private function get_central_sync_token() {
        if (defined('SMARK_CENTRAL_SYNC_TOKEN')) {
            $token = constant('SMARK_CENTRAL_SYNC_TOKEN');
            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        $token = get_option('smark_central_sync_token', '');
        $token = is_string($token) ? trim($token) : '';
        if ($token !== '') {
            return $token;
        }

        // Back-compat fallback keys
        $token = get_option('smark_core_sync_token', '');
        $token = is_string($token) ? trim($token) : '';
        if ($token !== '') {
            return $token;
        }

        if (is_multisite()) {
            $token = get_site_option('smark_central_sync_token', '');
            $token = is_string($token) ? trim($token) : '';
            if ($token !== '') {
                return $token;
            }

            $token = get_site_option('smark_core_sync_token', '');
            return is_string($token) ? trim($token) : '';
        }

        return '';
    }

    private function normalize_central_base_url($url) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return '';
        }

        $url = rtrim($url, '/');
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!in_array($scheme, array('http', 'https'), true) || !is_string($host) || $host === '') {
            return '';
        }

        return $url;
    }

    private function get_central_base_url() {
        if (defined('SMARK_CENTRAL_BASE_URL') && is_string(SMARK_CENTRAL_BASE_URL) && SMARK_CENTRAL_BASE_URL !== '') {
            $url = $this->normalize_central_base_url((string) SMARK_CENTRAL_BASE_URL);
            if ($url !== '') {
                return $url;
            }
        }

        $url = get_option(self::OPTION_CENTRAL_BASE_URL, '');
        $url = is_string($url) ? $this->normalize_central_base_url($url) : '';
        if ($url !== '') {
            return $url;
        }

        if (is_multisite()) {
            $url = get_site_option(self::OPTION_CENTRAL_BASE_URL, '');
            $url = is_string($url) ? $this->normalize_central_base_url($url) : '';
            if ($url !== '') {
                return $url;
            }
        }

        $filtered = apply_filters('SMARK_central_base_url', self::DEFAULT_CENTRAL_BASE_URL);
        $filtered = is_string($filtered) ? $this->normalize_central_base_url($filtered) : '';
        return $filtered !== '' ? $filtered : self::DEFAULT_CENTRAL_BASE_URL;
    }

    private function get_central_endpoint($path) {
        $path = is_string($path) ? '/' . ltrim($path, '/') : '';
        return rtrim($this->get_central_base_url(), '/') . $path;
    }

    private function resolve_projects_table() {
        global $wpdb;
        $candidates = array(
            $wpdb->prefix . 'smark_projects',
            $wpdb->prefix . 'SMARK_projects',
        );

        foreach ($candidates as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table discovery.
            $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!empty($found)) {
                return (string) $table;
            }
        }

        return (string) ($wpdb->prefix . 'smark_projects');
    }

    private function table_has_column($table, $column) {
        global $wpdb;
        $table_sql = $this->escape_db_identifier($table);
        if ($table_sql === '') {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema inspection requires direct query; identifier validated via escape_db_identifier().
        $exists = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', (string) $column));
        return !empty($exists);
    }

    private function get_current_project_permissions_row() {
        global $wpdb;
        $table = $this->resolve_projects_table();
        $table_sql = $this->escape_db_identifier($table);
        if ($table_sql === '') {
            return $this->get_fallback_permissions_row();
        }

        // Prefer resolving the correct project row by matching this site's URL.
        // This avoids relying on smark_current_project_db_id which may be unset on new installs.
        $project_db_id = (int) get_option('smark_current_project_db_id', 0);
        if ($this->table_has_column($table, 'website')) {
            $site_url = rtrim((string) home_url('/'), '/');
            $resolved = $this->resolve_project_db_id_for_website($table, $site_url);
            if ($resolved > 0) {
                $project_db_id = $resolved;
                update_option('smark_current_project_db_id', $project_db_id, false);
            }
        }

        if ($project_db_id <= 0) {
            return $this->get_fallback_permissions_row();
        }

        if (!$this->table_has_column($table, 'manual_ai')) {
            return $this->get_fallback_permissions_row();
        }

        $cols = array('id', 'manual_ai');
        if ($this->table_has_column($table, 'manual_ai_allowed_users')) {
            $cols[] = 'manual_ai_allowed_users';
        }

        $cols_sql = implode(', ', $cols);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$table_sql} WHERE id = %d LIMIT 1", $project_db_id), ARRAY_A);
        if (is_array($row) && !empty($row)) {
            return $row;
        }

        return $this->get_fallback_permissions_row();
    }

    private function get_fallback_permissions_row() {
        $manual_ai = (int) get_option(self::OPTION_MANUAL_AI_ENABLED, 0);
        $allowed_json = (string) get_option(self::OPTION_MANUAL_AI_ALLOWED_USER_IDS, '[]');

        return array(
            'id' => 0,
            'manual_ai' => $manual_ai ? 1 : 0,
            'manual_ai_allowed_users' => $allowed_json,
        );
    }

    private function normalize_allowed_user_ids($raw) {
        if (!is_array($raw)) {
            return array();
        }
        $ids = array_values(array_filter(array_map('absint', $raw)));
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    private function normalize_site_url_for_match($url) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return '';
        }
        $url = rtrim($url, '/');
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return strtolower($url);
        }
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        if ($host === '') {
            return strtolower($url);
        }
        return $host . $path;
    }

    private function resolve_project_db_id_for_website($projects_table, $website) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return 0;
        }

        $needle = $this->normalize_site_url_for_match($website);
        if ($needle === '') {
            return 0;
        }

        // Fast path: exact match (normalized URL as stored).
        $website = rtrim((string) $website, '/');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exact = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website));
        if ($exact > 0) {
            return $exact;
        }

        // Host-based fallback: fetch a few candidates and compare in PHP to avoid heavy SQL parsing.
        $parts = wp_parse_url($website);
        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $host_no_www = $host;
        if (strpos($host_no_www, 'www.') === 0) {
            $host_no_www = substr($host_no_www, 4);
        }
        if ($host === '') {
            return 0;
        }
        $like_host = '%' . $wpdb->esc_like($host) . '%';
        $like_no_www = ($host_no_www !== '' && $host_no_www !== $host) ? ('%' . $wpdb->esc_like($host_no_www) . '%') : $like_host;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, website FROM {$projects_table_sql} WHERE website LIKE %s OR website LIKE %s ORDER BY id DESC LIMIT 25", $like_host, $like_no_www), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['id']) || empty($row['website'])) {
                continue;
            }
            $cand = $this->normalize_site_url_for_match((string) $row['website']);
            if ($cand !== '' && $cand === $needle) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    private function decode_allowed_users_json($value) {
        if (!is_string($value) || $value === '') {
            return array();
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return array();
        }
        return $this->normalize_allowed_user_ids($decoded);
    }

    private function is_current_user_authorized_for_manual_ai($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $row = $this->get_current_project_permissions_row();
        if (!is_array($row) || empty($row)) {
            return false;
        }

        $allowed = array();
        if (isset($row['manual_ai_allowed_users'])) {
            $allowed = $this->decode_allowed_users_json((string) $row['manual_ai_allowed_users']);
        }

        if (empty($allowed)) {
            // Back-compat/bootstrap:
            // - If Manual AI is enabled but no allow-list exists yet, deny (explicit selection required).
            // - If Manual AI is disabled and no allow-list exists yet, allow admins so setup isn't locked out.
            $manual_ai = isset($row['manual_ai']) ? (int) $row['manual_ai'] : 0;
            if ($manual_ai === 1) {
                return false;
            }
            return user_can($user_id, 'manage_options');
        }

        return in_array($user_id, $allowed, true);
    }

    public function map_meta_cap($caps, $cap, $user_id, $args) {
        if ($cap === self::CAP_ACCESS) {
            $allowed = $this->is_current_user_authorized_for_manual_ai($user_id);
            return $allowed ? array('read') : array('do_not_allow');
        }

        if ($cap === self::CAP_MANUAL_AI) {
            $row = $this->get_current_project_permissions_row();
            $manual_ai = (is_array($row) && !empty($row['manual_ai'])) ? 1 : 0;
            if ($manual_ai !== 1) {
                return array('do_not_allow');
            }

            $allowed = $this->is_current_user_authorized_for_manual_ai($user_id);
            return $allowed ? array('read') : array('do_not_allow');
        }

        return $caps;
    }

    public function rest_can_central_manage(WP_REST_Request $request) {
        $expected = $this->get_central_sync_token();
        if ($expected === '') {
            // Backward-compatible default: if a token isn't set yet, allow management calls.
            // Recommended: configure a token to avoid exposing management endpoints publicly.
            return true;
        }

        $provided = (string) $request->get_header('x-smark-sync-token');
        if (!hash_equals($expected, $provided)) {
            return new WP_Error('smark_central_forbidden', 'Invalid sync token.', array('status' => 403));
        }

        return true;
    }

    public function rest_api_users(WP_REST_Request $request) {
        $q = (string) $request->get_param('q');
        $q = trim($q);

        $args = array(
            // For large sites, return fewer results per query; use live search from central panel.
            'number' => ($q !== '') ? 50 : 200,
            'fields' => array('ID', 'user_login', 'display_name', 'user_email'),
            'orderby' => 'display_name',
            'order' => 'ASC',
        );
        if ($q !== '') {
            $args['search'] = '*' . $q . '*';
            $args['search_columns'] = array('user_login', 'user_nicename', 'display_name', 'user_email');
        }

        $users = get_users($args);
        $out = array();
        foreach ($users as $u) {
            if (!is_object($u)) {
                continue;
            }
            $out[] = array(
                'id' => (int) $u->ID,
                'user_login' => (string) $u->user_login,
                'display_name' => (string) $u->display_name,
                'user_email' => (string) $u->user_email,
            );
        }

        return new WP_REST_Response(array('users' => $out), 200);
    }

    public function rest_api_manual_ai_permissions(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $manual_ai = !empty($params['manual_ai']) ? 1 : 0;
        $allowed_raw = isset($params['allowed_user_ids']) ? $params['allowed_user_ids'] : array();
        $allowed = $this->normalize_allowed_user_ids(is_array($allowed_raw) ? $allowed_raw : array());

        global $wpdb;
        $table = $this->resolve_projects_table();
        $table_sql = $this->escape_db_identifier($table);

        $website = isset($params['website']) ? (string) $params['website'] : '';
        $website = trim($website);

        // Always persist a fallback so access checks can work even if the projects table isn't set up yet.
        update_option(self::OPTION_MANUAL_AI_ENABLED, $manual_ai ? 1 : 0, false);
        update_option(self::OPTION_MANUAL_AI_ALLOWED_USER_IDS, wp_json_encode($allowed), false);
        if ($website !== '') {
            update_option(self::OPTION_MANUAL_AI_WEBSITE, rtrim($website, '/'), false);
        }

        if ($table_sql === '') {
            return new WP_REST_Response(array(
                'ok' => true,
                'manual_ai' => $manual_ai,
                'allowed_user_ids' => $allowed,
                'stored' => 'options_only',
            ), 200);
        }

        // If projects table doesn't exist yet, still succeed (options are already stored).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists !== $table) {
            return new WP_REST_Response(array(
                'ok' => true,
                'manual_ai' => $manual_ai,
                'allowed_user_ids' => $allowed,
                'stored' => 'options_only',
            ), 200);
        }

        // Ensure columns exist
        if (!$this->table_has_column($table, 'manual_ai')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema change requires direct query; identifier validated via escape_db_identifier().
            $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN manual_ai tinyint(1) NOT NULL DEFAULT 0');
        }
        if (!$this->table_has_column($table, 'manual_ai_allowed_users')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema change requires direct query; identifier validated via escape_db_identifier().
            $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN manual_ai_allowed_users longtext NULL DEFAULT NULL');
        }

        $project_db_id = (int) get_option('smark_current_project_db_id', 0);
        if ($website !== '' && $this->table_has_column($table, 'website')) {
            $resolved = $this->resolve_project_db_id_for_website($table, $website);
            if ($resolved > 0) {
                $project_db_id = $resolved;
                update_option('smark_current_project_db_id', $project_db_id, false);
            } else {
                $inserted = $this->maybe_insert_project_for_website($table, $table_sql, $website);
                if ($inserted > 0) {
                    $project_db_id = $inserted;
                    update_option('smark_current_project_db_id', $project_db_id, false);
                }
            }
        }

        if ($project_db_id <= 0) {
            // No project row resolved; keep options as source of truth.
            return new WP_REST_Response(array(
                'ok' => true,
                'manual_ai' => $manual_ai,
                'allowed_user_ids' => $allowed,
                'stored' => 'options_only',
            ), 200);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $updated = $wpdb->update(
            $table,
            array(
                'manual_ai' => $manual_ai,
                'manual_ai_allowed_users' => wp_json_encode($allowed),
            ),
            array('id' => $project_db_id),
            array('%d', '%s'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('smark_permissions_save_failed', $wpdb->last_error ? $wpdb->last_error : 'Update failed.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'ok' => true,
            'manual_ai' => $manual_ai,
            'allowed_user_ids' => $allowed,
            'stored' => 'projects_table',
        ), 200);
    }

    private function maybe_insert_project_for_website($table, $table_sql, $website) {
        global $wpdb;
        $website = rtrim((string) $website, '/');
        if ($website === '' || !$this->table_has_column($table, 'website')) {
            return 0;
        }

        $data = array('website' => $website);
        $format = array('%s');

        if ($this->table_has_column($table, 'project_name')) {
            $data['project_name'] = (string) get_bloginfo('name');
            $format[] = '%s';
        }
        if ($this->table_has_column($table, 'created_at')) {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
        }
        if ($this->table_has_column($table, 'updated_at')) {
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';
        }

        // manual_ai columns are ensured by caller.
        $data['manual_ai'] = (int) get_option(self::OPTION_MANUAL_AI_ENABLED, 0) ? 1 : 0;
        $format[] = '%d';
        $data['manual_ai_allowed_users'] = (string) get_option(self::OPTION_MANUAL_AI_ALLOWED_USER_IDS, '[]');
        $format[] = '%s';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($table, $data, $format);
        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    // DEBUG function removed

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Remove WordPress default Dashboard menu (temporarily disabled for debugging favicon/tab icon issue)
        // remove_menu_page('index.php');

        $dashboard_label = __('Dashboard', 'smark');
        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        if (stripos($locale, 'fa') === 0) {
            $dashboard_label = 'داشبورد';
        }

        add_menu_page(
            __('SMark Plugin', 'smark'),
            __('SMark', 'smark'),
            self::CAP_ACCESS,
            'smark-dashboard',
            array($this, 'admin_page'),
            $this->get_menu_icon_url(),
            30
        );

        // Add Dashboard as a submenu under SMark
        add_submenu_page(
            'smark-dashboard',
            __('Dashboard', 'smark'),
            $dashboard_label,
            self::CAP_ACCESS,
            'smark-dashboard-page',
            array($this, 'admin_page')
        );

    }

    private function get_menu_icon_url() {
        $svg_path = plugin_dir_path(__FILE__) . 'assets/icons/menu-icon.svg';
        if (is_readable($svg_path)) {
            $svg = file_get_contents($svg_path);
            if ($svg !== false && $svg !== '') {
                return 'data:image/svg+xml;base64,' . base64_encode($svg);
            }
        }

        return 'dashicons-admin-generic';
    }

    private function get_dashboard_daily_guide_cards($current_lang = 'en') {
        $lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $suggestions = $this->get_daily_guide_suggestions($lang);
        $active_items = array();
        foreach ($suggestions as $item) {
            if (!is_array($item)) {
                continue;
            }
            $active_key = isset($item['key']) ? sanitize_key((string) $item['key']) : '';
            if ($active_key !== '') {
                $active_items[$active_key] = $item;
            }
        }

        $meta = array(
            'email_contacts_daily' => array(
                'title_en' => 'Add Contacts',
                'title_fa' => 'افزودن مخاطب',
                'category' => 'email',
                'translation_key' => 'daily_guide_task_email_contacts_daily',
                'url' => '#',
                'email_view' => 'contacts',
                'agent_mark_cost' => 100,
                'smart_action' => true,
            ),
            'email_campaign_daily' => array(
                'title_en' => 'Send Email Campaign',
                'title_fa' => 'ارسال کمپین ایمیلی',
                'category' => 'email',
                'translation_key' => 'daily_guide_task_email_campaign_daily',
                'url' => '#',
                'email_view' => 'campaign-message',
                'smart_action' => false,
            ),
            'gap_transfer' => array(
                'title_en' => 'Transfer Competitor Keyword',
                'title_fa' => 'انتقال کلمه رقبا',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_gap_transfer',
                'url' => admin_url('admin.php?page=smark-keyword-gap'),
            ),
            'keyword_red' => array(
                'title_en' => 'Update Keyword Rankings',
                'title_fa' => 'به‌روزرسانی رتبه کلمات',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_keyword_red',
                'url' => admin_url('admin.php?page=smark-keyword-research'),
            ),
            'keyword_no_page' => array(
                'title_en' => 'Connect Keyword Pages',
                'title_fa' => 'اتصال صفحه به کلمه',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_keyword_no_page',
                'url' => admin_url('admin.php?page=smark-keyword-research&pageLinkFilter=no_link'),
            ),
            'rankmath_missing' => array(
                'title_en' => 'Add Site Keywords',
                'title_fa' => 'افزودن کلمات سایت',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_rankmath_missing',
                'url' => admin_url('admin.php?page=smark-keyword-research'),
            ),
            'content_red' => array(
                'title_en' => 'Review Stale Content',
                'title_fa' => 'بازبینی محتوای قدیمی',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_content_red',
                'url' => admin_url('admin.php?page=smark-content-management'),
            ),
            'backlink_acquired' => array(
                'title_en' => 'Acquire Backlink',
                'title_fa' => 'ثبت بک‌لینک',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_backlink_acquired',
                'url' => admin_url('admin.php?page=smark-backlinks-management'),
            ),
            'publish' => array(
                'title_en' => 'Publish Content',
                'title_fa' => 'انتشار محتوا',
                'category' => 'seo',
                'translation_key' => 'daily_guide_task_publish',
                'url' => admin_url('admin.php?page=smark-keyword-research'),
            ),
        );

        $module_visibility = $this->get_dashboard_module_visibility();
        $cards = array();
        foreach ($meta as $key => $card_meta) {
            $category = isset($card_meta['category']) ? sanitize_key((string) $card_meta['category']) : '';
            if ($category !== '' && array_key_exists($category, $module_visibility) && !$module_visibility[$category]) {
                continue;
            }

            $item = isset($active_items[$key]) && is_array($active_items[$key]) ? $active_items[$key] : array();
            $translation_key = isset($card_meta['translation_key']) ? (string) $card_meta['translation_key'] : '';
            $description_en = $translation_key !== '' ? $this->get_dashboard_translation($translation_key, 'en') : (isset($item['text']) ? (string) $item['text'] : '');
            $description_fa = $translation_key !== '' ? $this->get_dashboard_translation($translation_key, 'fa') : (isset($item['text']) ? (string) $item['text'] : '');
            $cards[] = array(
                'key' => $key,
                'title' => ($lang === 'fa') ? $card_meta['title_fa'] : $card_meta['title_en'],
                'titleEn' => $card_meta['title_en'],
                'titleFa' => $card_meta['title_fa'],
                'category' => $card_meta['category'],
                'description' => ($lang === 'fa') ? $description_fa : $description_en,
                'descriptionEn' => $description_en,
                'descriptionFa' => $description_fa,
                'url' => isset($item['url']) ? esc_url_raw((string) $item['url']) : esc_url_raw((string) $card_meta['url']),
                'completed' => !isset($active_items[$key]),
                'smartActionEnabled' => !isset($card_meta['smart_action']) || $card_meta['smart_action'] !== false,
                'agentMarkCost' => isset($card_meta['agent_mark_cost']) ? max(0, (int) $card_meta['agent_mark_cost']) : 0,
                'emailView' => isset($item['email_view']) ? sanitize_key((string) $item['email_view']) : (isset($card_meta['email_view']) ? sanitize_key((string) $card_meta['email_view']) : ''),
            );
        }

        return $cards;
    }

    private function get_dashboard_email_workflow() {
        return array(
            'en' => array(
                'sectionTitle' => 'Email Campaign Workflow',
                'sectionDescription' => 'Organize audience, messaging, schedule, and campaign performance in one guided workspace.',
                'tasks' => array(
                    array(
                        'icon' => 'dashicons-groups',
                        'title' => 'Contacts',
                        'description' => 'Prepare contact lists and tags based on each campaign goal.',
                        'view' => 'contacts',
                    ),
                    array(
                        'icon' => 'dashicons-email-alt',
                        'title' => 'Campaign Message',
                        'description' => 'Plan the subject, copy, offer, and call to action for each email.',
                        'url' => admin_url('admin.php?page=smark-email-campaign-message'),
                        'view' => 'campaign-message',
                    ),
                    array(
                        'icon' => 'dashicons-admin-users',
                        'title' => 'Email Accounts',
                        'description' => 'Manage sender accounts and daily send limits.',
                        'url' => admin_url('admin.php?page=smark-email-accounts'),
                        'view' => 'email-accounts',
                    ),
                    array(
                        'icon' => 'dashicons-chart-area',
                        'title' => 'Performance Review',
                        'description' => 'Track opens, clicks, conversions, and unsubscribes.',
                        'url' => admin_url('admin.php?page=smark-email-performance'),
                        'view' => 'performance-review',
                    ),
                ),
            ),
            'fa' => array(
                'sectionTitle' => 'برنامه کمپین‌های ایمیلی',
                'sectionDescription' => 'مخاطب، پیام، زمان‌بندی و عملکرد کمپین‌ها را در یک مسیر منظم مدیریت کنید.',
                'tasks' => array(
                    array(
                        'icon' => 'dashicons-groups',
                        'title' => 'مخاطبین',
                        'description' => 'لیست‌ها و برچسب‌های مخاطبان را بر اساس هدف کمپین آماده کنید.',
                        'view' => 'contacts',
                    ),
                    array(
                        'icon' => 'dashicons-email-alt',
                        'title' => 'طراحی پیام کمپین',
                        'description' => 'موضوع، متن، پیشنهاد و فراخوان اقدام ایمیل را برنامه‌ریزی کنید.',
                        'url' => admin_url('admin.php?page=smark-email-campaign-message'),
                        'view' => 'campaign-message',
                    ),
                    array(
                        'icon' => 'dashicons-admin-users',
                        'title' => 'حساب‌های ایمیل',
                        'description' => 'مدیریت حساب‌های فرستنده و سقف ارسال روزانه آن‌ها.',
                        'url' => admin_url('admin.php?page=smark-email-accounts'),
                        'view' => 'email-accounts',
                    ),
                    array(
                        'icon' => 'dashicons-chart-area',
                        'title' => 'پایش عملکرد',
                        'description' => 'نرخ باز شدن، کلیک، تبدیل و خروج از لیست را بررسی کنید.',
                        'url' => admin_url('admin.php?page=smark-email-performance'),
                        'view' => 'performance-review',
                    ),
                ),
            ),
        );
    }

    private function get_dashboard_module_visibility() {
        if (class_exists('SMarkProjectSettings', false) && method_exists('SMarkProjectSettings', 'get_module_visibility')) {
            return SMarkProjectSettings::get_module_visibility();
        }

        $saved = get_option('smark_dashboard_module_visibility', array());
        $saved = is_array($saved) ? $saved : array();

        return array(
            'email' => array_key_exists('email', $saved) ? (bool) $saved['email'] : true,
            'seo' => array_key_exists('seo', $saved) ? (bool) $saved['seo'] : true,
            'social' => array_key_exists('social', $saved) ? (bool) $saved['social'] : true,
            'ads' => array_key_exists('ads', $saved) ? (bool) $saved['ads'] : true,
            'offer' => array_key_exists('offer', $saved) ? (bool) $saved['offer'] : true,
        );
    }

    private function get_dashboard_mark_balance() {
        $project_id = $this->resolve_current_project_id();
        $mark = null;

        if ($project_id > 0) {
            $cache = get_option('smark_project_mark_cache', array());
            $cache = is_array($cache) ? $cache : array();
            $row = isset($cache[(string) $project_id]) ? $cache[(string) $project_id] : null;
            if (is_array($row) && isset($row['mark'])) {
                $mark = max(0, (int) $row['mark']);
            } elseif (is_numeric($row)) {
                $mark = max(0, (int) $row);
            }

            if ($mark === null) {
                global $wpdb;
                $projects_table = $this->resolve_projects_table();
                $projects_table_sql = $this->escape_db_identifier($projects_table);
                if ($projects_table !== '' && $projects_table_sql !== '' && $this->table_has_column($projects_table, 'mark')) {
                    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
                    $local_mark = $wpdb->get_var($wpdb->prepare('SELECT mark FROM ' . $projects_table_sql . ' WHERE id = %d LIMIT 1', $project_id));
                    if ($local_mark !== null && is_numeric($local_mark)) {
                        $mark = max(0, (int) $local_mark);
                    }
                }
            }
        }

        return array(
            'value' => $mark,
            'label' => $mark === null ? '—' : number_format_i18n((int) $mark),
        );
    }

    public function output_admin_menu_icon_css() {
        ?>
        <style>
            #adminmenu li.toplevel_page_smark-dashboard .wp-menu-image,
            #adminmenu li#toplevel_page_smark-dashboard .wp-menu-image {
                background-position: center center !important;
                background-repeat: no-repeat !important;
                background-size: 20px 20px !important;
            }

            #adminmenu li.toplevel_page_smark-dashboard .wp-menu-image img,
            #adminmenu li#toplevel_page_smark-dashboard .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                filter: brightness(0) invert(1);
            }
        </style>
        <?php
    }

    /**
     * Remove duplicate "SMark" submenu item
     */
    public function remove_duplicate_submenu() {
        remove_submenu_page('smark-dashboard', 'smark-dashboard');
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div id="smark-dashboard-root" class="wrap smark-dashboard-app-page" data-smark-logo="<?php echo esc_url(SMARK_PLUGIN_URL . 'assets/icons/menu-icon.svg'); ?>"></div>
        <?php
    }

    /**
     * Get translation for dashboard texts
     */
    private function get_dashboard_translation($key, $current_lang = 'en') {
        $translations = array(
            'en' => array(
                'smark_plugin_dashboard' => 'SMark Plugin Dashboard',
                'comprehensive_wordpress_toolkit' => 'Your comprehensive WordPress toolkit',
                'dashboard' => 'Dashboard',
                'main_features' => 'Main Features',
                'explore_powerful_features' => 'Explore the powerful features available in SMark Plugin',
                'social_media_designer' => 'Social Media Designer',
                'create_stunning_social' => 'Create stunning social media graphics and content for all major platforms.',
                'seo_optimization' => 'SEO Optimization Hub',
                'manage_seo_process' => 'Plan, execute, and track on-page, technical, and off-page SEO activities in one place.',
                'email_marketing' => 'Email Marketing',
                'manage_email_marketing' => 'Plan campaigns, segment audiences, and track email performance from one workspace.',
                'keyword_research' => 'Keyword Research',
                'centralize_keyword_bank' => 'Centralize your keyword bank, upload spreadsheets, and assign ideas to projects.',
                'basic_features' => 'Basic Features',
                'essential_tools' => 'Essential tools and utilities for your WordPress site',
                'project_settings' => 'Project Settings',
                'manage_project_settings' => 'Set up your project identity and connect services like Search Console.',
                'google_docs_converter' => 'Google Docs Converter',
                'convert_google_docs' => 'Convert Google Docs documents to WordPress blog posts with proper formatting.',
                'headline_analyzer' => 'Headline Analyzer',
                'analyze_headlines' => 'Analyze your headlines and get instant feedback to improve engagement and SEO performance.',
                'gemini_app' => 'Gemini App',
                'ai_powered_analysis' => 'AI-powered analysis and content generation using Google Gemini.',
                'competitor_analysis' => 'Competitor Analysis',
                'track_competitor_websites' => 'Track competitor websites and their new content. Monitor pages and blog posts.',
                'plugin_logs' => 'Plugin Logs',
                'view_manage_plugin_logs' => 'View and manage plugin logs for debugging and troubleshooting.',
                'prompt_bank' => 'Prompt Bank',
                'manage_ai_prompts' => 'Centralized repository for AI prompts used across all features.',
                'smark_plugin' => 'SMark Plugin',
                'daily_guide_title' => 'Daily Guide',
                'daily_guide_subtitle' => 'A quick checklist of today’s essential actions',
                'daily_guide_all_good' => 'All set for today. Great job!',
                'daily_guide_open' => 'Open',
                'daily_guide_smart_action' => 'Agent do',
                'daily_guide_task_gap_transfer' => 'Transfer at least one competitor keyword to Keyword Research today.',
                'daily_guide_task_keyword_red' => 'Some keyword rankings are stale or not updated. Review Keyword Research updates.',
                'daily_guide_task_keyword_no_page' => 'Some keywords still don’t have a linked page. Create/publish their pages and connect them from Keyword Research.',
                'daily_guide_task_rankmath_missing' => 'Some website keywords still have not been added to Keyword Research. Add one today.',
                'daily_guide_task_content_red' => 'Some content items are stale. Review updates in Content Management.',
                'daily_guide_task_publish' => 'No content published today. Publish at least one item.',
                'daily_guide_task_backlink_acquired' => 'Acquire at least one backlink in Backlinks Management today.',
                'daily_guide_task_email_contacts_daily' => 'Add 100 new email contacts today to keep campaign audiences growing.',
                'daily_guide_task_email_campaign_daily' => 'Send one email campaign today to keep your audience engaged.'
            ),
            'fa' => array(
                'smark_plugin_dashboard' => 'داشبورد پلاگین اسمارک',
                'comprehensive_wordpress_toolkit' => 'ابزار جامع وردپرس شما',
                'dashboard' => 'داشبورد',
                'main_features' => 'ویژگی‌های اصلی',
                'explore_powerful_features' => 'ویژگی‌های قدرتمند موجود در پلاگین اسمارک را کاوش کنید',
                'social_media_designer' => 'طراح شبکه‌های اجتماعی',
                'create_stunning_social' => 'ایجاد گرافیک‌ها و محتوای شگفت‌انگیز شبکه‌های اجتماعی برای تمامی پلتفرم‌های اصلی.',
                'seo_optimization' => 'مرکز مدیریت سئو',
                'manage_seo_process' => 'فرآیند سئو را در بخش‌های محتوایی، فنی و لینک‌سازی برنامه‌ریزی، اجرا و پایش کنید.',
                'keyword_research' => 'تحقیق کلمات کلیدی',
                'centralize_keyword_bank' => 'متمرکز کردن بانک کلمات کلیدی، آپلود فایل‌های اکسل و تخصیص ایده‌ها به پروژه‌ها.',
                'basic_features' => 'ویژگی‌های پایه',
                'essential_tools' => 'ابزارها و امکانات ضروری برای سایت وردپرس شما',
                'project_settings' => 'تنظیمات پروژه',
                'manage_project_settings' => 'تنظیمات پایه پروژه را مشخص کنید و سرویس‌هایی مانند Search Console را متصل کنید.',
                'google_docs_converter' => 'مبدل گوگل داک',
                'convert_google_docs' => 'تبدیل اسناد گوگل داک به پست‌های وبلاگ وردپرس با فرمت مناسب.',
                'headline_analyzer' => 'تحلیلگر عناوین',
                'analyze_headlines' => 'تجزیه و تحلیل عناوین شما و دریافت بازخورد فوری برای بهبود تعامل و عملکرد SEO.',
                'gemini_app' => 'اپلیکیشن جمینی',
                'ai_powered_analysis' => 'تجزیه و تحلیل و تولید محتوا با هوش مصنوعی با استفاده از گوگل جمینی.',
                'competitor_analysis' => 'تحلیل رقبا',
                'track_competitor_websites' => 'ردیابی وب‌سایت‌های رقبا و محتوای جدید آن‌ها. نظارت بر صفحات و پست‌های وبلاگ.',
                'plugin_logs' => 'لاگ‌های پلاگین',
                'view_manage_plugin_logs' => 'مشاهده و مدیریت لاگ‌های پلاگین برای اشکال‌زدایی و عیب‌یابی.',
                'prompt_bank' => 'بانک پرامپت',
                'manage_ai_prompts' => 'مخزن متمرکز برای پرامپت‌های هوش مصنوعی که در تمام فیچرها استفاده می‌شوند.',
                'smark_plugin' => 'پلاگین اسمارک',
                'daily_guide_title' => 'راهنمای روزانه',
                'daily_guide_subtitle' => 'چک‌لیست سریع کارهای ضروری امروز',
                'daily_guide_all_good' => 'همه چیز برای امروز اوکی است.',
                'daily_guide_open' => 'باز کردن',
                'daily_guide_smart_action' => 'انجام با ایجنت',
                'daily_guide_task_gap_transfer' => 'امروز حداقل یک کلمه کلیدی از تحلیل رقبا به تحقیق کلمات کلیدی پروژه منتقل کنید.',
                'daily_guide_task_keyword_red' => 'برخی کلمات کلیدی در تحقیق کلمات کلیدی نیاز به بروزرسانی دارند (قرمز/انجام‌نشده).',
                'daily_guide_task_keyword_no_page' => 'برخی کلمات کلیدی هنوز لینک صفحه ندارند؛ یعنی برایشان صفحه/محتوا ساخته نشده یا به تحقیق کلمات کلیدی وصل نشده است. لطفاً بررسی کنید و صفحه‌شان را بسازید.',
                'daily_guide_task_rankmath_missing' => 'برخی کلمات کلیدی وبسایت هنوز به جدول تحقیق کلمات کلیدی اضافه نشده‌اند. امروز یک مورد را اضافه کنید.',
                'daily_guide_task_content_red' => 'برخی آیتم‌ها در مدیریت محتوا نیاز به بروزرسانی دارند (قرمز).',
                'daily_guide_task_publish' => 'امروز هیچ محتوایی منتشر نشده است. حداقل یک محتوا منتشر کنید.',
                'daily_guide_task_email_contacts_daily' => 'امروز ۱۰۰ مخاطب جدید به سیستم ایمیل مارکتینگ اضافه کنید تا لیست کمپین‌ها رشد کند.',
                'daily_guide_task_email_campaign_daily' => 'امروز یک کمپین ایمیلی ارسال کنید تا ارتباط با مخاطبان فعال بماند.'
            )
        );

        if ($current_lang === 'fa' && $key === 'daily_guide_task_backlink_acquired') {
            return 'امروز حداقل یک بک‌لینک را در مدیریت بک‌لینک‌ها به وضعیت دریافت‌شده برسانید.';
        }
        if ($current_lang === 'fa' && $key === 'email_marketing') {
            return 'ایمیل مارکتینگ';
        }
        if ($current_lang === 'fa' && $key === 'manage_email_marketing') {
            return 'کمپین‌ها، مخاطبان و عملکرد ایمیل‌ها را در یک فضای منظم برنامه‌ریزی و پایش کنید.';
        }

        return isset($translations[$current_lang][$key]) ? $translations[$current_lang][$key] : $translations['en'][$key];
    }

    /**
     * Daily Guide suggestions for dashboard (based on today's activity).
     *
     * @param string $current_lang Language code.
     *
     * @return array<int, array{text:string,url:string}>
     */
    private function get_daily_guide_suggestions($current_lang = 'en') {
        $lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $project_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_id <= 0) {
            $legacy = (int) get_option('SMARK_current_project_db_id', 0);
            if ($legacy > 0) {
                $project_id = $legacy;
                update_option('smark_current_project_db_id', $project_id, false);
            }
        }
        if ($project_id <= 0) {
            // Best effort: resolve project row by matching this site URL (consistent with other parts of the plugin).
            $projects_table = $this->resolve_projects_table();
            $site_url = rtrim((string) home_url('/'), '/');
            if ($projects_table !== '' && $site_url !== '' && $this->table_has_column($projects_table, 'website')) {
                $resolved = (int) $this->resolve_project_db_id_for_website($projects_table, $site_url);
                if ($resolved > 0) {
                    $project_id = $resolved;
                    update_option('smark_current_project_db_id', $project_id, false);
                }
            }
        }
        $today = current_time('Y-m-d');
        $suggestions = array();

        // Email Marketing: add 100 new contacts each day.
        $contacts = get_option('smark_email_marketing_contacts', array());
        $contacts = is_array($contacts) ? $contacts : array();
        $contacts_added_today = 0;
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }
            $created_at = isset($contact['created_at']) ? (string) $contact['created_at'] : '';
            if ($created_at !== '' && substr($created_at, 0, 10) === $today) {
                $contacts_added_today++;
            }
        }

        if ($contacts_added_today < 100) {
            $suggestions[] = array(
                'key'  => 'email_contacts_daily',
                'text' => $this->get_dashboard_translation('daily_guide_task_email_contacts_daily', $lang),
                'url'  => '#',
                'email_view' => 'contacts',
            );
        }

        $campaign_messages = get_option('smark_email_marketing_campaign_messages', array());
        $campaign_messages = is_array($campaign_messages) ? $campaign_messages : array();
        $campaign_sent_today = false;
        foreach ($campaign_messages as $campaign_message) {
            if (!is_array($campaign_message)) {
                continue;
            }
            $sent_at = isset($campaign_message['sent_at']) ? (string) $campaign_message['sent_at'] : '';
            if ($sent_at !== '' && substr($sent_at, 0, 10) === $today) {
                $campaign_sent_today = true;
                break;
            }
        }

        if (!$campaign_sent_today) {
            $suggestions[] = array(
                'key'  => 'email_campaign_daily',
                'text' => $this->get_dashboard_translation('daily_guide_task_email_campaign_daily', $lang),
                'url'  => '#',
                'email_view' => 'campaign-message',
            );
        }

        // 1) Keyword Gap -> Keyword Research transfer happened today?
        $transfer_opt = 'smark_daily_guide_keyword_gap_transfer_' . (string) (int) $project_id;
        $last_transfer_day = (string) get_option($transfer_opt, '');
        if ($project_id <= 0 || $last_transfer_day !== $today) {
            $suggestions[] = array(
                'key'  => 'gap_transfer',
                'text' => $this->get_dashboard_translation('daily_guide_task_gap_transfer', $lang),
                'url'  => admin_url('admin.php?page=smark-keyword-gap'),
            );
        }

        // 2) Keyword Research has any red/not-updated ranking_updated_at? (JS uses >30 days as stale)
        global $wpdb;
        $kw_table = isset($wpdb->prefix) ? $wpdb->prefix . 'SMARK_keyword_research' : '';
        $kw_table_sql = $this->escape_db_identifier($kw_table);
        $kw_table_exists = false;
        if ($kw_table !== '' && $kw_table_sql !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard check.
            $kw_table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $kw_table)) === $kw_table);
        }

        $kw_needs_attention = false;
        if ($project_id > 0 && $kw_table_exists && $kw_table_sql !== '') {
            $threshold = wp_date('Y-m-d H:i:s', (int) current_time('timestamp') - (30 * DAY_IN_SECONDS));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
            $total_kw = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $kw_table_sql . ' WHERE project_id = %d', $project_id));
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
            $stale_kw = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $kw_table_sql . " WHERE project_id = %d AND (ranking_updated_at IS NULL OR ranking_updated_at = '' OR ranking_updated_at < %s)", $project_id, $threshold));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($total_kw <= 0 || $stale_kw > 0) {
                $kw_needs_attention = true;
            }
        } else {
            // If project is not set or table missing, treat as needs attention for this checklist item.
            $kw_needs_attention = true;
        }

        if ($kw_needs_attention) {
            $suggestions[] = array(
                'key'  => 'keyword_red',
                'text' => $this->get_dashboard_translation('daily_guide_task_keyword_red', $lang),
                'url'  => admin_url('admin.php?page=smark-keyword-research'),
            );
        }

        // 2b) Keyword Research: any items without a linked page?
        if ($project_id > 0 && $kw_table_exists && $kw_table_sql !== '' && $this->table_has_column($kw_table, 'page_link_status')) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
            $no_page = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $kw_table_sql . " WHERE project_id = %d AND (page_link_status = 'not_found' OR page_link_status = 'not_connected')", $project_id));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($no_page > 0) {
                $suggestions[] = array(
                    'key'  => 'keyword_no_page',
                    'text' => $this->get_dashboard_translation('daily_guide_task_keyword_no_page', $lang),
                    'url'  => admin_url('admin.php?page=smark-keyword-research&pageLinkFilter=no_link'),
                );
            }
        }

        // 2c) Rank Math focus keywords missing from Keyword Research.
        if ($project_id > 0 && $kw_table_exists && $kw_table_sql !== '') {
            $suggestions[] = array(
                'key'  => 'rankmath_missing',
                'text' => $this->get_dashboard_translation('daily_guide_task_rankmath_missing', $lang),
                'url'  => admin_url('admin.php?page=smark-keyword-research'),
            );
        }

        // 3) Content Management: any selected item with stale updatedAt? (JS uses >183 days as stale)
        $cm_opt = get_option('smark_cm_selected_content', array());
        $cm_ids = array();
        $pid_key = (string) (int) $project_id;
        if ($project_id > 0 && is_array($cm_opt) && isset($cm_opt[$pid_key]) && is_array($cm_opt[$pid_key])) {
            $cm_ids = array_values(array_unique(array_filter(array_map('intval', (array) $cm_opt[$pid_key]))));
        }

        $cm_has_stale = false;
        if (!empty($cm_ids)) {
            $threshold_ts = (int) current_time('timestamp') - (183 * DAY_IN_SECONDS);
            foreach ($cm_ids as $post_id) {
                if ($post_id <= 0) {
                    continue;
                }
                $post = get_post($post_id);
                if (!$post || !isset($post->post_modified)) {
                    continue;
                }
                $modified_ts = (int) mysql2date('U', (string) $post->post_modified, false);
                if ($modified_ts > 0 && $modified_ts < $threshold_ts) {
                    $cm_has_stale = true;
                    break;
                }
            }
        }

        if ($cm_has_stale) {
            $suggestions[] = array(
                'key'  => 'content_red',
                'text' => $this->get_dashboard_translation('daily_guide_task_content_red', $lang),
                'url'  => admin_url('admin.php?page=smark-content-management'),
            );
        }

        // 3b) Backlinks Management: at least one backlink acquired today?
        $backlinks_table = isset($wpdb->prefix) ? $wpdb->prefix . 'bmt_links' : '';
        $backlinks_table_sql = $this->escape_db_identifier($backlinks_table);
        $backlink_acquired_today = false;
        if ($backlinks_table !== '' && $backlinks_table_sql !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard check.
            $backlinks_table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $backlinks_table)) === $backlinks_table);
            if ($backlinks_table_exists && function_exists('bmt_ensure_links_acquired_at_column')) {
                bmt_ensure_links_acquired_at_column();
            }

            if ($backlinks_table_exists && $this->table_has_column($backlinks_table, 'acquired_at')) {
                $today_start = $today . ' 00:00:00';
                $tomorrow_start = wp_date('Y-m-d 00:00:00', (int) current_time('timestamp') + DAY_IN_SECONDS);
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
                $acquired_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $backlinks_table_sql . " WHERE status = 'acquired' AND acquired_at >= %s AND acquired_at < %s", $today_start, $tomorrow_start));
                $backlink_acquired_today = $acquired_count > 0;
            }
        }

        if (!$backlink_acquired_today) {
            $suggestions[] = array(
                'key'  => 'backlink_acquired',
                'text' => $this->get_dashboard_translation('daily_guide_task_backlink_acquired', $lang),
                'url'  => admin_url('admin.php?page=smark-backlinks-management'),
            );
        }

        // 4) Any content published today?
        $public_types = get_post_types(array('public' => true), 'names');
        $public_types = is_array($public_types) ? $public_types : array('post', 'page');
        $public_types = array_values(array_filter(array_map('sanitize_key', $public_types), function ($t) {
            return $t !== 'attachment';
        }));

        $published_today = false;
        if (!empty($public_types)) {
            $q = new WP_Query(array(
                'post_type'      => $public_types,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'date_query'     => array(
                    array(
                        'after'     => 'today',
                        'inclusive' => true,
                    ),
                ),
            ));
            $published_today = $q->have_posts();
            wp_reset_postdata();
        }

        if (!$published_today) {
            $suggestions[] = array(
                'key'  => 'publish',
                'text' => $this->get_dashboard_translation('daily_guide_task_publish', $lang),
                'url'  => admin_url('admin.php?page=smark-keyword-research'),
            );
        }

        return $suggestions;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if ($hook !== 'toplevel_page_smark-dashboard' && $hook !== 'smark_page_smark-dashboard-page') {
            return;
        }

        $lang = get_option('smark_panel_language', 'en');
        $lang = ($lang === 'fa') ? 'fa' : 'en';

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
            array(),
            SMARK_VERSION
        );

        $admin_css_version = SMARK_VERSION;
        $admin_css_path = SMARK_PLUGIN_PATH . 'assets/css/admin.css';
        if (is_readable($admin_css_path)) {
            $admin_css_version .= '.' . (string) filemtime($admin_css_path);
        }
        wp_enqueue_style('smark-admin', SMARK_PLUGIN_URL . 'assets/css/admin.css', array(), $admin_css_version);

        $dashboard_css_version = SMARK_VERSION;
        $dashboard_css_path = SMARK_PLUGIN_PATH . 'assets/dashboard/dashboard.css';
        if (is_readable($dashboard_css_path)) {
            $dashboard_css_version .= '.' . (string) filemtime($dashboard_css_path);
        }
        wp_enqueue_style('smark-dashboard', SMARK_PLUGIN_URL . 'assets/dashboard/dashboard.css', array('vazirmatn-font', 'smark-admin'), $dashboard_css_version);

        $admin_js_version = SMARK_VERSION;
        $admin_js_path = SMARK_PLUGIN_PATH . 'assets/js/admin.js';
        if (is_readable($admin_js_path)) {
            $admin_js_version .= '.' . (string) filemtime($admin_js_path);
        }
        wp_enqueue_script('smark-admin', SMARK_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $admin_js_version, true);

        wp_localize_script('smark-admin', 'SMarkAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('smark_daily_guide_smart_action'),
            'signalhireSettingsNonce' => wp_create_nonce('smark_signalhire_contact_search_settings'),
            'signalhireContactSearchSettings' => $this->get_signalhire_contact_search_settings(),
            'lang'    => $lang,
            'strings' => array(
                'smartTitle'    => ($lang === 'fa') ? 'انجام با ایجنت' : 'Agent do',
                'smartTitleDone'=> ($lang === 'fa') ? 'نتیجه ایجنت' : 'Agent result',
                'copy'          => ($lang === 'fa') ? 'کپی' : 'Copy',
                'result'        => ($lang === 'fa') ? 'نتیجه' : 'Result',
                'prompt'        => ($lang === 'fa') ? 'پرامپت' : 'Prompt',
                'sources'       => ($lang === 'fa') ? 'منابع' : 'Sources',
                'smartNotReady' => ($lang === 'fa') ? 'این اکشن ایجنت هنوز برای این آیتم آماده نیست.' : 'Agent action is not implemented for this item yet.',
                'smartRunning'  => ($lang === 'fa') ? 'ایجنت در حال انجام کار است…' : 'Agent is working…',
                'smartDone'     => ($lang === 'fa') ? 'برای «{keyword}» یک آیتم محتوا ساخته شد و پیش‌نویس بلاگ ایجاد شد.' : 'Created a content item and a blog draft for “{keyword}”.',
                'smartError'    => ($lang === 'fa') ? 'اکشن ایجنت ناموفق بود. لطفاً دوباره امتحان کنید.' : 'Agent action failed. Please try again.',
                'signalhireTitle' => ($lang === 'fa') ? 'تنظیمات جست‌وجوی مخاطب' : 'Contact Search Settings',
                'signalhireIntro' => ($lang === 'fa') ? 'این زیرساخت فعلا غیرفعال است. حداقل یکی از فیلدها را پر کنید تا تنظیمات جست‌وجوی آینده ذخیره شود.' : 'This infrastructure is currently inactive. Fill at least one field to save future contact search settings.',
                'signalhireProfileSection' => ($lang === 'fa') ? 'پروفایل' : 'Profile',
                'signalhireCompanySection' => ($lang === 'fa') ? 'شرکت' : 'Company',
                'signalhireProfileName' => ($lang === 'fa') ? 'نام پروفایل' : 'Profile name',
                'signalhireProfileLocation' => ($lang === 'fa') ? 'لوکیشن پروفایل' : 'Profile location',
                'signalhireJobTitle' => ($lang === 'fa') ? 'عنوان شغلی' : 'Job title',
                'signalhireDepartment' => ($lang === 'fa') ? 'دپارتمان' : 'Department',
                'signalhireSeniorityLevel' => ($lang === 'fa') ? 'سطح ارشدیت' : 'Seniority level',
                'signalhireYearsExperience' => ($lang === 'fa') ? 'سال‌های تجربه' : 'Years of experience',
                'signalhireEducation' => ($lang === 'fa') ? 'تحصیلات' : 'Education',
                'signalhireKeywords' => ($lang === 'fa') ? 'کلمات کلیدی' : 'Keywords',
                'signalhireCompanyName' => ($lang === 'fa') ? 'نام شرکت' : 'Company name',
                'signalhireCompanyLocation' => ($lang === 'fa') ? 'لوکیشن شرکت' : 'Company location',
                'signalhireIndustry' => ($lang === 'fa') ? 'صنعت' : 'Industry',
                'signalhireCompanySize' => ($lang === 'fa') ? 'اندازه شرکت' : 'Company size',
                'signalhireSave' => ($lang === 'fa') ? 'اجرای ایجنت' : 'Run agent',
                'signalhireSaved' => ($lang === 'fa') ? 'تنظیمات جست‌وجو ذخیره شد.' : 'Search settings saved.',
                'signalhireValidation' => ($lang === 'fa') ? 'حداقل یکی از فیلدها را پر کنید.' : 'Fill at least one field.',
                'signalhireInactive' => ($lang === 'fa') ? 'اجرای جست‌وجو فعلا غیرفعال است؛ فقط تنظیمات ذخیره می‌شود.' : 'Search execution is inactive for now; only settings are saved.',
                'backToDashboard' => ($lang === 'fa') ? 'بازگشت' : 'Back',
                'close' => ($lang === 'fa') ? 'بستن' : 'Close',
            ),
        ));

        $dashboard_js_version = SMARK_VERSION;
        $dashboard_js_path = SMARK_PLUGIN_PATH . 'assets/dashboard/dashboard.js';
        if (is_readable($dashboard_js_path)) {
            $dashboard_js_version .= '.' . (string) filemtime($dashboard_js_path);
        }
        wp_enqueue_script('smark-dashboard', SMARK_PLUGIN_URL . 'assets/dashboard/dashboard.js', array('wp-element', 'smark-admin'), $dashboard_js_version, true);
        wp_localize_script('smark-dashboard', 'SMarkDashboard', array(
            'logoUrl' => SMARK_PLUGIN_URL . 'assets/icons/menu-icon.svg',
            'version' => SMARK_VERSION,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('smark_daily_guide_smart_action'),
            'lang'    => $lang,
            'urls'    => array(
                'social' => admin_url('admin.php?page=smark-social-media'),
                'seo' => admin_url('admin.php?page=smark-seo-optimization'),
                'email' => '',
                'settings' => admin_url('admin.php?page=smark-project-settings'),
                'googleDocs' => admin_url('admin.php?page=smark-google-docs-converter'),
                'headlineAnalyzer' => admin_url('admin.php?page=smark-headline-analyzer'),
                'competitorAnalysis' => admin_url('admin.php?page=smark-competitor-analysis'),
            ),
            'dailyGuideCards' => $this->get_dashboard_daily_guide_cards($lang),
            'emailWorkflow' => $this->get_dashboard_email_workflow(),
            'moduleVisibility' => $this->get_dashboard_module_visibility(),
            'markBalance' => $this->get_dashboard_mark_balance(),
            'offerProducts' => $this->get_dashboard_offer_products(),
            'offerSections' => $this->get_dashboard_offer_sections(),
            'offerProductsNonce' => wp_create_nonce('smark_dashboard_offer_products'),
            'signalhireSettingsNonce' => wp_create_nonce('smark_signalhire_contact_search_settings'),
            'signalhireContactSearchSettings' => $this->get_signalhire_contact_search_settings(),
            'emailContactsViewNonce' => wp_create_nonce('smark_email_contacts_page_ajax'),
            'emailAccountsViewNonce' => wp_create_nonce('smark_email_accounts_ajax'),
            'emailCampaignMessageViewNonce' => wp_create_nonce('smark_email_campaign_message_ajax'),
            'emailPerformanceViewNonce' => wp_create_nonce('smark_email_performance_ajax'),
            'projectSettingsViewNonce' => wp_create_nonce('smark_project_settings_dashboard_ajax'),
            'stringsByLang' => array(
                'en' => array(
                    'dailyGuideTitle' => $this->get_dashboard_translation('daily_guide_title', 'en'),
                    'dailyGuideAllGood' => $this->get_dashboard_translation('daily_guide_all_good', 'en'),
                    'open' => $this->get_dashboard_translation('daily_guide_open', 'en'),
                    'smartAction' => $this->get_dashboard_translation('daily_guide_smart_action', 'en'),
                    'smartNotReady' => 'Agent action is not implemented for this item yet.',
                    'smartRunning' => 'Agent is working...',
                    'smartDone' => 'Agent action completed.',
                    'smartError' => 'Agent action failed. Please try again.',
                    'projectSettings' => $this->get_dashboard_translation('project_settings', 'en'),
                    'googleDocsConverter' => $this->get_dashboard_translation('google_docs_converter', 'en'),
                    'headlineAnalyzer' => $this->get_dashboard_translation('headline_analyzer', 'en'),
                    'competitorAnalysis' => $this->get_dashboard_translation('competitor_analysis', 'en'),
                ),
                'fa' => array(
                    'dailyGuideTitle' => $this->get_dashboard_translation('daily_guide_title', 'fa'),
                    'dailyGuideAllGood' => $this->get_dashboard_translation('daily_guide_all_good', 'fa'),
                    'open' => $this->get_dashboard_translation('daily_guide_open', 'fa'),
                    'smartAction' => $this->get_dashboard_translation('daily_guide_smart_action', 'fa'),
                    'smartNotReady' => 'این اکشن ایجنت هنوز برای این آیتم آماده نیست.',
                    'smartRunning' => 'ایجنت در حال انجام کار است...',
                    'smartDone' => 'اکشن ایجنت انجام شد.',
                    'smartError' => 'اکشن ایجنت ناموفق بود. لطفاً دوباره امتحان کنید.',
                    'projectSettings' => $this->get_dashboard_translation('project_settings', 'fa'),
                    'googleDocsConverter' => $this->get_dashboard_translation('google_docs_converter', 'fa'),
                    'headlineAnalyzer' => $this->get_dashboard_translation('headline_analyzer', 'fa'),
                    'competitorAnalysis' => $this->get_dashboard_translation('competitor_analysis', 'fa'),
                ),
            ),
            'strings' => array(
                'workspace'      => __('SMark dashboard workspace', 'smark'),
                'navigation'     => __('SMark dashboard navigation', 'smark'),
                'smark'          => __('SMark', 'smark'),
                'social'         => __('Social Media', 'smark'),
                'seo'            => __('SEO', 'smark'),
                'emailMarketing' => __('Email Marketing', 'smark'),
                'ads'            => ($lang === 'fa') ? 'ادز' : __('Ads', 'smark'),
                'offer'          => ($lang === 'fa') ? 'آفر' : __('Offer', 'smark'),
                'offerManagementTitle' => ($lang === 'fa') ? 'مدیریت آفریینگ' : __('Offering Management', 'smark'),
                'offerManagementDescription' => ($lang === 'fa') ? 'محصولات، مخاطبان، استراتژی‌ها و آفرهای کمپین را در یک فضای منظم تعریف کنید.' : __('Define campaign products, audiences, strategies, and offers in one organized workspace.', 'smark'),
                'offerProductsTitle' => ($lang === 'fa') ? 'محصولات' : __('Products', 'smark'),
                'offerAudienceTypeTitle' => ($lang === 'fa') ? 'انواع مخاطب' : __('Audience Types', 'smark'),
                'offerStrategyTitle' => ($lang === 'fa') ? 'استراتژی‌ها' : __('Strategies', 'smark'),
                'offerOfferTitle' => ($lang === 'fa') ? 'آفرها' : __('Offers', 'smark'),
                'offerProductsDescription' => ($lang === 'fa') ? 'محصولات قابل ارائه در کمپین‌ها را تعریف و آماده کنید.' : __('Define the products available for your campaigns.', 'smark'),
                'offerAudienceTypeDescription' => ($lang === 'fa') ? 'گروه‌های مخاطب هدف را با نیاز و انگیزه مشخص کنید.' : __('Define audience groups by needs and motivation.', 'smark'),
                'offerStrategyDescription' => ($lang === 'fa') ? 'مسیر ارائه، پیام اصلی و منطق فروش را مشخص کنید.' : __('Define positioning, core message, and sales logic.', 'smark'),
                'offerOfferDescription' => ($lang === 'fa') ? 'پیشنهادها، مزیت‌ها و محرک‌های خرید را آماده کنید.' : __('Define incentives, benefits, and purchase triggers.', 'smark'),
                'offerProductName' => ($lang === 'fa') ? 'نام محصول' : __('Product name', 'smark'),
                'offerItemName' => ($lang === 'fa') ? 'عنوان' : __('Title', 'smark'),
                'offerProductPrice' => ($lang === 'fa') ? 'قیمت' : __('Price', 'smark'),
                'offerProductUrl' => ($lang === 'fa') ? 'لینک محصول' : __('Product URL', 'smark'),
                'offerProductNotes' => ($lang === 'fa') ? 'توضیحات' : __('Notes', 'smark'),
                'offerProductAdd' => ($lang === 'fa') ? 'افزودن محصول' : __('Add product', 'smark'),
                'offerItemAdd' => ($lang === 'fa') ? 'افزودن آیتم' : __('Add item', 'smark'),
                'offerProductUpdate' => ($lang === 'fa') ? 'به‌روزرسانی محصول' : __('Update product', 'smark'),
                'offerItemUpdate' => ($lang === 'fa') ? 'به‌روزرسانی آیتم' : __('Update item', 'smark'),
                'offerProductCancel' => ($lang === 'fa') ? 'انصراف' : __('Cancel', 'smark'),
                'offerProductEdit' => ($lang === 'fa') ? 'ویرایش' : __('Edit', 'smark'),
                'offerProductDelete' => ($lang === 'fa') ? 'حذف' : __('Delete', 'smark'),
                'offerProductEmpty' => ($lang === 'fa') ? 'هنوز محصولی اضافه نشده است.' : __('No products have been added yet.', 'smark'),
                'offerSectionEmpty' => ($lang === 'fa') ? 'هنوز آیتمی در این بخش اضافه نشده است.' : __('No items have been added to this section yet.', 'smark'),
                'offerProductSaved' => ($lang === 'fa') ? 'آیتم‌ها ذخیره شدند.' : __('Items saved.', 'smark'),
                'offerProductSaveError' => ($lang === 'fa') ? 'ذخیره آیتم‌ها انجام نشد.' : __('Items could not be saved.', 'smark'),
                'offerProductNameRequired' => ($lang === 'fa') ? 'نام محصول را وارد کنید.' : __('Enter a product name.', 'smark'),
                'offerItemNameRequired' => ($lang === 'fa') ? 'عنوان را وارد کنید.' : __('Enter a title.', 'smark'),
                'signalhireTitle' => ($lang === 'fa') ? 'تنظیمات جست‌وجوی مخاطب' : __('Contact Search Settings', 'smark'),
                'signalhireIntro' => ($lang === 'fa') ? 'این زیرساخت فعلا غیرفعال است. حداقل یکی از فیلدها را پر کنید تا تنظیمات جست‌وجوی آینده ذخیره شود.' : __('This infrastructure is currently inactive. Fill at least one field to save future contact search settings.', 'smark'),
                'signalhireProfileSection' => ($lang === 'fa') ? 'پروفایل' : __('Profile', 'smark'),
                'signalhireCompanySection' => ($lang === 'fa') ? 'شرکت' : __('Company', 'smark'),
                'signalhireProfileName' => ($lang === 'fa') ? 'نام پروفایل' : __('Profile name', 'smark'),
                'signalhireProfileLocation' => ($lang === 'fa') ? 'لوکیشن پروفایل' : __('Profile location', 'smark'),
                'signalhireJobTitle' => ($lang === 'fa') ? 'عنوان شغلی' : __('Job title', 'smark'),
                'signalhireDepartment' => ($lang === 'fa') ? 'دپارتمان' : __('Department', 'smark'),
                'signalhireSeniorityLevel' => ($lang === 'fa') ? 'سطح ارشدیت' : __('Seniority level', 'smark'),
                'signalhireYearsExperience' => ($lang === 'fa') ? 'سال‌های تجربه' : __('Years of experience', 'smark'),
                'signalhireEducation' => ($lang === 'fa') ? 'تحصیلات' : __('Education', 'smark'),
                'signalhireKeywords' => ($lang === 'fa') ? 'کلمات کلیدی' : __('Keywords', 'smark'),
                'signalhireCompanyName' => ($lang === 'fa') ? 'نام شرکت' : __('Company name', 'smark'),
                'signalhireCompanyLocation' => ($lang === 'fa') ? 'لوکیشن شرکت' : __('Company location', 'smark'),
                'signalhireIndustry' => ($lang === 'fa') ? 'صنعت' : __('Industry', 'smark'),
                'signalhireCompanySize' => ($lang === 'fa') ? 'اندازه شرکت' : __('Company size', 'smark'),
                'signalhireSave' => ($lang === 'fa') ? 'اجرای ایجنت' : __('Run agent', 'smark'),
                'signalhireSaved' => ($lang === 'fa') ? 'تنظیمات جست‌وجو ذخیره شد.' : __('Search settings saved.', 'smark'),
                'signalhireValidation' => ($lang === 'fa') ? 'حداقل یکی از فیلدها را پر کنید.' : __('Fill at least one field.', 'smark'),
                'signalhireInactive' => ($lang === 'fa') ? 'اجرای جست‌وجو فعلا غیرفعال است؛ فقط تنظیمات ذخیره می‌شود.' : __('Search execution is inactive for now; only settings are saved.', 'smark'),
                'backToDashboard' => ($lang === 'fa') ? 'بازگشت' : __('Back', 'smark'),
                'close' => ($lang === 'fa') ? 'بستن' : __('Close', 'smark'),
                'markCredit'     => ($lang === 'fa') ? 'مارک' : __('Mark', 'smark'),
                'markCreditBalance' => ($lang === 'fa') ? 'اعتبار مارک' : __('Mark Credit Balance', 'smark'),
                'language'       => __('Language', 'smark'),
                'settings'       => __('Settings', 'smark'),
                'chooseLanguage' => __('Choose language', 'smark'),
                'persian'        => __('Persian', 'smark'),
                'english'        => __('English', 'smark'),
                'dailyGuideTitle' => $this->get_dashboard_translation('daily_guide_title', $lang),
                'dailyGuideAllGood' => $this->get_dashboard_translation('daily_guide_all_good', $lang),
                'open'           => $this->get_dashboard_translation('daily_guide_open', $lang),
                'smartAction'    => $this->get_dashboard_translation('daily_guide_smart_action', $lang),
                'smartNotReady'  => ($lang === 'fa') ? 'این اکشن ایجنت هنوز برای این آیتم آماده نیست.' : 'Agent action is not implemented for this item yet.',
                'smartRunning'   => ($lang === 'fa') ? 'ایجنت در حال انجام کار است...' : 'Agent is working...',
                'smartDone'      => ($lang === 'fa') ? 'اکشن ایجنت انجام شد.' : 'Agent action completed.',
                'smartError'     => ($lang === 'fa') ? 'اکشن ایجنت ناموفق بود. لطفاً دوباره امتحان کنید.' : 'Agent action failed. Please try again.',
                'projectSettings' => $this->get_dashboard_translation('project_settings', $lang),
                'googleDocsConverter' => $this->get_dashboard_translation('google_docs_converter', $lang),
                'headlineAnalyzer' => $this->get_dashboard_translation('headline_analyzer', $lang),
                'competitorAnalysis' => $this->get_dashboard_translation('competitor_analysis', $lang),
            ),
        ));
    }

    private function ensure_prompt_bank_loaded() {
        if (class_exists('SMarkPromptBank', false)) {
            return true;
        }

        $core_path = defined('SMARK_CORE_PLUGIN_PATH')
            ? rtrim((string) SMARK_CORE_PLUGIN_PATH, '/\\') . '/features/prompt-bank/prompt-bank.php'
            : rtrim((string) WP_PLUGIN_DIR, '/\\') . '/SMark-Core/features/prompt-bank/prompt-bank.php';

        if (file_exists($core_path)) {
            require_once $core_path;
        }

        return class_exists('SMarkPromptBank', false);
    }

    private function ensure_gemini_app_loaded() {
        if (class_exists('SMarkGeminiApp', false)) {
            return true;
        }

        $core_path = defined('SMARK_CORE_PLUGIN_PATH')
            ? rtrim((string) SMARK_CORE_PLUGIN_PATH, '/\\') . '/features/gemini-app/gemini-app.php'
            : rtrim((string) WP_PLUGIN_DIR, '/\\') . '/SMark-Core/features/gemini-app/gemini-app.php';

        if (file_exists($core_path)) {
            require_once $core_path;
        }

        return class_exists('SMarkGeminiApp', false);
    }

    private function normalize_heading_text($text) {
        $text = is_string($text) ? $text : '';
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\\s+/u', ' ', $text);
        $text = trim((string) $text);
        return $text;
    }

    private function extract_headings_from_html($html) {
        $html = is_string($html) ? $html : '';
        if ($html === '' || !class_exists('DOMDocument')) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return array();
        }

        $out = array();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//h1|//h2|//h3');
        if ($nodes instanceof DOMNodeList) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($node->tagName);
                if (!in_array($tag, array('h1', 'h2', 'h3'), true)) {
                    continue;
                }
                $txt = $this->normalize_heading_text($node->textContent);
                if ($txt === '') {
                    continue;
                }
                $out[] = array('level' => $tag, 'text' => $txt);
                if (count($out) >= 60) {
                    break;
                }
            }
        }

        $seen = array();
        $unique = array();
        foreach ($out as $h) {
            $key = strtolower($h['level'] . '|' . $h['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $h;
        }

        return $unique;
    }

    private function fetch_page_headings_cached($url) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return array('ok' => false, 'headings' => array(), 'message' => 'Invalid URL');
        }

        $cache_key = 'smark_smart_headings_' . md5(strtolower($url));
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['headings']) && is_array($cached['headings'])) {
            return array('ok' => true, 'headings' => $cached['headings'], 'message' => '');
        }

        $resp = wp_remote_get($url, array(
            'timeout' => 8,
            'redirection' => 5,
            'user-agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (smart-headings)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
            'limit_response_size' => 800000,
        ));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'headings' => array(), 'message' => $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300 || $body === '') {
            return array('ok' => false, 'headings' => array(), 'message' => 'Fetch failed');
        }

        $headings = $this->extract_headings_from_html($body);
        set_transient($cache_key, array('headings' => $headings), 21600);

        return array('ok' => true, 'headings' => $headings, 'message' => '');
    }

    private function fetch_serper_serp_items($keyword) {
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($keyword === '') {
            return array('ok' => false, 'items' => array(), 'message' => 'Missing keyword');
        }

        $cache_key = 'smark_smart_serp_' . md5(strtolower($keyword));
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
            return array('ok' => true, 'items' => $cached['items'], 'message' => '');
        }

        $token = $this->get_central_sync_token();
        $endpoint = $this->get_central_endpoint(self::CENTRAL_SERPER_SEARCH_PATH);
        $payload = wp_json_encode(array(
            'keyword' => $keyword,
            'num' => 10,
            'site_url' => rtrim((string) home_url('/'), '/'),
            'source' => 'smark-plugin/daily-guide-smart',
        ));

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
        );
        if ($token !== '') {
            $headers['x-smark-sync-token'] = $token;
        }

        $resp = wp_remote_post($endpoint, array(
            'timeout' => 15,
            'redirection' => 3,
            'headers' => $headers,
            'body' => $payload,
            'user-agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (smart-serper)',
        ));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'items' => array(), 'message' => $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        if ($code === 401 || $code === 403) {
            return array('ok' => false, 'items' => array(), 'message' => 'Central connection is not configured');
        }
        if ($code < 200 || $code >= 300 || $body === '') {
            return array('ok' => false, 'items' => array(), 'message' => 'Search failed');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return array('ok' => false, 'items' => array(), 'message' => 'Invalid response');
        }

        if (isset($data['success']) && $data['success'] === false) {
            $msg = isset($data['message']) ? (string) $data['message'] : 'Search failed';
            return array('ok' => false, 'items' => array(), 'message' => $msg);
        }

        $items = array();
        $rows = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $link = isset($row['link']) ? esc_url_raw((string) $row['link']) : '';
            if ($link === '') {
                continue;
            }
            $items[] = array(
                'title' => isset($row['title']) ? $this->normalize_heading_text((string) $row['title']) : '',
                'link' => $link,
            );
            if (count($items) >= 10) {
                break;
            }
        }

        set_transient($cache_key, array('items' => $items), 21600);
        return array('ok' => true, 'items' => $items, 'message' => '');
    }

    private function gemini_generate_text($message) {
        if (!$this->ensure_gemini_app_loaded()) {
            return array('ok' => false, 'response' => '', 'message' => 'Gemini App is not available');
        }

        $gemini = new SMarkGeminiApp();
        if (!method_exists($gemini, 'get_api_key')) {
            return array('ok' => false, 'response' => '', 'message' => 'Gemini API is not available');
        }

        $api_key = (string) $gemini->get_api_key();
        $api_key = trim($api_key);
        if ($api_key === '' || $api_key === 'YOUR_API_KEY_HERE') {
            return array('ok' => false, 'response' => '', 'message' => 'Gemini API Key not configured');
        }

        $model = get_option('SMARK_gemini_model', 'gemini-1.5-flash');
        $model = is_string($model) && $model !== '' ? trim($model) : 'gemini-1.5-flash';

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);

        $body = wp_json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => (string) $message),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature' => 0.4,
                'maxOutputTokens' => 700,
                'topP' => 0.8,
                'topK' => 40,
            ),
        ));

        $resp = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => $body,
            'timeout' => 45,
        ));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'response' => '', 'message' => $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $raw = (string) wp_remote_retrieve_body($resp);
        if ($code !== 200) {
            $err_data = json_decode($raw, true);
            $err_msg = is_array($err_data) && isset($err_data['error']['message']) ? (string) $err_data['error']['message'] : 'Gemini API error';
            return array('ok' => false, 'response' => '', 'message' => $err_msg);
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['candidates']) || !is_array($data['candidates']) || empty($data['candidates'])) {
            return array('ok' => false, 'response' => '', 'message' => 'Invalid Gemini response');
        }

        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
            return array('ok' => false, 'response' => '', 'message' => 'Content blocked by safety filters');
        }

        $text = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = (string) $data['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (is_array($part) && isset($part['text']) && $part['text'] !== '') {
                    $text .= (string) $part['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            return array('ok' => false, 'response' => '', 'message' => 'Empty Gemini response');
        }

        return array('ok' => true, 'response' => $text, 'message' => '');
    }

    private function normalize_keyword_key($keyword) {
        $k = sanitize_text_field((string) $keyword);
        $k = trim($k);
        $k = preg_replace('/\\s+/u', ' ', $k);
        $k = is_string($k) ? trim($k) : '';
        if ($k === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($k, 'UTF-8') : strtolower($k);
    }

    private function resolve_current_project_id() {
        $project_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_id <= 0) {
            $legacy = (int) get_option('SMARK_current_project_db_id', 0);
            if ($legacy > 0) {
                $project_id = $legacy;
                update_option('smark_current_project_db_id', $project_id, false);
            }
        }
        if ($project_id <= 0) {
            $projects_table = $this->resolve_projects_table();
            $site_url = rtrim((string) home_url('/'), '/');
            if ($projects_table !== '' && $site_url !== '' && $this->table_has_column($projects_table, 'website')) {
                $resolved = (int) $this->resolve_project_db_id_for_website($projects_table, $site_url);
                if ($resolved > 0) {
                    $project_id = $resolved;
                    update_option('smark_current_project_db_id', $project_id, false);
                }
            }
        }
        return (int) $project_id;
    }

    public function ajax_daily_guide_smart_action() {
        check_ajax_referer('smark_daily_guide_smart_action', 'nonce');

        if (!current_user_can(self::CAP_ACCESS)) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $stage = 'input';

        try {

        $key = isset($_POST['key']) ? sanitize_key(wp_unslash((string) $_POST['key'])) : '';
        if ($key === '') {
            wp_send_json_error(array('message' => 'Missing key', 'stage' => $stage), 400);
        }

        if ($key !== 'keyword_no_page' && $key !== 'gap_transfer') {
            wp_send_json_error(array('message' => 'Not implemented', 'stage' => $stage), 400);
        }

        $lang = get_option('smark_panel_language', 'en');
        $lang = ($lang === 'fa') ? 'fa' : 'en';

        $stage = 'project_resolve';
        $project_id = $this->resolve_current_project_id();
        if ($project_id <= 0) {
            $msg = ($lang === 'fa') ? 'پروژه انتخاب نشده است.' : 'Project is not selected.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 400);
        }

        if ($key === 'gap_transfer') {
            $stage = 'gap_transfer';
            global $smark_keyword_gap;
            if (!is_object($smark_keyword_gap) || !method_exists($smark_keyword_gap, 'smart_transfer_one_keyword')) {
                $msg = ($lang === 'fa') ? 'ÙÛŒÚ†Ø± Ø´Ú©Ø§Ù Ú©Ù„Ù…Ø§Øª Ú©Ù„ÛŒØ¯ÛŒ Ø¯Ø± Ø¯Ø³ØªØ±Ø³ Ù†ÛŒØ³Øª.' : 'Keyword Gap feature is not available.';
                wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
            }

            $res = $smark_keyword_gap->smart_transfer_one_keyword($project_id);
            if (!is_array($res) || empty($res['ok'])) {
                $msg = is_array($res) && isset($res['message']) ? (string) $res['message'] : (($lang === 'fa') ? 'Ø§Ù†ØªÙ‚Ø§Ù„ Ú©Ù„Ù…Ù‡â€ŒÚ©Ù„ÛŒØ¯ÛŒ Ù†Ø§Ù…ÙˆÙÙ‚ Ø¨ÙˆØ¯.' : 'Keyword transfer failed.');
                $res_stage = is_array($res) && isset($res['stage']) ? (string) $res['stage'] : $stage;
                wp_send_json_error(array('message' => $msg, 'stage' => $res_stage), 500);
            }

            $kw = isset($res['keyword']) ? (string) $res['keyword'] : '';
            $kw = trim(preg_replace('/\\s+/u', ' ', $kw));
            if ($kw === '') {
                $msg = ($lang === 'fa') ? 'Ù‡ÛŒÚ† Ú©Ù„Ù…Ù‡â€ŒÚ©Ù„ÛŒØ¯ÛŒ Ù…Ù†Ø§Ø³Ø¨ÛŒ Ø¨Ø±Ø§ÛŒ Ø§Ù†ØªÙ‚Ø§Ù„ Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯.' : 'No transferable keyword was found.';
                wp_send_json_error(array('message' => $msg, 'stage' => $stage), 404);
            }

            $ai = ($lang === 'fa') ? ('Ú©Ù„Ù…Ù‡â€ŒÚ©Ù„ÛŒØ¯ÛŒ Ø¨Ù‡ ØªØ­Ù‚ÛŒÙ‚ Ú©Ù„Ù…Ø§Øª Ú©Ù„ÛŒØ¯ÛŒ Ø§Ø¶Ø§ÙÙ‡ Ø´Ø¯: ' . $kw) : ('Keyword added to Keyword Research: ' . $kw);

            wp_send_json_success(array(
                'keyword' => $kw,
                'aiResponse' => $ai,
                'prompt' => '',
                'sources' => array(),
                'openUrl' => admin_url('admin.php?page=smark-keyword-research'),
            ));
        }

        global $wpdb;
        $stage = 'kw_table';
        $kw_table = isset($wpdb->prefix) ? $wpdb->prefix . 'SMARK_keyword_research' : '';
        $kw_table_sql = $this->escape_db_identifier($kw_table);
        if ($kw_table_sql === '' || !$this->table_has_column($kw_table, 'page_link_status')) {
            $msg = ($lang === 'fa') ? 'جدول تحقیق کلمات کلیدی آماده نیست.' : 'Keyword Research table is not ready.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 400);
        }

        // Pick a random keyword with no page link.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $keyword = (string) $wpdb->get_var($wpdb->prepare('SELECT keyword FROM ' . $kw_table_sql . " WHERE project_id = %d AND (page_link_status = 'not_found' OR page_link_status = 'not_connected') ORDER BY RAND() LIMIT 1", $project_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $stage = 'kw_pick';
        $keyword = trim(preg_replace('/\\s+/u', ' ', $keyword));
        if ($keyword === '') {
            $msg = ($lang === 'fa') ? 'هیچ کلمه‌ای بدون لینک صفحه پیدا نشد.' : 'No keywords without a page link were found.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 404);
        }

        // 1) Ensure Content Management create-item exists for this keyword (and force type to blog post).
        $cm_create_opt = get_option('smark_cm_create_content', array());
        $cm_create_opt = is_array($cm_create_opt) ? $cm_create_opt : array();
        $pid_key = (string) (int) $project_id;
        $items = isset($cm_create_opt[$pid_key]) && is_array($cm_create_opt[$pid_key]) ? $cm_create_opt[$pid_key] : array();

        $needle = $this->normalize_keyword_key($keyword);
        $found_idx = -1;
        foreach ($items as $idx => $it) {
            if (!is_array($it)) {
                continue;
            }
            $it_kw = isset($it['keyword']) ? $this->normalize_keyword_key($it['keyword']) : '';
            if ($it_kw !== '' && $it_kw === $needle) {
                $found_idx = (int) $idx;
                break;
            }
        }

        if ($found_idx < 0) {
            $items[] = array(
                'keyword'   => $keyword,
                'postType'  => 'post',
                'postId'    => 0,
                'status'    => '',
                'updatedAt' => '',
                'editUrl'   => '',
                'createdAt' => current_time('mysql'),
            );
            $found_idx = count($items) - 1;
        } else {
            $items[$found_idx]['keyword'] = $keyword;
            $items[$found_idx]['postType'] = 'post';
        }

        $existing_post_id = isset($items[$found_idx]['postId']) ? (int) $items[$found_idx]['postId'] : 0;
        $post_id = $existing_post_id;

        // 2) Create draft blog post (if not already created or missing).
        if ($post_id > 0) {
            $post = get_post($post_id);
            if (!$post) {
                $post_id = 0;
            }
        }

        if ($post_id <= 0) {
            $stage = 'draft_create';
            $post_id = wp_insert_post(array(
                'post_title'   => $keyword,
                'post_type'    => 'post',
                'post_status'  => 'draft',
                'post_content' => '',
            ), true);

            if (is_wp_error($post_id) || !$post_id) {
                $msg = ($lang === 'fa') ? 'ایجاد پیش‌نویس ناموفق بود.' : 'Failed to create draft.';
                wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
            }
        }

        $post = get_post((int) $post_id);
        $updated_at = $post && isset($post->post_modified) ? (string) $post->post_modified : current_time('mysql');

        $items[$found_idx]['postId'] = (int) $post_id;
        $items[$found_idx]['postType'] = 'post';
        $items[$found_idx]['status'] = $post && isset($post->post_status) ? (string) $post->post_status : 'draft';
        $items[$found_idx]['updatedAt'] = $updated_at;
        $items[$found_idx]['editUrl'] = get_edit_post_link((int) $post_id, 'raw');

        $cm_create_opt[$pid_key] = array_values($items);
        update_option('smark_cm_create_content', $cm_create_opt, false);

        $cm_url = admin_url('admin.php?page=smark-content-management&focus_post_id=' . (string) (int) $post_id . '&smark_kw=' . rawurlencode($keyword));

        // 3) "Write content" (smart) -> Fetch SERP (top 10) + headings for each URL.
        $stage = 'serp_fetch';
        $serp = $this->fetch_serper_serp_items($keyword);
        if (empty($serp['ok'])) {
            $msg = isset($serp['message']) ? (string) $serp['message'] : 'SERP fetch failed';
            $msg = ($lang === 'fa') ? ('دریافت نتایج گوگل ناموفق بود. ' . $msg) : ('Failed to fetch Google results. ' . $msg);
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
        }

        $sources = array();
        $serp_enriched = array();
        $rows = isset($serp['items']) && is_array($serp['items']) ? $serp['items'] : array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $link = isset($row['link']) ? (string) $row['link'] : '';
            $link = $link !== '' ? esc_url_raw($link) : '';
            if ($link === '') {
                continue;
            }

            $sources[] = $link;
            $headings_res = $this->fetch_page_headings_cached($link);
            $headings = isset($headings_res['headings']) && is_array($headings_res['headings']) ? $headings_res['headings'] : array();

            $subtitles = array();
            foreach ($headings as $h) {
                if (!is_array($h)) {
                    continue;
                }
                $level = isset($h['level']) ? strtolower((string) $h['level']) : '';
                if ($level !== 'h2' && $level !== 'h3') {
                    continue;
                }
                $txt = isset($h['text']) ? $this->normalize_heading_text((string) $h['text']) : '';
                if ($txt === '') {
                    continue;
                }
                $subtitles[] = $txt;
                if (count($subtitles) >= 30) {
                    break;
                }
            }

            $serp_enriched[] = array(
                'title' => isset($row['title']) ? $this->normalize_heading_text((string) $row['title']) : '',
                'link' => $link,
                'subtitles' => $subtitles,
            );

            if (count($serp_enriched) >= 10) {
                break;
            }
        }

        $stage = 'headings_fetch';
        if (empty($serp_enriched)) {
            $msg = ($lang === 'fa') ? 'هیچ نتیجه‌ای برای صفحه اول گوگل دریافت نشد.' : 'No results were returned for the first page of Google.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
        }

        // 4) Build prompt via Prompt Bank and send to Gemini.
        $stage = 'prompt_bank_load';
        if (!$this->ensure_prompt_bank_loaded()) {
            $msg = ($lang === 'fa') ? 'بانک پرامپت در دسترس نیست.' : 'Prompt Bank is not available.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
        }

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        $project_name = '';
        $lang_name = ($lang === 'fa') ? 'Persian' : 'English';
        if ($projects_table_sql !== '' && $project_id > 0 && $this->table_has_column($projects_table, 'project_name')) {
            $cols = array('project_name');
            if ($this->table_has_column($projects_table, 'brand_language')) {
                $cols[] = 'brand_language';
            } elseif ($this->table_has_column($projects_table, 'language')) {
                $cols[] = 'language';
            }
            $cols_sql = implode(', ', $cols);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $proj = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$projects_table_sql} WHERE id = %d LIMIT 1", (int) $project_id), ARRAY_A);
            if (is_array($proj)) {
                $project_name = isset($proj['project_name']) ? sanitize_text_field((string) $proj['project_name']) : '';
                $lang_code = '';
                if (isset($proj['brand_language'])) {
                    $lang_code = sanitize_text_field((string) $proj['brand_language']);
                } elseif (isset($proj['language'])) {
                    $lang_code = sanitize_text_field((string) $proj['language']);
                }
                $lang_code = strtolower(trim($lang_code));
                if ($lang_code === 'fa') {
                    $lang_name = 'Persian';
                } elseif ($lang_code === 'en') {
                    $lang_name = 'English';
                }
            }
        }

        $list_lines = array();
        foreach ($serp_enriched as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $list_lines[] = (string) ((int) $i + 1) . ') ' . (isset($it['title']) ? (string) $it['title'] : '');
            $list_lines[] = 'URL: ' . (isset($it['link']) ? (string) $it['link'] : '');
            $subs = isset($it['subtitles']) && is_array($it['subtitles']) ? $it['subtitles'] : array();
            if (!empty($subs)) {
                $list_lines[] = 'Subtitles:';
                foreach ($subs as $s) {
                    $s = $this->normalize_heading_text((string) $s);
                    if ($s === '') {
                        continue;
                    }
                    $list_lines[] = '- ' . $s;
                }
            }
            $list_lines[] = '';
        }
        $serp_text = trim(implode("\n", $list_lines));
        $serp_json = wp_json_encode($serp_enriched, JSON_UNESCAPED_UNICODE);

        $stage = 'prompt_build';
        $prompt_data = SMarkPromptBank::get_prompt_by_key('pick_best_subtitles', array(
            'project_id' => (string) (int) $project_id,
            'project_name' => $project_name,
            'brand_name' => $project_name,
            'language' => $lang_name,
            'keyword' => $keyword,
            'serp_list' => $serp_text,
            'serp_json' => (string) $serp_json,
            'sources' => implode("\n", $sources),
        ));

        if (!$prompt_data || empty($prompt_data['prompt_content'])) {
            $msg = ($lang === 'fa') ? 'پرامپت pick_best_subtitles در بانک پرامپت پیدا نشد.' : 'Prompt pick_best_subtitles was not found in Prompt Bank.';
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 404);
        }

        $stage = 'ai_generate';
        $gem = $this->gemini_generate_text((string) $prompt_data['prompt_content']);
        if (empty($gem['ok'])) {
            $err = isset($gem['message']) ? (string) $gem['message'] : 'Gemini failed';
            $msg = ($lang === 'fa') ? ('دریافت پاسخ از هوش مصنوعی ناموفق بود. ' . $err) : ('Failed to get AI response. ' . $err);
            wp_send_json_error(array('message' => $msg, 'stage' => $stage), 500);
        }

        wp_send_json_success(array(
            'keyword' => $keyword,
            'postId'  => (int) $post_id,
            'editUrl' => (string) $items[$found_idx]['editUrl'],
            'contentManagementUrl' => $cm_url,
            'sources' => array_values(array_unique($sources)),
            'prompt' => (string) $prompt_data['prompt_content'],
            'aiResponse' => (string) $gem['response'],
        ));
        } catch (Throwable $e) {
            $msg = ($lang === 'fa')
                ? 'Ø¹Ù…Ù„ÛŒØ§Øª Ù‡ÙˆØ´Ù…Ù†Ø¯ Ø¨Ø§ Ø®Ø·Ø§ Ù…ØªÙˆÙ‚Ù Ø´Ø¯.'
                : 'Agent action failed with an exception.';

            $payload = array(
                'message' => $msg,
                'stage' => is_string($stage) && $stage !== '' ? $stage : 'exception',
            );
            if (current_user_can('manage_options')) {
                $payload['debug'] = array(
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                );
            }

            wp_send_json_error($payload, 500);
        }
    }

    private function get_dashboard_offer_products() {
        $sections = $this->get_dashboard_offer_sections();
        return isset($sections['product']) && is_array($sections['product']) ? $sections['product'] : array();
    }

    private function get_signalhire_contact_search_fields() {
        return array(
            'profile_name',
            'profile_location',
            'job_title',
            'department',
            'seniority_level',
            'years_experience',
            'education',
            'keywords',
            'company_name',
            'company_location',
            'industry',
            'company_size',
        );
    }

    private function sanitize_signalhire_contact_search_settings($settings) {
        $settings = is_array($settings) ? $settings : array();
        $clean = array();

        foreach ($this->get_signalhire_contact_search_fields() as $field) {
            $clean[$field] = isset($settings[$field]) ? sanitize_text_field((string) $settings[$field]) : '';
        }

        return $clean;
    }

    private function get_signalhire_contact_search_settings() {
        $settings = get_option('smark_signalhire_contact_search_settings', array());
        return $this->sanitize_signalhire_contact_search_settings($settings);
    }

    public function ajax_save_signalhire_contact_search_settings() {
        check_ajax_referer('smark_signalhire_contact_search_settings', 'nonce');

        if (!current_user_can(self::CAP_ACCESS)) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}';
        $decoded = json_decode((string) $raw_settings, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => __('Invalid settings payload', 'smark')), 400);
        }

        $settings = $this->sanitize_signalhire_contact_search_settings($decoded);
        $has_value = false;
        foreach ($settings as $value) {
            if (trim((string) $value) !== '') {
                $has_value = true;
                break;
            }
        }

        if (!$has_value) {
            wp_send_json_error(array('message' => __('Fill at least one field.', 'smark')), 400);
        }

        update_option('smark_signalhire_contact_search_settings', $settings, false);

        wp_send_json_success(array(
            'settings' => $settings,
            'message' => __('Search settings saved.', 'smark'),
        ));
    }

    private function get_dashboard_offer_sections() {
        $sections = get_option('smark_dashboard_offer_sections', array());
        $sections = is_array($sections) ? $sections : array();
        $legacy_products = get_option('smark_dashboard_offer_products', array());

        if (!isset($sections['product']) && is_array($legacy_products)) {
            $sections['product'] = $legacy_products;
        }

        $clean = array();
        foreach (array('product', 'audience_type', 'strategy', 'offer') as $section_key) {
            $items = isset($sections[$section_key]) && is_array($sections[$section_key]) ? $sections[$section_key] : array();
            $clean[$section_key] = $this->sanitize_dashboard_offer_items($items);
        }

        return $clean;
    }

    private function sanitize_dashboard_offer_items($items) {
        $items = is_array($items) ? $items : array();
        $clean = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = isset($item['name']) ? sanitize_text_field((string) $item['name']) : '';
            if ($name === '') {
                continue;
            }

            $clean[] = array(
                'id' => isset($item['id']) ? sanitize_key((string) $item['id']) : wp_generate_uuid4(),
                'name' => $name,
                'price' => isset($item['price']) ? sanitize_text_field((string) $item['price']) : '',
                'url' => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
                'notes' => isset($item['notes']) ? sanitize_textarea_field((string) $item['notes']) : '',
            );
        }

        return $clean;
    }

    public function ajax_dashboard_offer_products_save() {
        check_ajax_referer('smark_dashboard_offer_products', 'nonce');

        if (!current_user_can(self::CAP_ACCESS)) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $raw_payload = isset($_POST['sections']) ? wp_unslash($_POST['sections']) : (isset($_POST['products']) ? wp_unslash($_POST['products']) : '[]');
        $decoded = json_decode((string) $raw_payload, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => __('Invalid products payload', 'smark')), 400);
        }

        if (isset($_POST['sections'])) {
            $sections = array();
            foreach (array('product', 'audience_type', 'strategy', 'offer') as $section_key) {
                $sections[$section_key] = isset($decoded[$section_key]) && is_array($decoded[$section_key])
                    ? $this->sanitize_dashboard_offer_items($decoded[$section_key])
                    : array();
            }
        } else {
            $sections = $this->get_dashboard_offer_sections();
            $sections['product'] = $this->sanitize_dashboard_offer_items($decoded);
        }

        update_option('smark_dashboard_offer_sections', $sections, false);
        update_option('smark_dashboard_offer_products', $sections['product'], false);

        wp_send_json_success(array(
            'products' => $sections['product'],
            'sections' => $sections,
        ));
    }

    /**
     * AJAX handler for saving language preference
     */
    public function ajax_save_language() {
        check_ajax_referer('smark_social_media_nonce', 'nonce');

        if (!current_user_can(self::CAP_ACCESS)) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';

        if (empty($language) || !in_array($language, array('en', 'fa'))) {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        // Save language preference
        update_option('smark_panel_language', $language);

        wp_send_json_success(array(
            'message' => __('Language preference saved', 'smark'),
            'language' => $language
        ));
    }

    /**
     * Check database schema and update if needed
     */
    public function check_database_schema() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Check if brand_language column exists in projects table
        $projects_table = $wpdb->prefix . 'smark_projects';
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if (empty($projects_table_sql)) {
            return;
        }

        // Ensure manual_ai and manual_ai_allowed_users exist for permissioning.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema check requires direct query; identifier validated via escape_db_identifier().
        $manual_ai_exists = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM ' . $projects_table_sql . ' LIKE %s', 'manual_ai'));
        if (empty($manual_ai_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema change requires direct query; identifier validated via escape_db_identifier().
            $wpdb->query('ALTER TABLE ' . $projects_table_sql . ' ADD COLUMN manual_ai tinyint(1) NOT NULL DEFAULT 0');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema check requires direct query; identifier validated via escape_db_identifier().
        $allowed_users_exists = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM ' . $projects_table_sql . ' LIKE %s', 'manual_ai_allowed_users'));
        if (empty($allowed_users_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema change requires direct query; identifier validated via escape_db_identifier().
            $wpdb->query('ALTER TABLE ' . $projects_table_sql . ' ADD COLUMN manual_ai_allowed_users longtext NULL DEFAULT NULL');
        }

        $column_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$projects_table_sql} LIKE %s",
            'brand_language'
        ));
        if (empty($column_exists)) {
            $alter_result = $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$projects_table_sql} ADD COLUMN brand_language varchar(10) DEFAULT %s AFTER project_name",
                'fa'
            ));
            if ($alter_result !== false) {
                SMarkLogger::info('Added brand_language column to ' . $projects_table);
            } else {
                SMarkLogger::error('Failed to add brand_language column: ' . $wpdb->last_error);
            }
        }

        $project_id_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$projects_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($project_id_exists)) {
            $alter_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$projects_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER id"
            );
            if ($alter_result !== false) {
                SMarkLogger::info('Added project_id column (schema check) to ' . $projects_table);
            } else {
                SMarkLogger::error('Failed to add project_id column (schema check): ' . $wpdb->last_error);
            }
        }

        $projects_without_id = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SELECT id FROM {$projects_table_sql} WHERE project_id IS NULL OR project_id = %s",
            ''
        ), ARRAY_A);
        if (!empty($projects_without_id)) {
            foreach ($projects_without_id as $project_row) {
                $new_project_id = $this->generate_project_id($project_row['id']);
                $wpdb->update(
                    $projects_table,
                    array('project_id' => $new_project_id),
                    array('id' => $project_row['id']),
                    array('%s'),
                    array('%d')
                );
                SMarkLogger::debug('Backfilled project_id ' . $new_project_id . ' for project ' . $project_row['id']);
            }

            $has_unique_index = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "SHOW INDEX FROM {$projects_table_sql} WHERE Key_name = %s",
                'project_id_unique'
            ));
            if (empty($has_unique_index)) {
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                    "ALTER TABLE {$projects_table_sql} ADD UNIQUE KEY project_id_unique (project_id)"
                );
            }
        }

        $social_media_table = $wpdb->prefix . 'smark_social_media';
        $social_media_suggestions_table = $wpdb->prefix . 'smark_social_media_suggestions';
        $social_media_table_sql = $this->escape_db_identifier($social_media_table);
        $social_media_suggestions_table_sql = $this->escape_db_identifier($social_media_suggestions_table);
        if (empty($social_media_table_sql) || empty($social_media_suggestions_table_sql)) {
            return;
        }

        $sm_has_project_id = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$social_media_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($sm_has_project_id)) {
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$social_media_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER project"
            );
        }

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers are strictly validated.
            "UPDATE {$social_media_table_sql} sm INNER JOIN {$projects_table_sql} p ON sm.project = p.project_name SET sm.project_id = p.project_id WHERE sm.project_id IS NULL OR sm.project_id = ''"
        );

        $sms_has_project_id = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$social_media_suggestions_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($sms_has_project_id)) {
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$social_media_suggestions_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER project"
            );
        }

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers are strictly validated.
            "UPDATE {$social_media_suggestions_table_sql} sms INNER JOIN {$projects_table_sql} p ON sms.project = p.project_name SET sms.project_id = p.project_id WHERE sms.project_id IS NULL OR sms.project_id = ''"
        );

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Allow features to register rewrite rules before flushing.
        do_action('smark_register_rewrites');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Ensure tables exist (only on plugin pages)
     */
    public function ensure_tables_exist() {
        if (!is_admin() || !current_user_can(self::CAP_ACCESS)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && strpos((string) $screen->id, 'smark') === false) {
            return;
        }

        global $wpdb;

        // Check if social media suggestions table exists
        $social_media_suggestions_table = $wpdb->prefix . 'smark_social_media_suggestions';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_suggestions_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check requires direct query.

        if (!$table_exists) {
            SMarkLogger::warning('Social media suggestions table not found, creating...');
            $this->create_tables();
        }
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $charset_collate = $wpdb->get_charset_collate();

        // Create SMark Projects table
        $projects_table = $wpdb->prefix . 'smark_projects';
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if (empty($projects_table_sql)) {
            return;
        }
        $sql_projects = "CREATE TABLE $projects_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_name varchar(255) NOT NULL,
            brand_language varchar(10) DEFAULT 'fa',
            canva_template varchar(1000) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY project_name_unique (project_name)
        ) $charset_collate;";

        dbDelta($sql_projects);
        SMarkLogger::info('SMark Projects table created successfully: ' . $projects_table);

        // Add brand_language column to existing projects table if it doesn't exist
        $brand_language_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$projects_table_sql} LIKE %s",
            'brand_language'
        ));
        if (empty($brand_language_exists)) {
            $alter_result = $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$projects_table_sql} ADD COLUMN brand_language varchar(10) DEFAULT %s AFTER project_name",
                'fa'
            ));
            if ($alter_result !== false) {
                SMarkLogger::info('Added brand_language column to ' . $projects_table);
            } else {
                SMarkLogger::error('Failed to add brand_language column: ' . $wpdb->last_error);
            }
        }

        // Add canva_template column to existing projects table if it doesn't exist
        $canva_template_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$projects_table_sql} LIKE %s",
            'canva_template'
        ));
        if (empty($canva_template_exists)) {
            $alter_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$projects_table_sql} ADD COLUMN canva_template varchar(1000) DEFAULT NULL AFTER brand_language"
            );
            if ($alter_result !== false) {
                SMarkLogger::info('Added canva_template column to ' . $projects_table);
            } else {
                SMarkLogger::error('Failed to add canva_template column: ' . $wpdb->last_error);
            }
        }

        // Add project_id column to existing projects table if it doesn't exist
        $project_id_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$projects_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($project_id_exists)) {
            // Add the column
            $alter_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$projects_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER id"
            );
            if ($alter_result !== false) {
                SMarkLogger::info('Added project_id column to ' . $projects_table);

                // Generate project_id for existing projects
                $existing_projects = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                    "SELECT id FROM {$projects_table_sql} WHERE project_id IS NULL",
                    ARRAY_A
                );
                if ($existing_projects) {
                    foreach ($existing_projects as $project) {
                        $new_project_id = $this->generate_project_id($project['id']);
                        $wpdb->update(
                            $projects_table,
                            array('project_id' => $new_project_id),
                            array('id' => $project['id']),
                            array('%s'),
                            array('%d')
                        );
                        SMarkLogger::debug("Generated project_id: $new_project_id for project ID: " . $project['id']);
                    }
                }

                // Make project_id unique
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                    "ALTER TABLE {$projects_table_sql} ADD UNIQUE KEY project_id_unique (project_id)"
                );
                SMarkLogger::info('Added unique constraint to project_id column');

                // Remove unique constraint from project_name to allow name changes
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                    "ALTER TABLE {$projects_table_sql} DROP INDEX project_name_unique"
                );
                SMarkLogger::info('Removed unique constraint from project_name column');
            } else {
                SMarkLogger::error('Failed to add project_id column: ' . $wpdb->last_error);
            }
        } else {
            // Column exists, but check if there are any projects without project_id
            $projects_without_id = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "SELECT id FROM {$projects_table_sql} WHERE project_id IS NULL OR project_id = %s",
                ''
            ), ARRAY_A);
            if ($projects_without_id) {
                SMarkLogger::debug('Found ' . count($projects_without_id) . ' projects without project_id');
                foreach ($projects_without_id as $project) {
                    $new_project_id = $this->generate_project_id($project['id']);
                    $wpdb->update(
                        $projects_table,
                        array('project_id' => $new_project_id),
                        array('id' => $project['id']),
                        array('%s'),
                        array('%d')
                    );
                    SMarkLogger::debug("Generated project_id: $new_project_id for existing project ID: " . $project['id']);
                }
            }
        }

        // Create SMark Social Media table
        $social_media_table = $wpdb->prefix . 'smark_social_media';
        $sql_social_media = "CREATE TABLE $social_media_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project varchar(255) NOT NULL,
            headline text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_index (project)
        ) $charset_collate;";

        dbDelta($sql_social_media);
        SMarkLogger::info('SMark Social Media table created successfully: ' . $social_media_table);

        // Create SMark Social Media Suggestions table
        $social_media_suggestions_table = $wpdb->prefix . 'smark_social_media_suggestions';
        $sql_social_media_suggestions = "CREATE TABLE IF NOT EXISTS $social_media_suggestions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project varchar(255) NOT NULL,
            headline text NOT NULL,
            caption text DEFAULT NULL,
            visual varchar(500) DEFAULT NULL,
            visual_type varchar(50) DEFAULT NULL,
            visual_text text DEFAULT NULL,
            expert_approval_status varchar(20) DEFAULT 'needs_approval',
            score int(3) DEFAULT 0,
            source varchar(500) DEFAULT NULL,
            source_url text DEFAULT NULL,
            source_type varchar(50) DEFAULT 'manual',
            competitor_name varchar(255) DEFAULT NULL,
            published_date datetime DEFAULT NULL,
            discovered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_index (project),
            KEY source_type (source_type)
        ) $charset_collate;";

        dbDelta($sql_social_media_suggestions);

        // Check if table was created successfully
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_suggestions_table));
        if ($table_exists) {
            SMarkLogger::info('SMark Social Media Suggestions table created successfully: ' . $social_media_suggestions_table);
        } else {
            SMarkLogger::error('Failed to create SMark Social Media Suggestions table: ' . $social_media_suggestions_table);
        }

        // Add project_id column to social_media table if it doesn't exist
        $social_media_table_sql = $this->escape_db_identifier($social_media_table);
        if (empty($social_media_table_sql)) {
            return;
        }
        $sm_project_id_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$social_media_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($sm_project_id_exists)) {
            $alter_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$social_media_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER project"
            );
            if ($alter_result !== false) {
                SMarkLogger::info('Added project_id column to ' . $social_media_table);

                // Update existing records with project_id based on project_name
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers are strictly validated.
                    "UPDATE {$social_media_table_sql} sm INNER JOIN {$projects_table_sql} p ON sm.project = p.project_name SET sm.project_id = p.project_id WHERE sm.project_id IS NULL"
                );
                SMarkLogger::info('Updated existing social_media records with project_id');
            } else {
                SMarkLogger::error('Failed to add project_id column to social_media: ' . $wpdb->last_error);
            }
        }

        // Add project_id column to social_media_suggestions table if it doesn't exist
        $social_media_suggestions_table_sql = $this->escape_db_identifier($social_media_suggestions_table);
        if (empty($social_media_suggestions_table_sql)) {
            return;
        }
        $sms_project_id_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
            "SHOW COLUMNS FROM {$social_media_suggestions_table_sql} LIKE %s",
            'project_id'
        ));
        if (empty($sms_project_id_exists)) {
            $alter_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
                "ALTER TABLE {$social_media_suggestions_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER project"
            );
            if ($alter_result !== false) {
                SMarkLogger::info('Added project_id column to ' . $social_media_suggestions_table);

                // Update existing records with project_id based on project_name
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema setup requires direct query.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers are strictly validated.
                    "UPDATE {$social_media_suggestions_table_sql} sms INNER JOIN {$projects_table_sql} p ON sms.project = p.project_name SET sms.project_id = p.project_id WHERE sms.project_id IS NULL"
                );
                SMarkLogger::info('Updated existing social_media_suggestions records with project_id');
            } else {
                SMarkLogger::error('Failed to add project_id column to social_media_suggestions: ' . $wpdb->last_error);
            }
        }

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'smark_version' => SMARK_VERSION,
            'smark_activated' => current_time('mysql')
        );

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Add CSS class to admin body for SMark plugin pages
     */
    public function add_smark_body_class($classes) {
        $screen = get_current_screen();
        $screen_id = ($screen && isset($screen->id)) ? (string) $screen->id : '';
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection.

        if (
            strpos($screen_id, 'smark') !== false ||
            strpos($page, 'smark') === 0
        ) {
            $classes .= ' smark-plugin-page';
        }

        // Note: smark-gemini-app-page class is now only applied to the wrap element, not body

        return $classes;
    }

    /**
     * Generate unique project ID
     * Format: PRJ-XXXXX (e.g., PRJ-00001, PRJ-00002)
     */
    private function generate_project_id($database_id) {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'smark_projects';
        $projects_table_sql = $this->escape_db_identifier($projects_table);

        // Generate ID based on database ID with padding
        $project_id = 'PRJ-' . str_pad($database_id, 5, '0', STR_PAD_LEFT);

        // Check if this ID already exists (unlikely, but safe to check)
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!empty($projects_table_sql)) {
            $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_id = %s", $project_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated.
        } else {
            $exists = 0;
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // If it exists, add a random suffix
        if ($exists > 0) {
            $project_id = 'PRJ-' . str_pad($database_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
        }

        return $project_id;
    }
}

// Include logger (moved to SMark Core)
if (!class_exists('SMarkLogger', false)) {
    $smark_core_logger_path = rtrim((string) WP_PLUGIN_DIR, '/\\') . '/SMark-Core/features/plugin-logs/includes/SMark-logger.php';
    if (file_exists($smark_core_logger_path)) {
        require_once $smark_core_logger_path;
    }
}

if (!class_exists('SMarkLogger', false)) {
    class SMarkLogger {
        public static function log($level, $message, $context = array()) {
            $enabled = defined('WP_DEBUG') && (bool) WP_DEBUG;
            $enabled = (bool) apply_filters('smark_fallback_logger_enabled', $enabled, $level, $message, $context);
            if (!$enabled || !function_exists('error_log')) {
                return;
            }

            $context_str = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only fallback when SMark Core logger is unavailable.
            error_log("SMark Plugin [{$level}]: {$message}{$context_str}");
        }

        public static function info($message, $context = array()) { self::log('INFO', $message, $context); }
        public static function warning($message, $context = array()) { self::log('WARNING', $message, $context); }
        public static function error($message, $context = array()) { self::log('ERROR', $message, $context); }
        public static function debug($message, $context = array()) { self::log('DEBUG', $message, $context); }

        public static function log_gemini_call($action, $data = array()) { self::info("Gemini API Call: {$action}", $data); }
        public static function log_gemini_response($response, $success = true) { $m = $success ? 'info' : 'error'; self::{$m}('Gemini API Response', array('success' => $success, 'response' => $response)); }
        public static function log_gemini_error($error, $context = array()) { self::error("Gemini API Error: {$error}", $context); }
    }
}

// Ensure the backlinks feature page is registered early, so WordPress can resolve its hook and allow access.
add_action('admin_menu', function () {
    if (!function_exists('add_submenu_page')) {
        return;
    }

    add_submenu_page(
        null,
        __('Backlinks Management', 'smark'),
        __('Backlinks Management', 'smark'),
        SMarkPlugin::CAP_ACCESS,
        'smark-backlinks-management',
        'smark_render_backlinks_management_page'
    );
}, 0);

if (!function_exists('smark_render_backlinks_management_page')) {
    function smark_render_backlinks_management_page() {
        // Prefer the feature class if available.
        if (isset($GLOBALS['smark_backlinks_management']) && $GLOBALS['smark_backlinks_management'] instanceof SMarkBacklinksManagement) {
            $GLOBALS['smark_backlinks_management']->render_page();
            return;
        }

        if (class_exists('SMarkBacklinksManagement', false)) {
            $instance = new SMarkBacklinksManagement();
            if (method_exists($instance, 'render_page')) {
                $instance->render_page();
                return;
            }
        }

        wp_die(esc_html__('Backlinks feature is not available.', 'smark'), 500);
    }
}

// Force-enqueue Backlinks Management assets on its page (some environments don't call feature enqueue reliably).
add_action('admin_enqueue_scripts', function () {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check.
    if ($page !== 'smark-backlinks-management') {
        return;
    }

    $asset_version = defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0';

    wp_enqueue_style('dashicons');
    wp_enqueue_style(
        'vazirmatn-font',
        'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
        array(),
        $asset_version
    );

    wp_enqueue_style(
        'smark-backlinks-shell',
        SMARK_PLUGIN_URL . 'features/content-management/assets/content-management.css',
        array('dashicons', 'vazirmatn-font'),
        $asset_version
    );

    wp_enqueue_style(
        'smark-backlinks-management',
        SMARK_PLUGIN_URL . 'features/backlinks-management/assets/backlinks-management.css',
        array('smark-backlinks-shell'),
        $asset_version
    );

    wp_enqueue_script(
        'smark-backlinks-management',
        SMARK_PLUGIN_URL . 'features/backlinks-management/assets/backlinks-management.js',
        array('jquery'),
        $asset_version,
        true
    );

    add_filter('admin_body_class', function ($classes) {
        if (strpos((string) $classes, 'smark-plugin-page') === false) {
            $classes .= ' smark-plugin-page';
        }
        return $classes;
    });

    wp_localize_script('smark-backlinks-management', 'SMarkBacklinksManagement', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('SMARK_cm_nonce'),
        'lang' => (get_option('smark_panel_language', 'en') === 'fa') ? 'fa' : 'en',
    ));
}, 0);

// Global Mark top-up modal (used when user has insufficient Mark credits).
add_action('admin_enqueue_scripts', function () {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
    if ($page === '' || strpos($page, 'smark') !== 0) {
        return;
    }

    $asset_version = defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0';
    $css_path = SMARK_PLUGIN_PATH . 'assets/css/mark-modal.css';
    $js_path = SMARK_PLUGIN_PATH . 'assets/js/mark-modal.js';
    $css_version = (string) $asset_version;
    $js_version = (string) $asset_version;
    if (is_readable($css_path)) {
        $css_version .= '.' . (string) filemtime($css_path);
    }
    if (is_readable($js_path)) {
        $js_version .= '.' . (string) filemtime($js_path);
    }
    $lang = (get_option('smark_panel_language', 'en') === 'fa') ? 'fa' : 'en';

    wp_enqueue_style('dashicons');
    wp_enqueue_style('smark-mark-modal', SMARK_PLUGIN_URL . 'assets/css/mark-modal.css', array('dashicons'), $css_version);
 
    wp_enqueue_script('smark-mark-modal', SMARK_PLUGIN_URL . 'assets/js/mark-modal.js', array('jquery'), $js_version, true);
    wp_localize_script('smark-mark-modal', 'SMarkMarkModalConfig', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('SMARK_mark_topup'),
        'lang' => $lang,
        'strings' => array(
            'title' => ($lang === 'fa') ? 'کردیت مارک شما تمام شده است' : 'Your Mark credits are finished',
            'desc' => ($lang === 'fa')
                ? 'برای ادامه استفاده از امکانات اسمارک، همین حالا حساب‌تان را شارژ کنید.'
                : 'To continue using SMark features, please top up your account now.',
            'hint' => ($lang === 'fa')
                ? 'این عملیات نیازمند مارک است و بدون شارژ حساب قابل انجام نیست.'
                : 'This action requires Mark credits and cannot be completed without a top up.',
            'cta' => ($lang === 'fa') ? 'خرید ۵٬۰۰۰ مارک' : 'Buy 5,000 Mark',
            'later' => ($lang === 'fa') ? 'بعداً' : 'Not now',
        ),
    ));
}, 0);

/**
 * AJAX: Start Mark top-up flow (redirect user to central checkout session).
 */
add_action('wp_ajax_SMARK_mark_topup_start', function () {
    if (!is_admin()) {
        wp_send_json_error(array('message' => 'Invalid context.'), 400);
    }

    if (!current_user_can('smark_access') && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'SMARK_mark_topup')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
    }

    $amount = isset($_POST['amount']) ? absint($_POST['amount']) : 0;
    if ($amount < 5000) {
        wp_send_json_error(array('message' => 'Minimum top up is 5000.'), 400);
    }

    $central_token = '';
    if (defined('SMARK_CENTRAL_SYNC_TOKEN')) {
        $t = constant('SMARK_CENTRAL_SYNC_TOKEN');
        if (is_string($t) && trim($t) !== '') {
            $central_token = trim($t);
        }
    }
    if ($central_token === '') {
        $central_token = (string) get_option('smark_central_sync_token', '');
        $central_token = trim($central_token);
    }
    if ($central_token === '') {
        $central_token = (string) get_option('smark_core_sync_token', '');
        $central_token = trim($central_token);
    }
    if ($central_token === '' && is_multisite()) {
        $central_token = (string) get_site_option('smark_central_sync_token', '');
        $central_token = trim($central_token);
        if ($central_token === '') {
            $central_token = (string) get_site_option('smark_core_sync_token', '');
            $central_token = trim($central_token);
        }
    }

    $website = rtrim((string) home_url('/'), '/');
    $return_url = admin_url('admin.php?page=smark-project-settings');

    $fallback_init = (string) apply_filters('SMARK_central_mark_topup_init_url', 'https://saeedhasani.com/?smark_topup_init=1');
    $fallback_init = trim($fallback_init);
    $fallback_url = '';
    if ($fallback_init !== '') {
        $fallback_url = add_query_arg(array(
            'website' => $website,
            'amount' => $amount,
            'return_success' => $return_url,
            'return_fail' => $return_url,
        ), $fallback_init);
    }

    $endpoint_private = (string) apply_filters('SMARK_central_mark_topup_start_endpoint', 'https://saeedhasani.com/wp-json/smark-core/v1/marks/topup/start');
    $endpoint_public = (string) apply_filters('SMARK_central_mark_topup_start_public_endpoint', 'https://saeedhasani.com/wp-json/smark-core/v1/marks/topup/start-public');

    $endpoint_private = trim($endpoint_private);
    $endpoint_public = trim($endpoint_public);

    $payload = array(
        'website' => $website,
        'amount' => $amount,
        'return_success' => $return_url,
        'return_fail' => $return_url,
    );

    $args = array(
        'timeout' => 20,
        'headers' => array(
            'Content-Type' => 'application/json; charset=utf-8',
        ),
        'body' => wp_json_encode($payload),
    );

    $resp = null;
    if ($central_token !== '' && $endpoint_private !== '') {
        $args['headers']['x-smark-sync-token'] = $central_token;
        $resp = wp_remote_post($endpoint_private, $args);
    } elseif ($endpoint_public !== '') {
        $resp = wp_remote_post($endpoint_public, $args);
    } else {
        wp_send_json_error(array('message' => 'Central endpoint is not configured.'), 500);
    }

    if (is_wp_error($resp)) {
        if ($fallback_url !== '') {
            wp_send_json_success(array('checkout_url' => $fallback_url));
        }
        wp_send_json_error(array('message' => $resp->get_error_message()), 500);
    }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = (string) wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);
    if (!is_array($data)) {
        $data = array();
    }

    if ($code < 200 || $code >= 300) {
        $msg = isset($data['message']) ? (string) $data['message'] : 'Central server rejected the request.';
        wp_send_json_error(array('message' => $msg), $code > 0 ? $code : 500);
    }

    $checkout_url = isset($data['checkout_url']) ? (string) $data['checkout_url'] : '';
    if ($checkout_url === '') {
        if ($fallback_url !== '') {
            wp_send_json_success(array('checkout_url' => $fallback_url));
        }
        wp_send_json_error(array('message' => 'Central server did not return checkout_url.'), 500);
    }

    wp_send_json_success(array(
        'checkout_url' => $checkout_url,
    ));
});

/**
 * Admin notice: Mark top-up result (shown after returning from central payment).
 */
add_action('admin_notices', function () {
    if (!is_admin()) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
    $status = isset($_GET['smark_topup_status']) ? sanitize_key(wp_unslash($_GET['smark_topup_status'])) : '';
    if ($status === '') {
        return;
    }

    $lang = (get_option('smark_panel_language', 'en') === 'fa') ? 'fa' : 'en';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
    $amount = isset($_GET['smark_topup_amount']) ? absint($_GET['smark_topup_amount']) : 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
    $reason = isset($_GET['smark_topup_reason']) ? sanitize_text_field(wp_unslash($_GET['smark_topup_reason'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
    $session = isset($_GET['smark_topup_session']) ? sanitize_text_field(wp_unslash($_GET['smark_topup_session'])) : '';

    if ($status === 'success') {
        $msg = ($lang === 'fa')
            ? ('شارژ مارک شما با موفقیت انجام شد' . ($amount > 0 ? (' (+' . number_format_i18n($amount) . ' مارک)') : '') . '.')
            : ('Your Mark top up was successful' . ($amount > 0 ? (' (+' . number_format_i18n($amount) . ' Mark)') : '') . '.');

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        return;
    }

    if ($status === 'failed') {
        $base = ($lang === 'fa') ? 'خرید ناموفق بود.' : 'Purchase failed.';
        if ($reason !== '') {
            $base .= ($lang === 'fa') ? (' دلیل: ' . $reason . '.') : (' Reason: ' . $reason . '.');
        }

        $contact = ($lang === 'fa')
            ? 'اگر مبلغ از حساب شما کسر شده ولی مارک شارژ نشد، لطفاً با پشتیبانی تماس بگیرید.'
            : 'If you were charged but your Mark was not added, please contact support.';

        $support_url = (string) apply_filters('SMARK_support_url', 'https://saeedhasani.com/contact-us/');
        $support_url = $support_url !== '' ? $support_url : 'https://saeedhasani.com/contact-us/';

        $extra = ($session !== '') ? (' ' . (($lang === 'fa') ? ('کد پیگیری: ' . $session) : ('Tracking code: ' . $session))) : '';

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($base . $extra) . '</p><p><a href="' . esc_url($support_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($contact) . '</a></p></div>';
        return;
    }
});

// Include features
require_once SMARK_PLUGIN_PATH . 'features/google-docs-converter/google-docs-converter.php';
require_once SMARK_PLUGIN_PATH . 'features/headline-analyzer/headline-analyzer.php';
require_once SMARK_PLUGIN_PATH . 'features/social-media/social-media.php';
require_once SMARK_PLUGIN_PATH . 'features/seo-optimization/seo-optimization.php';
require_once SMARK_PLUGIN_PATH . 'features/email-marketing/email-marketing.php';
require_once SMARK_PLUGIN_PATH . 'features/keyword-research/keyword-research.php';
require_once SMARK_PLUGIN_PATH . 'features/keyword-gap/keyword-gap.php';
require_once SMARK_PLUGIN_PATH . 'features/content-management/content-management.php';
require_once SMARK_PLUGIN_PATH . 'features/backlinks-management/backlinks-management.php';
require_once SMARK_PLUGIN_PATH . 'features/competitor-analysis/competitor-analysis.php';
require_once SMARK_PLUGIN_PATH . 'features/project-settings/project-settings.php';

// Prompt Bank is now in SMark Core - only load if Core is not active.
// Use active plugins list (not class_exists) to avoid load-order issues.
if (!function_exists('smark_is_core_active')) {
    function smark_is_core_active() {
        if (defined('SMARK_CORE_ACTIVE') && (bool) constant('SMARK_CORE_ACTIVE')) {
            return true;
        }

        $is_core_plugin_file = static function ($plugin_file) {
            $plugin_file = (string) $plugin_file;
            return (bool) preg_match('#/SMark-Core\\.php$#i', $plugin_file);
        };

        foreach ((array) get_option('active_plugins', array()) as $plugin_file) {
            if ($is_core_plugin_file($plugin_file)) {
                return true;
            }
        }

        if (is_multisite()) {
            foreach (array_keys((array) get_site_option('active_sitewide_plugins', array())) as $plugin_file) {
                if ($is_core_plugin_file($plugin_file)) {
                    return true;
                }
            }
        }

        return false;
    }
}

$smark_core_active = smark_is_core_active();

new SMarkPlugin();
// Initialize Social Media feature globally
global $smark_social_media;
$smark_social_media = new SMarkSocialMedia();

// Initialize SEO Optimization feature globally
global $smark_seo_optimization;
$smark_seo_optimization = new SMarkSeoOptimization();

// Initialize Email Marketing feature globally
global $smark_email_marketing;
$smark_email_marketing = new SMarkEmailMarketing();

// Initialize Competitor Analysis feature globally
global $smark_competitor_analysis;
$smark_competitor_analysis = new SMarkCompetitorAnalysis();

// Initialize Project Management feature globally
// (Moved to SMark Core)

// Initialize Keyword Research feature globally (temporarily disabled for testing)
global $smark_keyword_research;
$smark_keyword_research = new SMarkKeywordResearch();

// Initialize Keyword Gap feature globally
global $smark_keyword_gap;
$smark_keyword_gap = new SMarkKeywordGap();

// Initialize Project Settings feature globally
global $smark_project_settings;
$smark_project_settings = new SMarkProjectSettings();

// Initialize Content Management feature globally
global $smark_content_management;
$smark_content_management = new SMarkContentManagement();

=== EDH Newsletter - Weekly & Monthly Digest ===
Contributors: EncodeDotHost
Tags: newsletter, email, digest, subscription, mailing-list
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 8.0

Double opt-in newsletter signup with automatic weekly and monthly post digests, GDPR tooling, spam protection, and theme-overridable email templates.

## What it does

Visitors subscribe through a form (block or shortcode), confirm by email, and then receive a digest of your published posts either weekly or monthly. Subscribers manage their own frequency, pause, resume, or unsubscribe through signed links in every email. Administrators manage the list, send test emails, trigger digests, and export CSV from the WordPress admin.

## Features

### Digests
- Weekly and monthly digests built from posts published in the period
- Send day and hour configured per frequency, in the site's timezone
- Sends are batched (100 recipients per batch by default) so large lists never depend on one long request
- Manual trigger buttons on the dashboard for testing

### Subscriptions
- Double opt-in: nothing is subscribed until the confirmation link is clicked
- Unconfirmed signups are removed automatically after 24 hours
- Subscribers can change frequency, pause, resume, or unsubscribe via signed links
- A preferences lookup form emails a management link to active subscribers

### Privacy and GDPR
- Consent checkbox with recorded date, version, IP address, and user agent
- Integrates with the WordPress personal data export and erasure tools
- Suggested privacy policy text added to the WordPress privacy guide
- Unsubscribed records are deleted after a configurable retention period

### Spam protection
- Honeypot field and a minimum fill time on both public forms
- Per-address and per-IP submission throttling
- Suppression list (addresses or whole domains) and disposable-domain blocking
- Optional Cloudflare Turnstile challenge

### Templates
- Four email templates: weekly digest, monthly digest, confirmation, welcome
- Override any template from your theme or child theme
- Brand colour and logo (chosen with the WordPress media picker)

### Admin
- Dashboard with subscriber counts, next scheduled runs, and delivery statistics
- Subscriber list with status and frequency filters, single and bulk actions (unsubscribe, resubscribe, delete)
- Add a subscriber by hand, with or without confirmation
- CSV export filtered by status
- Test email sender

## Installation

1. Upload the plugin folder to `/wp-content/plugins/edh-newsletter/` and activate it.
2. Go to **Newsletter > Settings** and set the send day and hour for each digest, and the From name and address.
3. Optionally set a brand colour and logo under the Template Settings tab.
4. Add a signup form to a page using the block or the shortcode below.

WordPress cron must be running for digests to send. On sites where visitors are infrequent, point a real cron job at `wp-cron.php`. The dashboard shows a warning when cron is disabled.

## Blocks

Search for "Newsletter" in the block inserter.

- **Newsletter Signup**: the signup form. Sidebar settings: title, description, button text, whether visitors can choose weekly or monthly, default frequency, and style (default, minimal, boxed, inline).
- **Newsletter Preferences**: the form where a subscriber requests a link to manage their preferences.

Both blocks are rendered on the server and produce the same markup as the shortcodes, so any styling applies to both.

## Shortcodes

Signup form with defaults:

```
[newsletter_signup]
```

All signup attributes:

```
[newsletter_signup title="Subscribe Now" description="Get our latest updates" button_text="Join Us" show_frequency="true" default_frequency="monthly" style="boxed"]
```

| Attribute | Values | Default |
|---|---|---|
| `title` | text | Subscribe to Our Newsletter |
| `description` | text | Get the latest updates delivered to your inbox. |
| `button_text` | text | Subscribe |
| `show_frequency` | `true` or `false` | `true` |
| `default_frequency` | `weekly` or `monthly` | `weekly` |
| `style` | `default`, `minimal`, `boxed`, `inline` | `default` |

Preferences lookup form:

```
[newsletter_preferences title="Manage Your Newsletter Preferences"]
```

The v1.x shortcode `[weekly_digest_signup]` still works and renders the signup form.

## Template customisation

Copy any file from the plugin's `templates/` folder into a `newsletter-templates/` folder in your theme and edit it there. A child theme is checked first, then the parent theme, then the plugin.

- `weekly-digest.php` and `monthly-digest.php`: the digest email
- `confirmation.php`: sent after signup, contains the confirmation link
- `welcome.php`: sent after confirmation

Variables available inside the digest templates: `$posts`, `$frequency`, `$blog_name`, `$blog_url`, `$unsubscribe_url`, `$manage_preferences_url`, and `$is_test`. The digest body is rendered once per send and the two URLs are substituted per recipient. If your template needs the `$subscriber` array, return `true` from the `edh_newsletter_render_per_recipient` filter.

## Settings reference

**Newsletter > Settings**
- Weekly digest: day of week and hour
- Monthly digest: day of month (clamped to the last day of shorter months) and hour
- Email: From name and From address
- Template: brand colour and logo

**Newsletter > Privacy**
- Require consent checkbox, privacy policy URL, consent version
- Data retention period for unsubscribed records
- Minimum form fill time, submissions per IP per hour
- Disposable-address blocking and the suppression list
- Cloudflare Turnstile site key and secret key

## Developer notes

Get a module instance with `edh_newsletter_get_module( 'subscriber_manager' )`. Module keys: `subscriber_manager`, `email_sender`, `template_manager`, `privacy_manager`, `digest_scheduler`, `spam_guard`, `frontend_forms`, `blocks`, and `admin_interface` (admin requests only).

Actions:
- `edh_newsletter_subscriber_created`, `edh_newsletter_subscription_confirmed`, `edh_newsletter_subscriber_updated`, `edh_newsletter_subscriber_unsubscribed`, `edh_newsletter_subscriber_deleted`
- `edh_newsletter_confirmation_sent`, `edh_newsletter_welcome_sent`, `edh_newsletter_email_sent`, `edh_newsletter_email_failed`
- `edh_newsletter_digest_sent` with `$frequency, $sent, $post_count, $failed`
- `edh_newsletter_weekly_digest_scheduled`, `edh_newsletter_monthly_digest_scheduled`
- `edh_newsletter_spam_blocked` with `$form, $reason`
- `edh_newsletter_expired_subscribers_cleaned`, `edh_newsletter_expired_tokens_cleaned`
- `edh_newsletter_modules_loaded`

Filters:
- `edh_newsletter_digest_post_args`: the `WP_Query` arguments for a digest
- `edh_newsletter_digest_batch_size`: recipients per batch (default 100)
- `edh_newsletter_render_per_recipient`: render the digest body separately for each recipient
- `edh_newsletter_template_content`: the rendered HTML of any template
- `edh_newsletter_disposable_email_domains`: extend the disposable-domain list
- `edh_newsletter_unsubscribe_reasons`: choices shown on the unsubscribe form
- `edh_newsletter_spam_min_seconds`, `edh_newsletter_spam_max_per_ip`, `edh_newsletter_spam_max_per_email`
- `edh_newsletter_client_ip`: supply the real client IP when behind a proxy

Cron hooks: `edh_newsletter_send_weekly_digest`, `edh_newsletter_send_monthly_digest`, `edh_newsletter_send_digest_batch`, `edh_newsletter_cleanup_expired_data`.

The plugin has no build step. The block editor script is plain JavaScript. `.distignore` lists the files that should not ship in a release archive.

## Migration from v1.x

Existing subscribers in the v1 table are copied to the new table on activation, the old send-time options are converted, the legacy cron event is removed, and the legacy shortcode keeps working.

## Requirements

- WordPress 6.3 or higher
- PHP 8.0 or higher
- MySQL 5.6 or MariaDB 10.0 or higher

## Changelog

### 2.1.0
- Native block editor blocks for the signup and preferences forms
- Spam protection: honeypot, minimum fill time, throttling, suppression list, disposable-domain block, optional Turnstile
- Digest sending is batched and the body is rendered once per run
- Scheduler computes send times in the site timezone and re-arms after each send
- Admin: add subscriber, bulk actions, resubscribe, CSV export, run cleanup, native media picker for the logo
- Security and correctness fixes; see CODE_REVIEW.md in the repository
- Requires WordPress 6.3 and PHP 8.0

### 2.0.0
- Complete rewrite with modular architecture
- Monthly digest support
- GDPR compliance features
- Theme-overridable templates
- New admin interface

### 1.1
- Legacy version, migrated automatically

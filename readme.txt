=== EDH Newsletter - Weekly & Monthly Digest ===
Contributors: EncodeDotHost
Tags: newsletter, email, digest, subscription, mailing-list
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 2.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Double opt-in newsletter signup with automatic weekly and monthly post digests, GDPR tooling, spam protection, and theme-overridable email templates.

== Description ==

Visitors subscribe through a form (block or shortcode), confirm by email, and then receive a digest of your published posts either weekly or monthly. Subscribers manage their own frequency, pause, resume, or unsubscribe through signed links in every email. Administrators manage the list, send test emails, trigger digests, and export CSV from the WordPress admin.

= Digests =

* Weekly and monthly digests built from posts published in the period
* Send day and hour configured per frequency, in the site's timezone
* Sends are batched (100 recipients per batch by default) so large lists never depend on one long request
* Manual trigger buttons on the dashboard for testing

= Subscriptions =

* Double opt-in: nothing is subscribed until the confirmation link is clicked
* Unconfirmed signups are removed automatically after 24 hours
* Subscribers can change frequency, pause, resume, or unsubscribe via signed links
* A preferences lookup form emails a management link to active subscribers

= Privacy and GDPR =

* Consent checkbox with recorded date, version, IP address, and user agent
* Integrates with the WordPress personal data export and erasure tools
* Suggested privacy policy text added to the WordPress privacy guide
* Unsubscribed records are deleted after a configurable retention period

= Spam protection =

* Honeypot field and a minimum fill time on both public forms
* Per-address and per-IP submission throttling
* Suppression list (addresses or whole domains) and disposable-domain blocking
* Optional Cloudflare Turnstile challenge

= Templates =

* Four email templates: weekly digest, monthly digest, confirmation, welcome
* Override any template from your theme or child theme
* Brand colour and logo (chosen with the WordPress media picker)

= Admin =

* Dashboard with subscriber counts, next scheduled runs, and delivery statistics
* Subscriber list with status and frequency filters, single and bulk actions (unsubscribe, resubscribe, delete)
* Add a subscriber by hand, with or without confirmation
* CSV export filtered by status
* Test email sender

= Blocks =

Search for "Newsletter" in the block inserter.

* **Newsletter Signup**: the signup form. Sidebar settings: title, description, button text, whether visitors can choose weekly or monthly, default frequency, and style (default, minimal, boxed, inline).
* **Newsletter Preferences**: the form where a subscriber requests a link to manage their preferences.

Both blocks are rendered on the server and produce the same markup as the shortcodes, so any styling applies to both.

= Shortcodes =

Signup form with defaults:

`[newsletter_signup]`

All signup attributes:

`[newsletter_signup title="Subscribe Now" description="Get our latest updates" button_text="Join Us" show_frequency="true" default_frequency="monthly" style="boxed"]`

* `title` - text (default: Subscribe to Our Newsletter)
* `description` - text (default: Get the latest updates delivered to your inbox.)
* `button_text` - text (default: Subscribe)
* `show_frequency` - true or false (default: true)
* `default_frequency` - weekly or monthly (default: weekly)
* `style` - default, minimal, boxed, or inline (default: default)

Preferences lookup form:

`[newsletter_preferences title="Manage Your Newsletter Preferences"]`

The v1.x shortcode `[weekly_digest_signup]` still works and renders the signup form.

= Template customisation =

Copy any file from the plugin's `templates/` folder into a `newsletter-templates/` folder in your theme and edit it there. A child theme is checked first, then the parent theme, then the plugin.

* `weekly-digest.php` and `monthly-digest.php`: the digest email
* `confirmation.php`: sent after signup, contains the confirmation link
* `welcome.php`: sent after confirmation

Variables available inside the digest templates: `$posts`, `$frequency`, `$blog_name`, `$blog_url`, `$unsubscribe_url`, `$manage_preferences_url`, and `$is_test`. The digest body is rendered once per send and the two URLs are substituted per recipient. If your template needs the `$subscriber` array, return true from the `edh_newsletter_render_per_recipient` filter.

= Developer notes =

Get a module instance with `edh_newsletter_get_module( 'subscriber_manager' )`. Module keys: `subscriber_manager`, `email_sender`, `template_manager`, `privacy_manager`, `digest_scheduler`, `spam_guard`, `frontend_forms`, `blocks`, and `admin_interface` (admin requests only).

Actions: `edh_newsletter_subscriber_created`, `edh_newsletter_subscription_confirmed`, `edh_newsletter_subscriber_updated`, `edh_newsletter_subscriber_unsubscribed`, `edh_newsletter_subscriber_deleted`, `edh_newsletter_confirmation_sent`, `edh_newsletter_welcome_sent`, `edh_newsletter_email_sent`, `edh_newsletter_email_failed`, `edh_newsletter_digest_sent`, `edh_newsletter_weekly_digest_scheduled`, `edh_newsletter_monthly_digest_scheduled`, `edh_newsletter_spam_blocked`, `edh_newsletter_expired_subscribers_cleaned`, `edh_newsletter_expired_tokens_cleaned`, `edh_newsletter_modules_loaded`.

Filters: `edh_newsletter_digest_post_args`, `edh_newsletter_digest_batch_size`, `edh_newsletter_render_per_recipient`, `edh_newsletter_template_content`, `edh_newsletter_disposable_email_domains`, `edh_newsletter_unsubscribe_reasons`, `edh_newsletter_spam_min_seconds`, `edh_newsletter_spam_max_per_ip`, `edh_newsletter_spam_max_per_email`, `edh_newsletter_client_ip`.

Cron hooks: `edh_newsletter_send_weekly_digest`, `edh_newsletter_send_monthly_digest`, `edh_newsletter_send_digest_batch`, `edh_newsletter_cleanup_expired_data`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/edh-newsletter/` and activate it.
2. Go to Newsletter > Settings and set the send day and hour for each digest, and the From name and address.
3. Optionally set a brand colour and logo under the Template Settings tab.
4. Add a signup form to a page using the Newsletter Signup block or the `[newsletter_signup]` shortcode.

WordPress cron must be running for digests to send. On sites where visitors are infrequent, point a real cron job at `wp-cron.php`. The dashboard shows a warning when cron is disabled.

== Frequently Asked Questions ==

= Where are the settings? =

Newsletter > Settings holds the digest schedule, From address, brand colour and logo. Newsletter > Privacy holds consent, retention, and spam protection settings.

= Why did a subscriber not receive the digest? =

Check that WordPress cron is running (the dashboard warns if it is not), that the subscriber's status is Subscribed, and that their frequency matches the digest that was sent. Delivery counts are shown on the dashboard.

= Can I change the email design? =

Yes. Copy a template from the plugin's `templates/` folder into `newsletter-templates/` in your theme and edit it. See Template customisation above.

= Does it work with page builders that are not the block editor? =

Yes. Use the `[newsletter_signup]` and `[newsletter_preferences]` shortcodes.

= Is subscriber data included in WordPress privacy requests? =

Yes. Newsletter data is exported and erased through Tools > Export Personal Data and Tools > Erase Personal Data.

= Upgrading from version 1.x =

Existing subscribers in the v1 table are copied to the new table on activation, the old send-time options are converted, the legacy cron event is removed, and the legacy shortcode keeps working.

== Changelog ==

= 2.1.0 =
* Native block editor blocks for the signup and preferences forms
* Spam protection: honeypot, minimum fill time, throttling, suppression list, disposable-domain block, optional Turnstile
* Digest sending is batched and the body is rendered once per run
* Scheduler computes send times in the site timezone and re-arms after each send
* Admin: add subscriber, bulk actions, resubscribe, CSV export, run cleanup, native media picker for the logo
* Security and correctness fixes
* Requires WordPress 6.3 and PHP 8.0

= 2.0.0 =
* Complete rewrite with modular architecture
* Monthly digest support
* GDPR compliance features
* Theme-overridable templates
* New admin interface

= 1.1 =
* Legacy version, migrated automatically

== Upgrade Notice ==

= 2.1.0 =
Requires WordPress 6.3 and PHP 8.0. Digest schedules are recalculated in the site timezone on first load after updating.

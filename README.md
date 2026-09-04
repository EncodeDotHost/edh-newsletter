=== EDH Newsletter - Weekly & Monthly Digest ===
Contributors: EncodeDotHost
Tags: newsletter, email, digest, subscription, mailing-list
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 8.0

A comprehensive WordPress newsletter plugin with GDPR compliance, multi-frequency support, and advanced template customization.

## Features

### 📧 **Multi-Frequency Digests**
- Weekly and monthly digest options
- Smart scheduling with timezone awareness
- Configurable send times for each frequency

### 🔒 **Privacy & GDPR Compliance**
- Double opt-in subscription process
- Explicit consent tracking with versioning
- WordPress privacy tools integration
- Automatic data retention and cleanup
- Secure unsubscribe and preference management

### 🎨 **Advanced Templates**
- Customizable email templates
- Brand color and logo support
- Mobile-responsive design
- Theme template overrides
- Multiple template types (digest, confirmation, welcome)

### 🛡️ **Spam Protection**
- Honeypot field and minimum fill time on every public form
- Per-address and per-IP submission throttling
- Suppression list (addresses or whole domains) and disposable-domain blocking
- Optional Cloudflare Turnstile challenge

### 🎛️ **Modern Admin Interface**
- Comprehensive dashboard with analytics
- Advanced subscriber management
- Bulk actions and filtering
- Test email functionality
- Privacy compliance monitoring

### 🔧 **Developer Features**
- Modular architecture with dependency injection
- Hook-based extensibility
- PSR-4 autoloading
- Comprehensive API
- Legacy compatibility

## Installation

1. Upload the plugin files to `/wp-content/plugins/edh-newsletter/`
2. Activate the plugin through the WordPress admin
3. Configure settings in **Newsletter > Settings**
4. Add signup forms using shortcodes or widgets

## Blocks

Both forms are available as native blocks in the block editor (search for "Newsletter"):

- **Newsletter Signup** — the signup form, with title, description, button text, frequency chooser, default frequency and style settings in the sidebar.
- **Newsletter Preferences** — the preferences lookup form.

The blocks are server-rendered and produce exactly the same markup as the shortcodes below, so existing styling applies to both.

## Shortcodes

### Basic Signup Form
```
[newsletter_signup]
```

### Customized Signup Form
```
[newsletter_signup title="Subscribe Now" description="Get our latest updates" button_text="Join Us" default_frequency="monthly" style="boxed"]
```

### Preferences Management
```
[newsletter_preferences]
```

### Legacy Support
```
[weekly_digest_signup]
```

## Template Customization

Create a `newsletter-templates` folder in your active theme and copy template files from the plugin to customize:

- `weekly-digest.php` - Weekly digest email
- `monthly-digest.php` - Monthly digest email  
- `confirmation.php` - Subscription confirmation
- `welcome.php` - Welcome email

## Privacy Compliance

The plugin includes comprehensive GDPR compliance features:

- ✅ Double opt-in required
- ✅ Consent tracking and versioning
- ✅ WordPress privacy tools integration
- ✅ Data export and erasure support
- ✅ Automatic data retention policies
- ✅ Secure token-based actions

## Migration from v1.x

The plugin automatically migrates from version 1.x:

- Existing subscribers are preserved
- Settings are converted to new format
- Legacy shortcodes continue to work
- Database is upgraded seamlessly

## System Requirements

- WordPress 6.3 or higher
- PHP 8.0 or higher
- MySQL 5.6 or higher

## Support

For support and documentation, visit the plugin settings page in your WordPress admin.

## Changelog

### Version 2.1.0
- Native block editor blocks for the signup and preferences forms
- Spam protection: honeypot, timing check, throttling, suppression list, optional Turnstile
- Digest sending is batched and rendered once per run
- Scheduler computes send times in the site timezone
- Security and correctness fixes (see CODE_REVIEW.md)
- Requires WordPress 6.3 and PHP 8.0

### Version 2.0.0
- Complete rewrite with modular architecture
- Added monthly digest support
- Implemented GDPR compliance features
- Enhanced template system
- Modern admin interface
- Improved security and performance

### Version 1.1
- Legacy version (automatically upgraded)

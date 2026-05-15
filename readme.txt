=== SmartRecur Calendar ===
Contributors: smartrecur
Tags: calendar, appointments, scheduling, msp, recurring, office365, syncro
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.4
Stable tag: 2026.05.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recurring appointment scheduler for MSPs. Integrates with Office 365, Syncro MSP, Invoice Ninja, and Zoho.

== Description ==

SmartRecur Calendar turns WordPress into a full-featured appointment scheduler for managed service providers:

* Powerful recurrence rules (yearly, half-yearly, quarterly, monthly with relative or absolute date patterns)
* Office 365 calendar sync + Mail.Send via Microsoft Graph
* Syncro MSP customer import and ticket creation
* Invoice Ninja customer sync
* Zoho CRM / Books connection
* Per-service email templates and reminder schedules
* Native shortcode and Elementor V3 widget
* Compatible with LiteSpeed Cache, Imunify360, FluentSMTP, WP Mail SMTP

Authentication is fully handled by WordPress — pair this plugin with a dedicated 2FA plugin
(WP 2FA, Two-Factor) for MFA, and let Imunify360 or similar handle brute-force protection.

== Installation ==

1. Upload the `smart-recurr-calendar` directory to `/wp-content/plugins/`.
2. Activate it from the Plugins screen.
3. Visit **SmartRecur > Integrations** to connect external services.
4. Add `[smartrecur]` to any page or drop the **SmartRecur Calendar** Elementor widget where you want the booking UI to appear.

For Office 365, define the OAuth client secret in `wp-config.php`:

`define( 'SMARTRECUR_O365_CLIENT_SECRET', 'your-secret-here' );`

== Capabilities ==

The plugin adds four capabilities mapped onto the default roles at activation:

* `smartrecur_manage` — full admin (administrator).
* `smartrecur_book` — create/edit appointments (administrator, editor).
* `smartrecur_view` — read-only access (administrator, editor, subscriber).
* `smartrecur_manage_clients` — CRUD on client records (administrator, editor).

== REST endpoints ==

All endpoints live under the `smartrecur/v1` namespace and require a valid `X-WP-Nonce` plus the appropriate capability:

* `GET|POST /appointments`, `GET|PUT|DELETE /appointments/{id}`, `POST /appointments/generate`
* `GET|POST /clients`, `PUT|DELETE /clients/{id}`
* `GET|POST /services`, `PUT|DELETE /services/{id}`
* `GET|POST /technicians`, `PUT|DELETE /technicians/{id}`
* `GET|POST /recurring-rules`
* `GET /calendar`
* `GET|PUT /settings`, `GET /settings/backup`, `POST /settings/restore`
* `GET /integrations/status`, `PUT /integrations/{type}/config`, `POST /integrations/{type}/test`
* Office 365: `POST /integrations/office365/connect`, `GET /integrations/office365/callback`, `POST /integrations/office365/disconnect`, `POST /integrations/office365/sync`, `GET /integrations/office365/calendars`
* Syncro: `POST /integrations/syncro/import`, `POST /integrations/syncro/tickets`
* Invoice Ninja: `POST /integrations/invoiceninja/import`
* Email: `POST /integrations/email/send`, `GET /email-logs`

== Frequently Asked Questions ==

= What database tables are created? =

`wp_smartrecur_appointments`, `wp_smartrecur_clients`, `wp_smartrecur_assets`, `wp_smartrecur_services`, `wp_smartrecur_technicians`, `wp_smartrecur_recurring_rules`, `wp_smartrecur_email_logs`, `wp_smartrecur_integration_configs`. All names use the configured WordPress table prefix.

= Will my data survive a plugin reinstall? =

Yes by default. Enable **Settings > Data Management > Delete data on uninstall** if you want the tables dropped when the plugin is removed.

= How do I migrate from the standalone PHP/MariaDB SmartRecur? =

Use **SmartRecur > Migration Tool** and provide the legacy DB credentials. Customers, services, technicians, and events are copied over.

== Changelog ==

= 2026.05.3 =
* New: WordPress dashboard widget showing upcoming SmartRecur appointments. Visible to anyone with the `smartrecur_view` capability; honours the per-user "Screen Options" hide/show.
* New: SmartRecur → Settings → Dashboard Widget section to enable/disable the widget, cap the number of items shown, and set how many days ahead to scan.
* Updater: switched from GitHub Releases API to a `release` branch model. The updater reads `smart-recurr-calendar.php` on the `release` branch to detect new versions and uses GitHub's auto-generated branch archive as the download. Releases now go out via a single `git push` to the release branch — no GitHub UI step.

= 2026.05.2 =
* Self-updater: plugin now checks GitHub Releases every 12 hours and surfaces new versions through the standard WordPress Plugins → Updates flow. "Check for updates" link added to the plugin row.
* Dashboard: neutral grey container background that holds up on light and dark WordPress admin themes.
* Versioning convention: feature releases bump the third segment (2026.05.2), patch releases add a fourth (2026.05.2.1).

= 2026.05.1 =
* First public WordPress plugin release.
* Replaces the legacy JWT authentication and custom users table with the native WordPress user system + capabilities (`smartrecur_manage`, `smartrecur_book`, `smartrecur_view`, `smartrecur_manage_clients`).
* Every endpoint moves to the WordPress REST API under `smartrecur/v1` with `permission_callback`, capability checks, and `X-WP-Nonce` enforcement.
* Tables created automatically via `dbDelta()` on activation; schema version tracked in `smartrecur_db_version` for future incremental migrations.
* Integration secrets (Office 365, Syncro MSP, Invoice Ninja, Zoho) encrypted at rest with `AUTH_SALT`-derived keys and never returned in REST responses.
* OAuth state lives in per-user transients to prevent concurrent-flow collisions.
* `[smartrecur]` shortcode + Elementor V3 widget; Tailwind styles scoped under `.smartrecur-wrap`.
* Email switched to `wp_mail()` so any WP SMTP plugin (WP Mail SMTP, FluentSMTP, etc.) is honoured.
* Includes a one-shot migration tool that pulls customers / services / technicians / events from a legacy standalone SmartRecur MariaDB database.

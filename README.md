# SmartRecur Calendar

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

This repository now ships **two** flavors of SmartRecur:

| Layout                                | Purpose                                                              |
|---------------------------------------|----------------------------------------------------------------------|
| `plugin/smart-recurr-calendar/`       | **WordPress plugin** (current — recommended).                       |
| Root files (`api/`, `database/`, …)   | Legacy standalone PHP + MariaDB app, kept for reference / migration. |

---

## WordPress plugin

### Requirements

- WordPress 6.0+
- PHP 8.4+
- A WordPress 2FA plugin (WP 2FA, Two-Factor) if you need MFA — SmartRecur no longer reimplements TOTP.

### Install

1. Copy `plugin/smart-recurr-calendar/` into `wp-content/plugins/`.
2. Activate **SmartRecur Calendar** from the Plugins screen.
3. (Optional) Define the Office 365 client secret in `wp-config.php`:

   ```php
   define( 'SMARTRECUR_O365_CLIENT_SECRET', 'your-secret-here' );
   ```

4. Visit **SmartRecur > Integrations** to wire up Office 365, Syncro, Invoice Ninja, and Zoho.
5. Add the calendar to any page either way:

   - Shortcode: `[smartrecur view="calendar"]`
   - Elementor: drag the **SmartRecur Calendar** widget into your layout.

### Capabilities

| Capability                  | Granted to            | Allows                                          |
|-----------------------------|-----------------------|-------------------------------------------------|
| `smartrecur_manage`         | administrator         | Full admin (settings, integrations, all CRUD)   |
| `smartrecur_book`           | administrator, editor | Create / edit / cancel appointments             |
| `smartrecur_manage_clients` | administrator, editor | CRUD on clients                                 |
| `smartrecur_view`           | administrator, editor, subscriber | Read-only calendar access           |

### REST endpoints

All endpoints sit under the `smartrecur/v1` namespace and require a valid `X-WP-Nonce` + the matching capability:

- `GET|POST /appointments`, `GET|PUT|DELETE /appointments/{id}`, `POST /appointments/generate`
- `GET|POST /clients` (`/customers` alias), `PUT|DELETE /clients/{id}`
- `GET|POST /services`, `PUT|DELETE /services/{id}`
- `GET|POST /technicians`, `PUT|DELETE /technicians/{id}`
- `GET|POST /recurring-rules`
- `GET /calendar`
- `GET|PUT /settings`, `GET /settings/backup`, `POST /settings/restore`
- `GET /integrations/status`, `PUT /integrations/{type}/config`, `POST /integrations/{type}/test`
- Office 365: `POST /integrations/office365/connect`, `GET /integrations/office365/callback`, `POST /integrations/office365/disconnect`, `POST /integrations/office365/sync`, `GET /integrations/office365/calendars`
- Syncro MSP: `POST /integrations/syncro/import`, `POST /integrations/syncro/tickets`
- Invoice Ninja: `POST /integrations/invoiceninja/import`
- Email: `POST /integrations/email/send`, `GET /email-logs`

### Building the React bundle

The compiled assets ship inside the plugin (`assets/js/smartrecur-app.js`, `assets/css/smartrecur-app.css`) so the plugin works without running npm on the server. To rebuild:

```bash
cd plugin/smart-recurr-calendar
npm install
npm run build
```

The output overwrites `assets/js/` and `assets/css/`. The React source lives in `plugin/smart-recurr-calendar/src/`.

### Database tables

Created on activation via `dbDelta()` with the configured `$wpdb->prefix`:

`smartrecur_appointments`, `smartrecur_clients`, `smartrecur_assets`, `smartrecur_services`, `smartrecur_technicians`, `smartrecur_recurring_rules`, `smartrecur_email_logs`, `smartrecur_integration_configs`.

Schema version is stored as the `smartrecur_db_version` option so future plugin upgrades can run incremental migrations.

### Migrating from the standalone version

Use **SmartRecur > Migration Tool** in the WP admin and provide the legacy database DSN. The migrator copies customers, services, technicians, and events into the prefixed WP tables. The old `users` table is ignored — WordPress handles users now.

### Security at a glance

- Every REST endpoint enforces `permission_callback` + `current_user_can()`. No `__return_true` on data endpoints.
- WordPress nonces (`X-WP-Nonce`) are required for every state-changing call.
- All queries use `$wpdb->prepare()`.
- Integration secrets (Office 365 / Syncro / Invoice Ninja / Zoho tokens) are encrypted at rest with `AUTH_SALT`-derived keys via `sodium_crypto_secretbox` or AES-256-CBC + HMAC.
- Integration responses never return secrets — only `••••••••` placeholders.
- Outbound HTTP from integrations is SSRF-guarded (HTTPS-only, public-IP-only, plus host whitelists for Zoho/Syncro).
- Rate limiting on booking endpoints via transients.

---

## Legacy standalone application

The original PHP + MariaDB app under the repository root remains in place. See the previous README revisions in git history for installation instructions if you want to run that version, or use the WordPress plugin's migration tool to bring its data forward.

The standalone schema and source files are kept for reference and to seed the migration tool — they are not required at runtime once you're on the WordPress plugin.

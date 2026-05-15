# SmartRecur Calendar

WordPress plugin: recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

The compiled, installable plugin lives at [`plugin/smart-recurr-calendar/`](plugin/smart-recurr-calendar). The release tree at the root of the [`release` branch](https://github.com/mamhuijb/Smart-Recurr-Calendar/tree/release) is what end users download.

## Install

End users:

1. WordPress → **Plugins → Add New → Upload Plugin**
2. Upload the latest zip from the [Releases page](https://github.com/mamhuijb/Smart-Recurr-Calendar/releases) (or pull `https://github.com/mamhuijb/Smart-Recurr-Calendar/archive/refs/heads/release.zip` directly).
3. Activate.

The plugin creates its own tables (`{prefix}smartrecur_*`), seeds defaults, and registers capabilities on activation. No manual setup needed.

## Self-update

Plugin checks the GitHub `release` branch every 12 hours. New versions appear in the standard WordPress **Plugins → Updates** screen. Click "Update Now" — that's it.

To force an immediate check: **Plugins → SmartRecur Calendar row → "Check for updates"**.

## Capabilities

| Capability                  | Default roles                                   |
|-----------------------------|-------------------------------------------------|
| `smartrecur_manage`         | administrator                                   |
| `smartrecur_book`           | administrator, editor                           |
| `smartrecur_manage_clients` | administrator, editor                           |
| `smartrecur_view`           | administrator, editor, subscriber               |

## Office 365

The plugin uses a multi-tenant Microsoft Graph app with PKCE so end users don't need to register their own Azure app. Site admins click **Connect to Office 365** in the Integrations screen, consent on Microsoft's page, then pick which calendar to sync. No client secret. No per-site Azure setup.

Two-way sync runs automatically once connected:

- Every appointment created/updated/deleted in SmartRecur is pushed to Outlook in real time.
- WP-Cron pulls changes from Outlook every 15 minutes and creates/updates SmartRecur appointments to match.

### One-time Azure app setup (plugin maintainer)

The bundled flow needs ONE Azure app registration owned by the plugin author. This is configured once and then every site that installs the plugin uses it via PKCE (no client secret, no per-site setup).

1. Sign in to https://portal.azure.com.
2. **Microsoft Entra ID → App registrations → New registration**.
3. Name: `SmartRecur Calendar`.
4. **Supported account types**: *Accounts in any organizational directory and personal Microsoft accounts (multitenant)*.
5. **Redirect URI**: leave empty for now (we add it programmatically per install) — but for testing, add one of type "Single-page application" pointing at `https://your-site.example/wp-json/smartrecur/v1/integrations/office365/callback`.
6. Click **Register**.
7. Copy the **Application (client) ID**.
8. **Authentication → Platform configurations → Add a platform → Single-page application** (this enables PKCE without a client secret) and add `https://your-site.example/wp-json/smartrecur/v1/integrations/office365/callback`. Repeat for any additional install URLs, or use the wildcard subdomain pattern via "Web" platform if your customers all share a domain.
9. **API permissions → Add → Microsoft Graph → Delegated**: `User.Read`, `Calendars.ReadWrite`, `Mail.Send`, `offline_access`. Click **Grant admin consent** for your tenant (each end-user tenant will consent themselves on first connect).

Then in the plugin source, set the bundled client ID:

```php
// includes/integrations/class-smartrecur-office365.php → client_id()
return 'YOUR-COPIED-APPLICATION-CLIENT-ID';
```

Or per-site (overrides the bundled value), define it in `wp-config.php`:

```php
define( 'SMARTRECUR_O365_CLIENT_ID', 'tenant-or-site-specific-client-id' );
```

## REST API

Namespace: `smartrecur/v1`. Every endpoint requires `X-WP-Nonce` and the matching capability.

- `GET|POST /appointments`, `GET|PUT|DELETE /appointments/{id}`, `POST /appointments/generate`
- `GET|POST /clients`, `PUT|DELETE /clients/{id}`
- `GET|POST /services`, `PUT|DELETE /services/{id}`
- `GET|POST /technicians`, `PUT|DELETE /technicians/{id}`
- `GET|POST /recurring-rules`
- `GET /calendar`
- `GET|PUT /settings`, `GET /settings/backup`, `POST /settings/restore`
- `GET /integrations/status`, `PUT /integrations/{type}/config`, `POST /integrations/{type}/test`
- Office 365: `POST /integrations/office365/connect`, `GET /integrations/office365/callback`, `POST /integrations/office365/disconnect`, `POST /integrations/office365/sync-now`, `GET /integrations/office365/calendars`, `POST /integrations/office365/select-calendar`
- Syncro: `POST /integrations/syncro/import`, `POST /integrations/syncro/tickets`
- Invoice Ninja: `POST /integrations/invoiceninja/import`
- Email: `POST /integrations/email/send`, `GET /email-logs`

## Building from source

```bash
cd plugin/smart-recurr-calendar
npm install
npm run build      # outputs assets/js/smartrecur-app.js + assets/css/smartrecur-app.css
```

The compiled bundle is committed alongside the source so the plugin works without npm on the server.

## Repo layout

- [`plugin/smart-recurr-calendar/`](plugin/smart-recurr-calendar) — the plugin (source + compiled assets).
- [`release` branch](https://github.com/mamhuijb/Smart-Recurr-Calendar/tree/release) — same contents, flattened to the branch root, used by the auto-updater.

## License

GPL-2.0-or-later.

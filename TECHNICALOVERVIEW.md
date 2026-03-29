# SmartRecur Calendar — Technical Overview

Version 2.1 | Last updated: March 2026

---

## 1. Architecture

```
                    Browser (Desktop / Mobile)
                            |
                      HTTPS (TLS 1.3)
                            |
                    +-----------------+
                    |   LiteSpeed     |
                    |   Web Server    |
                    +-----------------+
                       |          |
              Static files    .htaccess rewrite
              (app.html,      /api/* -> api/index.php
               assets/)
                              |
                    +-----------------+
                    |   PHP 8.1+      |
                    |   API Router    |
                    +-----------------+
                       |          |
                  Controllers   Integrations
                       |          |
                    +-----------------+
                    |   MariaDB 10.5+ |
                    |   (InnoDB)      |
                    +-----------------+
                              |
                 External APIs (Office 365,
                 Syncro MSP, Invoice Ninja, Zoho)
```

The application follows a **SPA + API** pattern:
- The **frontend** is a single-page React application served as static files
- The **backend** is a PHP API that handles all business logic, authentication, and database access
- They communicate via JSON over REST endpoints

---

## 2. Technology Stack

### Frontend

| Technology | Version | Purpose |
|------------|---------|---------|
| **React** | 18.2 | UI component framework |
| **TypeScript** | 5.2 | Type-safe JavaScript |
| **Vite** | 5.0 | Build tool and dev server |
| **Tailwind CSS** | 3.x (CDN) | Utility-first CSS framework |
| **Lucide React** | 0.563 | Icon library (tree-shakeable SVG icons) |
| **QRCode.react** | 4.2 | QR code generation for 2FA setup |
| **OTPAuth** | 9.4 | TOTP secret generation for 2FA |

The frontend is compiled by Vite into a single JavaScript bundle (`assets/index-{hash}.js`) and an HTML entry point (`app.html`). These built files are committed to git so the production server never needs Node.js.

### Backend

| Technology | Version | Purpose |
|------------|---------|---------|
| **PHP** | 8.1+ | Server-side scripting language |
| **MariaDB** | 10.5+ | Relational database (MySQL compatible) |
| **PDO** | (bundled) | Database abstraction layer |
| **cURL** | (bundled) | HTTP client for external API calls |
| **OpenSSL** | (bundled) | Cryptographic operations (JWT, Web Push) |

No PHP frameworks or Composer packages are used. The entire backend is written with zero external dependencies — all functionality (JWT, SMTP, Web Push, TOTP) is implemented from scratch using PHP's built-in extensions.

### Server

| Technology | Purpose |
|------------|---------|
| **Plesk** | Server management panel |
| **LiteSpeed** | Web server (Apache-compatible .htaccess) |
| **Let's Encrypt** | TLS/SSL certificates (managed by Plesk) |

---

## 3. Programming Languages

| Language | Usage | Lines (approx) |
|----------|-------|-----------------|
| **TypeScript/JSX** | Frontend components, API client, utilities | ~6,000 |
| **PHP** | Backend API, controllers, helpers, integrations | ~4,500 |
| **SQL** | Database schema, queries (via PDO prepared statements) | ~200 |
| **HTML/CSS** | Entry point, Tailwind utilities, custom styles | ~220 |

---

## 4. Project Structure

```
/
├── app.html                 # Production entry point (built by Vite)
├── assets/                  # Built JS/CSS bundles (hashed filenames)
├── index.html               # Vite dev template (not used in production)
├── index.tsx                # React entry point
├── App.tsx                  # Main application component
├── types.ts                 # TypeScript type definitions
├── .htaccess                # LiteSpeed/Apache routing and security
├── .env.example             # Environment variable template
├── manifest.json            # PWA manifest
├── sw.js                    # Service worker for push notifications
│
├── components/
│   ├── AdminPanel.tsx       # Admin panel with sidebar + 17 tabs
│   ├── CalendarGrid.tsx     # Month/week calendar view
│   ├── EditEventModal.tsx   # Appointment edit modal
│   ├── EventCreator.tsx     # New appointment wizard
│   ├── LoginScreen.tsx      # Authentication UI
│   ├── ReminderDashboard.tsx# Email reminder queue preview
│   ├── ScheduledJobs.tsx    # Upcoming appointments sidebar
│   └── CronJobsTab.tsx      # Cron job management tab
│
├── services/
│   └── api.ts               # API client (fetch-based, JWT auth)
│
├── utils/
│   ├── recurrenceEngine.ts  # Date generation for recurring appointments
│   ├── authSecurity.ts      # TOTP secret generation
│   ├── notificationManager.ts # Web Push subscription manager
│   └── toast.ts             # Non-blocking toast notifications
│
├── api/
│   ├── index.php            # API router (pattern matching, CORS, error handling)
│   ├── config/
│   │   ├── app.php          # Application constants (JWT secret, expiry)
│   │   └── database.php     # PDO singleton with connection error handling
│   ├── controllers/
│   │   ├── AuthController.php        # Login, 2FA, JWT token issuance
│   │   ├── EventController.php       # Appointment CRUD
│   │   ├── CustomerController.php    # Customer CRUD with email validation
│   │   ├── ServiceController.php     # Service type management
│   │   ├── TechnicianController.php  # Technician profiles
│   │   ├── SettingsController.php    # App settings, backup/restore
│   │   ├── IntegrationController.php # Integration orchestrator
│   │   ├── PushController.php        # Web Push subscriptions and sending
│   │   └── CronController.php        # Cron job status, settings, manual trigger
│   ├── helpers/
│   │   ├── Env.php           # .env file parser
│   │   ├── JWT.php           # HS256 JWT encode/decode
│   │   ├── UUID.php          # UUID v4 generation
│   │   ├── Response.php      # JSON response wrapper with exit
│   │   ├── RateLimiter.php   # IP-based rate limiting (file-based)
│   │   ├── SmtpMailer.php    # Raw SMTP client (no dependencies)
│   │   └── WebPush.php       # Web Push with VAPID (ECDH + HKDF)
│   ├── integrations/
│   │   ├── Office365.php     # Microsoft Graph API (OAuth2, calendar, mail)
│   │   ├── SyncroMSP.php     # Syncro MSP REST API
│   │   ├── InvoiceNinja.php  # Invoice Ninja v5 API
│   │   └── Zoho.php          # Zoho CRM/Books API
│   ├── middleware/
│   │   └── AuthMiddleware.php # JWT token verification
│   └── cron/
│       └── send-reminders.php # Scheduled reminder email/push sender
│
├── database/
│   └── schema.sql            # Full database schema with defaults
│
├── vite.config.ts            # Vite build configuration
├── tsconfig.json             # TypeScript compiler configuration
└── package.json              # Node.js dependencies and build scripts
```

---

## 5. Database Schema

11 tables in MariaDB, all InnoDB with UTF-8mb4:

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `users` | Admin accounts | username, password_hash (bcrypt), 2FA secret |
| `customers` | Client companies/contacts | company, name, email, syncro_id, invoiceninja_id |
| `assets` | Customer equipment | name, type, FK to customers |
| `services` | Service templates | name, duration, location type, color, email template |
| `technicians` | Staff profiles | name, email, color, skills (JSON) |
| `events` | Appointments | title, customer, service, tech, recurrence_rule, generated_dates (JSON), start/end time |
| `settings` | Key-value config store | setting_key (PK), setting_value (JSON) |
| `email_logs` | Email send history | recipient, subject, status, method, error |
| `reminder_log` | Duplicate prevention | event_id + target_date + reminder_day (unique) |
| `cron_logs` | Cron execution history | job_name, status, duration, email/push counts |
| `integration_configs` | API credentials | id (office365/syncro/etc), config (JSON), connected status |
| `push_subscriptions` | Web Push endpoints | endpoint, p256dh key, auth key |

---

## 6. Authentication & Security

### JWT Authentication
- Tokens signed with HS256 (HMAC-SHA256)
- Default expiry: 24 hours (configurable via `JWT_EXPIRY`)
- Token sent as `Authorization: Bearer <token>` header
- Frontend stores token in localStorage with session timestamp

### Password Security
- Hashed with bcrypt (cost factor 12)
- Verified with `password_verify()` (constant-time comparison)

### Two-Factor Authentication (2FA)
- TOTP (Time-based One-Time Password) per RFC 6238
- 30-second time steps with 1-step drift tolerance
- Secrets generated as Base32-encoded random bytes
- QR codes generated client-side for authenticator app scanning

### Rate Limiting
- Login: 5 attempts per 5 minutes per IP address
- File-based tracking in `/tmp/smartrecur_ratelimit/`

### CORS
- Origin validated against `CORS_ORIGIN` environment variable
- Preflight `OPTIONS` requests handled automatically

### HTTP Method Override
- PUT/DELETE requests sent as POST with `X-HTTP-Method-Override` header
- Bypasses ModSecurity/Imunify360 WAF rules on Plesk/CloudLinux

### Security Headers
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Strict-Transport-Security` (when HTTPS)
- CSP, XSS-Protection, Referrer-Policy (via .htaccess)

### Global Error Handler
- All PHP errors caught and returned as JSON (never raw HTML)
- Detailed error messages only shown when `APP_DEBUG=true`
- Fatal errors caught via `register_shutdown_function()`

---

## 7. API Endpoints

68 endpoints organized by resource:

### Health & Auth
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/health` | No | Server status, DB connectivity |
| POST | `/auth/login` | No | Username/password login |
| POST | `/auth/verify-2fa` | No | TOTP code verification |
| GET | `/auth/me` | JWT | Current user info |

### CRUD Resources (all require JWT)
| Resource | GET list | POST create | PUT update | DELETE |
|----------|----------|-------------|------------|--------|
| `/events` | List all | Create | Update by ID | Delete by ID |
| `/customers` | List all | Create | Update by ID | Delete by ID |
| `/services` | List all | Create | Update by ID | Delete by ID |
| `/technicians` | List all | Create | Update by ID | Delete by ID |

### Settings & Admin (all require JWT)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/settings` | Load all settings |
| PUT | `/settings` | Update settings |
| GET | `/settings/backup` | Export full backup as JSON |
| POST | `/settings/restore` | Restore from backup JSON |

### Integrations (all require JWT)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/integrations/status` | All integration connection statuses |
| PUT | `/integrations/:type/config` | Save integration config |
| POST | `/integrations/:type/test` | Test integration connection |
| GET | `/integrations/office365/auth-url` | Start OAuth2 flow |
| GET | `/integrations/office365/callback` | OAuth2 redirect handler |
| GET | `/integrations/office365/calendars` | List O365 calendars |
| POST | `/integrations/office365/calendar-event` | Create O365 calendar event |
| GET | `/integrations/syncro/customers` | Fetch Syncro customers |
| POST | `/integrations/syncro/tickets` | Create Syncro ticket |
| POST | `/integrations/email/send` | Send test email |
| GET | `/email-logs` | Email send history |
| POST | `/integrations/invoiceninja/sync-customers` | Sync InvoiceNinja clients |

### Push Notifications (all require JWT)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/push/config` | VAPID public key and settings |
| POST | `/push/subscribe` | Register push subscription |
| POST | `/push/unsubscribe` | Remove push subscription |
| POST | `/push/test` | Send test push to all devices |

### Cron Management
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/cron/status` | JWT | Job status, settings, recent logs |
| PUT | `/cron/settings` | JWT | Update frequency, enable/disable |
| POST | `/cron/run` | JWT | Manual trigger from admin panel |
| DELETE | `/cron/logs` | JWT | Clear old log entries |
| GET | `/cron/send-reminders` | Token | Actual cron execution endpoint |

---

## 8. Integrations

### Office 365 (Microsoft Graph API)
- **Auth:** OAuth 2.0 Authorization Code flow
- **Scopes:** User.Read, Calendars.ReadWrite, Mail.Send
- **Features:** Token refresh, calendar sync, email delivery via Mail.Send
- **Token storage:** Encrypted in `integration_configs` table

### Syncro MSP
- **Auth:** API key in header
- **Features:** Customer import, ticket creation linked to appointments
- **Validation:** Subdomain regex validation, SSRF protection

### Invoice Ninja
- **Auth:** API key in X-API-Token header
- **Features:** Client sync with duplicate detection by invoiceninja_id
- **Validation:** Endpoint URL validation, private IP blocking

### Zoho CRM/Books
- **Auth:** API key/OAuth token
- **Features:** Contact integration
- **Validation:** Endpoint hostname validation

### SMTP Email
- **Implementation:** Raw socket SMTP client (no external libraries)
- **Features:** STARTTLS/SSL support, authentication, HTML/plain text
- **Timeout:** 10 second connection timeout

---

## 9. Recurring Appointment Engine

The recurrence engine (`utils/recurrenceEngine.ts`) generates dates based on rules:

| Pattern | Example |
|---------|---------|
| Yearly | Every year on March 15 |
| Half-yearly | Every 6 months from start date |
| Quarterly | Every 3 months from start date |
| Monthly | Every month on day X |
| Custom | Every N months on day X |

**Date generation respects:**
- Business hours (configurable start/end time)
- Closed days (e.g. Sunday)
- Holidays (configurable per year)
- Manual closures (one-off blocked dates)
- Day clamping (e.g. Jan 31 monthly → Feb 28)

Generated dates are stored as a JSON array in the `events.generated_dates` column for fast querying without runtime calculation.

---

## 10. Email Reminder System

The cron job (`api/cron/send-reminders.php`) runs on a schedule and:

1. Loads all `SCHEDULED` events with their customers, services, and technicians
2. For each event, checks every generated date against configured reminder days (default: 14, 7, 1 days before)
3. Checks the `reminder_log` table to prevent duplicate sends
4. Sends via SMTP or Office 365 Mail.Send (configurable priority)
5. Logs results to `email_logs` and `reminder_log`
6. Sends Web Push notifications alongside emails
7. Records execution metrics in `cron_logs`

**Email templates** support placeholders:
`{customer_name}`, `{service_name}`, `{date}`, `{company_name}`, `{tech_name}`, `{location_type}`, `{link}`

Per-service templates override the global template.

---

## 11. Push Notifications

**Protocol:** Web Push API with VAPID (Voluntary Application Server Identification)

- VAPID key pairs are auto-generated on first use and stored in the `settings` table
- Subscriptions stored in `push_subscriptions` table
- Encryption uses ECDH key agreement + HKDF + AES-128-GCM (RFC 8291)
- Implemented entirely in PHP without external libraries (`api/helpers/WebPush.php`)
- Requires HTTPS (browser requirement for Service Workers)

**Service Worker** (`sw.js`) handles incoming push events and displays notifications with:
- Title, body, icon
- Click-to-open behavior
- Tag-based deduplication

---

## 12. Configuration Reference

### Environment Variables (`.env`)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_DEBUG` | No | `false` | Show detailed error messages in API responses |
| `DB_HOST` | Yes | `localhost` | MariaDB host |
| `DB_PORT` | No | `3306` | MariaDB port |
| `DB_NAME` | Yes | `smartrecur` | Database name |
| `DB_USER` | Yes | — | Database user |
| `DB_PASS` | Yes | — | Database password |
| `JWT_SECRET` | Yes | — | Secret key for JWT signing (min 32 chars) |
| `JWT_EXPIRY` | No | `86400` | JWT token lifetime in seconds |
| `CORS_ORIGIN` | No | `*` | Allowed origin for CORS |
| `TIMEZONE` | No | `Europe/Amsterdam` | Timezone for date calculations |
| `CRON_SECRET` | Yes | — | Secret token for cron endpoint authentication |

### App Settings (stored in database `settings` table)

| Key | Type | Description |
|-----|------|-------------|
| `branding` | Object | `{ logoUrl, primaryColorHex, themeMode }` |
| `security` | Object | `{ twoFactorEnabled, twoFactorSecret }` |
| `reminders` | Object | `{ days: [14, 7, 1] }` — days before to send reminders |
| `holidays` | Array | `["2025-12-25", ...]` — blocked dates |
| `manualClosures` | Array | One-off closed dates |
| `businessHours` | Object | `{ start: "09:00", end: "17:00", closedDays: [0] }` |
| `templates` | Object | `{ reminder: { subject, body } }` — email templates |
| `preferredMailMethod` | String | `"auto"`, `"smtp"`, or `"office365"` |
| `cronSettings` | Object | `{ enabled, frequencyMinutes, runHour, runMinute }` |
| `vapidKeys` | Object | `{ publicKey, privateKey }` — auto-generated |

---

## 13. Build & Deployment

### Build Process (developer machine only)

```
npm install          → Downloads React, TypeScript, Vite, etc.
npm run build        → Runs Vite compiler:
                        1. TypeScript → JavaScript
                        2. JSX → React.createElement
                        3. Tree-shaking and minification
                        4. CSS extraction
                        5. Content-hashed filenames
                        6. Output to dist/
                     → Post-build script copies:
                        dist/assets/* → assets/
                        dist/index.html → app.html
```

### Production File Flow

```
Browser requests /
  → LiteSpeed serves app.html (DirectoryIndex)
  → app.html loads /assets/index-{hash}.js
  → React app boots, checks JWT in localStorage
  → If no token → show login screen
  → If token → load data from /api/* endpoints
  → LiteSpeed rewrites /api/* → api/index.php
  → PHP router matches URI pattern → calls controller
  → Controller queries MariaDB via PDO → returns JSON
```

### Deployment (Plesk server)

```
git pull origin main   → Gets latest code + pre-built frontend
                       → No npm install, no build step
                       → New DB tables auto-create on first access
                       → .env file persists across pulls (gitignored)
```

---

## 14. Design System

The UI follows **Apple Human Interface Guidelines** principles:

- **Typography:** Inter font family with system fallbacks
- **Colors:** Primary indigo palette with CSS custom properties for theming
- **Spacing:** 4px base grid with generous whitespace
- **Corners:** 8-16px border radius (rounded-xl to rounded-2xl)
- **Shadows:** Layered shadows for depth (card, elevated, glass)
- **Dark mode:** Full support via Tailwind `dark:` prefix, CSS variables
- **Animations:** Spring-based transitions (cubic-bezier 0.34, 1.56, 0.64, 1)
- **Glassmorphism:** Frosted glass effect on header and modals
- **Accessibility:** Focus-visible rings, keyboard navigation, ARIA labels

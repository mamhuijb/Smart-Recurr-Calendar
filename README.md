# SmartRecur Calendar

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

**Stack:** React + TypeScript + Tailwind CSS / PHP 8.1+ / MariaDB / LiteSpeed

---

## Installation on Plesk (LiteSpeed + PHP + MariaDB)

### Prerequisites

- Plesk with LiteSpeed or Apache
- PHP 8.1+ with extensions: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json`
- MariaDB 10.5+ (or MySQL 8+)
- HTTPS enabled (required for push notifications and security headers)

> Node.js is **only needed on your development machine** to build the frontend. The production server runs PHP only.

### Step 1: Build the frontend (on your dev machine)

```bash
npm install
npm run build
```

This creates `app.html` and `assets/` with the compiled React app.

### Step 2: Upload to Plesk

Upload to your domain's document root (e.g. `/httpdocs/`):

```
/httpdocs/
├── .htaccess              ← Routing + security (already configured)
├── .env                   ← Create this (see Step 4)
├── app.html               ← Built frontend entry point
├── assets/                ← Built JS/CSS (hashed filenames)
├── sw.js                  ← Service worker for push notifications
├── manifest.json          ← PWA manifest
├── api/                   ← PHP backend
│   ├── index.php          ← API router
│   ├── config/
│   ├── controllers/
│   ├── helpers/
│   ├── integrations/
│   ├── middleware/
│   └── cron/
└── database/
    └── schema.sql         ← Database schema
```

**Do NOT upload:** `node_modules/`, `*.ts`, `*.tsx`, `package.json`, `tsconfig.json`, `.git/`

### Step 3: Create the database

In Plesk > Databases:
1. Create database `smartrecur`
2. Create a database user with full privileges
3. Import the schema:

```bash
mysql -u smartrecur -p smartrecur < database/schema.sql
```

This creates all tables and a default admin user.

### Step 4: Create .env file

Create `.env` in your document root (next to `.htaccess`):

```env
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartrecur
DB_USER=your_db_user
DB_PASS=your_db_password

JWT_SECRET=paste_a_64_char_random_string_here
JWT_EXPIRY=86400

CORS_ORIGIN=https://your-domain.com
TIMEZONE=Europe/Amsterdam

CRON_SECRET=paste_a_32_char_random_string_here
```

Generate secrets:
```bash
openssl rand -hex 32
```

Set file permissions:
```bash
chmod 600 .env
```

### Step 5: Verify PHP extensions

In Plesk > PHP Settings, enable:
- `pdo_mysql`
- `curl`
- `mbstring`
- `openssl`
- `json`

### Step 6: Test

1. Visit `https://your-domain.com/api/health` — should show `{"status":"ok","database":"connected"}`
2. Visit `https://your-domain.com` — login screen appears
3. Login: **admin** / **change_me_on_first_login**

### Step 7: Change the default password

```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Then in MariaDB:
```sql
UPDATE users SET password_hash = '<output>' WHERE username = 'admin';
```

### Step 8: Set up email reminders (optional)

In Plesk > Scheduled Tasks, add a cron job:
```
0 8 * * * curl -s "https://your-domain.com/api/cron/send-reminders?token=YOUR_CRON_SECRET"
```

This sends reminder emails daily at 8:00 AM.

---

## Troubleshooting

### "Server returned non-JSON response (500)"

1. Set `APP_DEBUG=true` in `.env` temporarily to see the actual error
2. Visit `/api/health` to check database connectivity
3. Common fixes:
   - `.env` missing → create it from the template above
   - Wrong DB credentials → check DB_USER/DB_PASS
   - Tables missing → run `schema.sql`
   - PHP extension missing → enable `pdo_mysql` in Plesk PHP Settings
4. Set `APP_DEBUG=false` after fixing

### Updating an existing database

If you already have the database and pulled new code with schema changes:

```sql
-- Add reminder_log table
CREATE TABLE IF NOT EXISTS reminder_log (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    target_date DATE NOT NULL,
    reminder_day INT NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    method VARCHAR(20) DEFAULT NULL,
    error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_date_day (event_id, target_date, reminder_day),
    INDEX idx_event_id (event_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
```

---

## Integrations

All integrations are configured in **Admin > Settings** within the app.

| Integration | Auth | Features |
|-------------|------|----------|
| Office 365 | OAuth2 | Calendar sync, email sending |
| Syncro MSP | API Key | Customer import, ticket creation |
| Invoice Ninja | API Key | Customer sync |
| Zoho | API Key | CRM integration |
| SMTP | Credentials | Direct email sending |

### Office 365 Setup

1. Register app at [Azure Portal](https://portal.azure.com) > App Registrations
2. Add redirect URI: `https://your-domain.com/api/integrations/office365/callback`
3. Add permissions: `User.Read`, `Calendars.ReadWrite`, `Mail.Send`
4. Enter Client ID + Tenant ID in Admin panel
5. Click "Connect" to start OAuth flow

---

## Architecture

```
Browser → app.html (React SPA)
              ↓ fetch /api/*
         .htaccess rewrites to api/index.php
              ↓ JWT auth
         PHP Controllers
              ↓ PDO prepared statements
         MariaDB
              ↓
         External APIs (Office 365, Syncro, Invoice Ninja, Zoho)
```

- JWT authentication with bcrypt passwords and optional TOTP 2FA
- All API keys stored server-side (never sent to browser)
- Rate limiting on login (5 attempts / 5 minutes)
- Global error handler ensures JSON responses (never raw PHP errors)
- PUT/DELETE sent as POST with X-HTTP-Method-Override for WAF compatibility
- Web Push notifications via VAPID (auto-generated keys)

---

## Default Login

| Username | Password |
|----------|----------|
| `admin` | `change_me_on_first_login` |

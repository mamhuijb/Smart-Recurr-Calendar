# SmartRecur Calendar v2.0.1

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

**Stack:** React 18 + TypeScript + Tailwind CSS (frontend) / PHP + MariaDB (backend)

---

## Quick Start (Local Development)

### Prerequisites

- **Node.js 18+** (for Vite dev server)
- **PHP 8.1+** with extensions: `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`
- **MariaDB 10.5+** (or MySQL 8+)

### 1. Clone & install frontend dependencies

```bash
git clone <repo-url> && cd Smart-Recurr-Calendar
npm install
```

### 2. Create the database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `smartrecur` database with all tables and a default admin user.

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```
APP_DEBUG=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartrecur
DB_USER=smartrecur
DB_PASS=your_db_password

JWT_SECRET=generate_a_random_32_char_string_here
CORS_ORIGIN=*
TIMEZONE=Europe/Amsterdam
```

Generate a JWT secret:

```bash
openssl rand -hex 32
```

### 4. Start the PHP backend

```bash
php -S localhost:8000 -t . api/index.php
```

### 5. Start the Vite dev server (separate terminal)

```bash
npm run dev
```

The app is now running at **http://localhost:5173**.
The Vite dev server proxies `/api/*` requests to the PHP backend on port 8000.

### 6. Verify the API is working

Open in your browser: **http://localhost:5173/api/health**

You should see JSON like:
```json
{
  "status": "ok",
  "version": "2.0.1",
  "php": "8.x.x",
  "env": "loaded",
  "database": "connected",
  "tables": ["users", "customers", "events", "services", ...]
}
```

**If you see errors here**, fix them before trying to login:
- `"env": "missing"` → Your `.env` file is missing or in the wrong location
- `"database": "error"` → Check DB credentials in `.env`
- `"tables": []` → Run `mysql -u root -p smartrecur < database/schema.sql`

### 7. Login

- **Username:** `admin`
- **Password:** `change_me_on_first_login`

**Change this password immediately** in the database:

```bash
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Then update the user in MariaDB:

```sql
UPDATE users SET password_hash = '<output_from_above>' WHERE username = 'admin';
```

---

## Troubleshooting

### "Server returned non-JSON response (500)"

This means PHP is crashing before it can return JSON. To diagnose:

1. **Enable debug mode:** Set `APP_DEBUG=true` in `.env` — error details will appear in API responses
2. **Check the health endpoint:** Visit `/api/health` to see DB connection status
3. **Check PHP error log:** Look in your server's PHP error log for the actual error
4. **Common causes:**
   - `.env` file missing or unreadable
   - Wrong database credentials
   - Database or tables don't exist (run `schema.sql`)
   - Missing PHP extensions (`pdo_mysql` is the most common)

### Database already exists (schema update)

If you already have the database and need to apply schema updates:

```sql
-- Add FK constraints (skip if tables are fresh from schema.sql)
-- NOTE: This will fail if there are orphaned records. Clean them first:
DELETE FROM events WHERE customer_id NOT IN (SELECT id FROM customers);
DELETE FROM events WHERE service_id NOT IN (SELECT id FROM services);

-- Then add constraints:
ALTER TABLE events
  ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  ADD FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  ADD FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL;

-- Add reminder_log table if it doesn't exist:
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

-- Add composite index for performance:
CREATE INDEX idx_customer_created ON events (customer_id, created_at);
```

### Plesk / LiteSpeed specific

- PUT/DELETE methods are automatically sent as POST with `X-HTTP-Method-Override` header (WAF compatible)
- Check that `.htaccess` rewrite rules are active
- Ensure PHP version is 8.1+ in Plesk PHP Settings
- Check that `pdo_mysql` extension is enabled

---

## Production Deployment (Plesk)

### 1. Build the frontend

```bash
npm run build
```

### 2. Upload to Plesk

Upload the entire project to your domain's document root (`/httpdocs/`).

**Required files/folders:**
```
/httpdocs/
├── .htaccess          (SPA routing + security headers)
├── app.html           (production entry point - created by build)
├── assets/            (built JS/CSS - created by build)
├── sw.js              (service worker for push notifications)
├── manifest.json      (PWA manifest)
├── api/               (PHP backend)
│   ├── index.php
│   ├── controllers/
│   ├── helpers/
│   ├── integrations/
│   ├── middleware/
│   ├── config/
│   └── cron/
├── database/
│   └── schema.sql
└── .env               (create from .env.example — chmod 600!)
```

**Do NOT upload:** `node_modules/`, `*.ts`, `*.tsx`, `package.json`, `tsconfig.json`, `.git/`

### 3. Create the database in Plesk

1. Go to **Databases** in Plesk
2. Create a new database (e.g., `smartrecur`)
3. Create a database user with full privileges
4. Import the schema:
```bash
mysql -u smartrecur -p smartrecur < database/schema.sql
```

### 4. Configure .env

Create `.env` in the document root (next to `.htaccess`):

```
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartrecur
DB_USER=your_plesk_db_user
DB_PASS=your_plesk_db_password

JWT_SECRET=<random 64 char string>
JWT_EXPIRY=86400

CORS_ORIGIN=https://your-domain.com
TIMEZONE=Europe/Amsterdam

CRON_SECRET=<random 32 char string>
```

Generate secrets:
```bash
openssl rand -hex 32
```

### 5. Verify PHP extensions

In Plesk > PHP Settings, make sure these extensions are enabled:
- `pdo_mysql`
- `curl`
- `mbstring`
- `json`
- `openssl`

### 6. Set permissions

```bash
chmod 600 .env
chmod -R 755 api/
```

### 7. Set up cron for email reminders

In Plesk > Scheduled Tasks, add:
```
0 8 * * * curl -s "https://your-domain.com/api/cron/send-reminders?token=YOUR_CRON_SECRET"
```

This sends reminder emails daily at 8:00 AM.

### 8. Test

1. Visit `https://your-domain.com/api/health` — verify all checks pass
2. Visit `https://your-domain.com` — login screen should appear
3. Login with `admin` / `change_me_on_first_login`

---

## Integrations Setup

### Office 365

1. Register an app at [Azure Portal](https://portal.azure.com) > App Registrations
2. Set redirect URI to: `https://your-domain.com/api/integrations/office365/callback`
3. Add API permissions: `User.Read`, `Calendars.ReadWrite`, `Mail.Send`
4. Create a Client Secret
5. Copy Client ID, Tenant ID into Admin > Office 365
6. Add Client Secret via the admin panel
7. Click "Connect" — an OAuth popup will open

### Syncro MSP

1. Get your API key from Syncro MSP Admin > API Tokens
2. Enter API Key and Subdomain in Admin > Syncro MSP
3. Click "Test" to verify, then "Import Customers" to sync

### Invoice Ninja

1. Get your API key from Invoice Ninja > Settings > Account Management
2. Enter the endpoint URL and API key in Admin > InvoiceNinja
3. Click "Test Connection", then "Sync Customers"

### Zoho CRM/Books

1. Generate an OAuth access token from Zoho API Console
2. Enter the token and endpoint in Admin > Zoho
3. Click "Test Connection"

---

## Default Login

| Username | Password |
|----------|----------|
| `admin` | `change_me_on_first_login` |

**Change this immediately after first login.**

---

## Architecture

```
Frontend (React SPA)
    ↓ JWT Bearer token
PHP API (api/index.php router)
    ↓ PDO prepared statements
MariaDB
    ↓
Integration proxies → Office 365, Syncro, Invoice Ninja, Zoho
```

- All API keys stored server-side in `integration_configs` table (never sent to browser)
- JWT authentication with bcrypt password hashing
- Server-side TOTP 2FA verification
- CORS restricted to configured origin
- Rate limiting on login (5 attempts / 5 min)
- SSRF protection on integration endpoints
- Global error handler ensures all errors return JSON (never raw HTML/PHP errors)
- Push notifications via Web Push API (VAPID)

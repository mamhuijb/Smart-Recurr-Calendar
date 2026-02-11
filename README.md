# SmartRecur Calendar v2.0

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

**Stack:** React 18 + TypeScript + Tailwind CSS (frontend) / PHP + MariaDB (backend)

---

## Quick Start (Local Development)

### Prerequisites

- **Node.js 18+** (for Vite dev server)
- **PHP 8.1+** with extensions: `pdo_mysql`, `curl`, `mbstring`, `json`
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
DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartrecur
DB_USER=smartrecur
DB_PASS=your_db_password

JWT_SECRET=generate_a_random_32_char_string_here
```

Generate a JWT secret:

```bash
openssl rand -hex 32
```

### 4. Start the PHP backend

```bash
php -S localhost:8000 -t . api/index.php
```

Or use the npm script:

```bash
npm run php
```

### 5. Start the Vite dev server (separate terminal)

```bash
npm run dev
```

The app is now running at **http://localhost:5173**.
The Vite dev server proxies `/api/*` requests to the PHP backend on port 8000.

### 6. Login

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

## Production Deployment (Plesk)

### 1. Build the frontend

```bash
npm run build
```

This outputs optimized files to `dist/`.

### 2. Upload to Plesk

Upload the entire project to your domain's document root. The directory structure should be:

```
/httpdocs/
├── .htaccess          (SPA routing + security headers)
├── dist/              (built frontend)
│   ├── index.html
│   └── assets/
├── api/               (PHP backend)
│   ├── index.php
│   ├── .htaccess
│   ├── controllers/
│   ├── helpers/
│   ├── integrations/
│   ├── middleware/
│   └── config/
├── database/
│   └── schema.sql
├── .env               (create from .env.example)
└── .env.example
```

**Do NOT upload:** `node_modules/`, `src/`, `*.ts`, `*.tsx`, `package.json`, `.git/`

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
DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartrecur
DB_USER=your_plesk_db_user
DB_PASS=your_plesk_db_password

JWT_SECRET=<random 64 char string>
JWT_EXPIRY=86400

CORS_ORIGIN=https://your-domain.com

O365_CLIENT_ID=
O365_CLIENT_SECRET=
O365_TENANT_ID=common
O365_REDIRECT_URI=https://your-domain.com/api/integrations/office365/callback
```

### 5. Verify PHP extensions

In Plesk > PHP Settings, make sure these extensions are enabled:
- `pdo_mysql`
- `curl`
- `mbstring`
- `json`

### 6. Set permissions

```bash
chmod 600 .env
chmod -R 755 api/
```

### 7. Test

Visit `https://your-domain.com` — you should see the login screen.

---

## Integrations Setup

### Office 365

1. Register an app at [Azure Portal](https://portal.azure.com) > App Registrations
2. Set redirect URI to: `https://your-domain.com/api/integrations/office365/callback`
3. Add permissions: `User.Read`, `Calendars.ReadWrite`
4. Copy Client ID and Tenant ID into Admin > Office 365
5. Add Client Secret to `.env` as `O365_CLIENT_SECRET`
6. Click "Connect" in the admin panel

### Syncro MSP

1. Get your API key from Syncro MSP Admin > API Tokens
2. Enter API Key and Subdomain in Admin > Syncro MSP
3. Click "Test" to verify, then "Import Customers" to sync

### Invoice Ninja

1. Get your API key from Invoice Ninja > Settings > Account Management
2. Enter the endpoint URL and API key in Admin > InvoiceNinja
3. Click "Test Connection"

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

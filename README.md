# SmartRecur Calendar

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

**Stack:** React 18 + TypeScript + Tailwind CSS / PHP 8.1+ / MariaDB / LiteSpeed (Plesk)

---

## Deploying on Plesk

The frontend is pre-built and committed to git. No Node.js needed on the server.

### Step 1: Pull the code

```bash
cd /httpdocs
git pull origin main
```

Everything needed is in the repo: `app.html`, `assets/`, `api/`, `.htaccess`, etc.

### Step 2: Create the database (first time only)

In Plesk > Databases:
1. Create database `smartrecur`
2. Create a database user with full privileges
3. Import the schema:

```bash
mysql -u smartrecur -p smartrecur < database/schema.sql
```

This creates all tables (users, events, customers, services, technicians, settings, email_logs, reminder_log, cron_logs, integration_configs, push_subscriptions) and a default admin user.

### Step 3: Create `.env` file (first time only)

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

### Step 4: Verify PHP extensions

In Plesk > PHP Settings, enable: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json`

### Step 5: Test

Visit `https://your-domain.com` and login with **admin** / **change_me_on_first_login**

If something is wrong, set `APP_DEBUG=true` in `.env` and check `/api/health`.

### Step 6: Set up the cron job

In Plesk > Scheduled Tasks, add a cron task with desired frequency (e.g. every hour):

```
0 * * * * curl -s "https://your-domain.com/api/cron/send-reminders?token=YOUR_CRON_SECRET"
```

You can also manage frequency and monitor execution from **Admin > Scheduled Tasks** in the app.

### Step 7: Change the default password

```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);"
```

```sql
UPDATE users SET password_hash = '<output>' WHERE username = 'admin';
```

---

## Updating

```bash
cd /httpdocs
git pull origin main
```

That's it. The built frontend is included in the repo. No build step needed. New database tables are created automatically when first accessed.

---

## Admin Panel

Access via the gear icon in the top-right corner. Sections:

| Section | Purpose |
|---------|---------|
| **Reporting** | Yearly overview, appointment stats by location type |
| **Branding & UI** | Logo, primary color, light/dark theme toggle |
| **Security (2FA)** | Enable/disable TOTP two-factor authentication |
| **Customers** | Add, edit, delete customers with company/contact info |
| **Services** | Define recurring or one-time service types with colors |
| **Technicians** | Manage technician profiles with skills and colors |
| **Business Hours** | Set working hours, closed days, holidays |
| **Office 365** | OAuth2 connect, calendar sync, email via Graph API |
| **SMTP Email** | Direct SMTP configuration for email delivery |
| **Syncro MSP** | API key connect, customer import, ticket creation |
| **InvoiceNinja** | API key connect, customer sync |
| **Zoho** | CRM integration |
| **Email & Reminders** | Configure reminder days, email templates, test email |
| **Push Notifications** | Web push setup, device registration, timing config |
| **Email Logs** | View sent/failed email history |
| **Scheduled Tasks** | Cron job status, manual trigger, execution history |
| **Backup & Restore** | Export/import full database as JSON |

---

## Integrations

All configured in the Admin Panel:

| Integration | Auth Method | Features |
|-------------|-------------|----------|
| Office 365 | OAuth2 | Calendar sync, email sending via Mail.Send API |
| Syncro MSP | API Key | Customer import, ticket creation |
| Invoice Ninja | API Key | Customer sync from invoicing platform |
| Zoho | API Key | CRM contact integration |
| SMTP | Credentials | Direct email sending (alternative to Office 365) |

---

## Development (modifying source code)

Only needed if you want to change the React frontend. Requires Node.js 18+ on your dev machine.

```bash
npm install
npm run dev          # Vite dev server on :5173
php -S localhost:8000 -t . api/index.php   # PHP backend
```

After making changes:

```bash
npm run build        # Rebuilds app.html + assets/
git add app.html assets/
git commit -m "rebuild frontend"
git push
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| "Server returned non-JSON response (500)" | Set `APP_DEBUG=true` in `.env`, check `/api/health` |
| "Session expired" on login | This was a known bug (fixed). Pull latest code. |
| Push notifications don't work | Requires HTTPS. Check browser permissions. |
| Cron not running | Check Plesk > Scheduled Tasks. Verify `CRON_SECRET` matches `.env`. Monitor in Admin > Scheduled Tasks. |
| Email not sending | Test from Admin > Email & Reminders. Check SMTP or Office 365 config. |

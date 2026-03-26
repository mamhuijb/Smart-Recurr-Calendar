# SmartRecur Calendar

Recurring appointment scheduler for MSPs with Office 365, Syncro MSP, Invoice Ninja, and Zoho integrations.

**Stack:** React + Tailwind CSS / PHP 8.1+ / MariaDB / LiteSpeed

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

Generate secrets: `openssl rand -hex 32`

Set permissions: `chmod 600 .env`

### Step 4: Verify PHP extensions

In Plesk > PHP Settings, enable: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json`

### Step 5: Test

Visit `https://your-domain.com` and login with **admin** / **change_me_on_first_login**

If something is wrong, set `APP_DEBUG=true` in `.env` and check `/api/health`.

---

## Updating

After code changes are merged:

```bash
cd /httpdocs
git pull origin main
```

That's it. The built frontend is included in the repo. No build step needed.

If the update includes database changes, check the release notes for migration SQL.

---

## Changing the default password

```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);"
```

```sql
UPDATE users SET password_hash = '<output>' WHERE username = 'admin';
```

---

## Email reminders (cron)

In Plesk > Scheduled Tasks:

```
0 8 * * * curl -s "https://your-domain.com/api/cron/send-reminders?token=YOUR_CRON_SECRET"
```

---

## Integrations

Configured in **Admin > Settings** within the app.

| Integration | Auth | Features |
|-------------|------|----------|
| Office 365 | OAuth2 | Calendar sync, email sending |
| Syncro MSP | API Key | Customer import, ticket creation |
| Invoice Ninja | API Key | Customer sync |
| Zoho | API Key | CRM integration |
| SMTP | Credentials | Direct email sending |

---

## Development (modifying source code)

Only needed if you want to change the React frontend.

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

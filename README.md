# SmartRecur Calendar v1.0

SmartRecur is an AI-powered appointment scheduler for MSPs, featuring natural language parsing, SyncroMSP integration, and Office 365 sync.

## Security Assessment (v1.0)

**Critical:** This application currently runs in "Client-Side Mode". For a production environment on a Plesk server, you must implement the backend proxy pattern.

1.  **API Keys:** Do not store Syncro or Office 365 Secret keys in the `src/` code or LocalStorage.
2.  **Auth:** Implement server-side session management via Laravel Sanctum or Passport.

## Installation Guide (Plesk + Laravel)

### Prerequisites
*   Plesk Obsidian (or newer)
*   Node.js 18+
*   PHP 8.1+
*   MariaDB 10.5+

### Step 1: Backend Setup (Laravel)
1.  Create a new subdomain in Plesk (e.g., `app.yourdomain.com`).
2.  Install Laravel in the document root.
3.  Set up the database in Plesk and update `.env`.
4.  **Security:** Add your Syncro API Key to `.env`:
    ```
    SYNCRO_API_KEY=your_key_here
    SYNCRO_SUBDOMAIN=your_subdomain
    GEMINI_API_KEY=your_google_key
    ```
5.  Create a Controller (`php artisan make:controller ApiProxyController`) to handle requests. Ensure the React frontend calls this controller instead of calling Syncro directly.

### Step 2: Frontend Setup (React)
1.  On your local machine, run `npm install` and `npm run build`.
2.  The build output will be in the `dist/` or `build/` folder.
3.  Upload the contents of this folder to the `public/` directory of your Laravel application on Plesk.
4.  Ensure your Laravel `web.php` routes catch all requests and return the `index.html` for SPA routing:
    ```php
    Route::get('/{any}', function () {
        return view('app'); // assumes you renamed index.html to resources/views/app.blade.php
    })->where('any', '.*');
    ```

### Step 3: Database & Production
1.  Run `php artisan migrate` on the server.
2.  Set up a Cron job in Plesk for `php artisan schedule:run` to handle background email reminders.

## Customization
Log in as `admin` to access the Admin Panel.
*   **Branding:** Change the Logo and Primary Color (Hex).
*   **Theme:** Toggle between Light and Dark mode.
*   **Business Hours:** Configure opening times and holidays.

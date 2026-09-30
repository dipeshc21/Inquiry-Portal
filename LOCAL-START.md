# Run on this laptop

The portal is installed in this folder. No XAMPP control-panel services are needed.

1. Double-click **Start-Portal.cmd**.
2. Open **http://localhost:5173/login**.
3. Sign in with **admin@example.com** and **password**.

Public inquiry form: http://localhost:5173/inquiry

Double-click **Stop-Portal.cmd** when finished. Data is preserved between starts.

**Setup-Portal.cmd** reinstalls locked dependencies and rebuilds the frontend if needed. It requires internet access. After editing frontend source, run Setup again to rebuild the served frontend.

## Local services

- Frontend: loopback port 5173, built React app.
- Laravel API: loopback port 8000.
- MySQL 8.4: loopback port 3307, database `inquiry_portal`.
- Queue worker and scheduler: hidden background processes.
- PHP: your existing `C:\xampp\php\php.exe`.
- Node and MySQL: isolated under `.local`, without changing your system installation.

Runtime logs are in `.local/logs`. Email uses Laravel's log driver; it does not send real email.

Do not delete `.local/mysql-data` or `backend/.env`: they contain your local database and configuration. Database credentials are saved in `backend/.env` and `.local/admin.cnf`; do not share those files or the entire installed folder.

Laravel 11 was retained at your request for **local testing only**, despite Composer security advisories. Do not expose these services to a network or deploy this setup publicly.

The original ZIP contains the original source. This installed copy includes local launchers, dependency lock files, and fixes discovered during verification.

# Reports catalogue prototype

This repository contains a simple reporting catalogue page together with a lightweight PHP API and MySQL seed data. The frontend is intentionally framework-free and compiled with TypeScript only so that the page can be embedded into an existing application later on.

## Project structure

```
backend/           PHP API endpoint that returns report definitions and metrics
frontend/          TypeScript + CSS assets for the reports page
  ├─ public/       Static HTML and styling served after the build
  └─ src/          TypeScript source code
database/          MySQL schema and sample data referenced by the API
```

## Requirements

* PHP 8.2+
* MySQL 8+
* Node.js 18+ (for building the TypeScript frontend)

## Backend

The backend exposes a single endpoint: `backend/api/reports.php`. It aggregates high-level metrics for disbursements, repayments, and scheduled payments using the provided sample database. If a database connection cannot be established the endpoint falls back to the bundled seed data so that the frontend still renders meaningful demo numbers. Configure database credentials via the following environment variables (defaults in parentheses):

* `DB_HOST` (`127.0.0.1`)
* `DB_PORT` (`3306`)
* `DB_NAME` (`reports_sample`)
* `DB_USER` (`root`)
* `DB_PASS` (empty string)

To seed a database locally, run the MySQL CLI against the bundled schema. On macOS/Linux you can pipe the file directly:

```bash
mysql < database/schema.sql
```

On Windows (without WSL) the equivalent command uses `mysql.exe`:

```powershell
& "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe" < database\schema.sql
```

Serve the API using your preferred PHP runtime (for example, `php -S 0.0.0.0:8000 -t .`).

## Frontend

The frontend lives under `frontend/` and is compiled with the TypeScript compiler. After installation the build output appears in `frontend/dist/` alongside the copied static assets.

```bash
cd frontend
npm install
npm run build
```

The build script is implemented with Node.js so it runs the same way on Windows, macOS, and Linux. It cleans the `dist` folder,
compiles TypeScript, and copies the static assets without relying on shell utilities that might not be available on Windows.

Serve the resulting `frontend/dist/` directory with any static web server (for example, `php -S 0.0.0.0:8080 -t dist`). Ensure the PHP backend is reachable at `/backend/api/reports.php`; adjust the deployment path if your server structure differs.

## Integrating with an existing application

* The frontend expects JSON responses in the structure already implemented by `backend/api/reports.php`.
* Additional reports can be added inside `buildReportsPayload` in `backend/api/reports.php`; the frontend automatically renders any card following the same schema.
* Update CSS inside `frontend/public/styles.css` to match the host application’s design system.

## Tests

This project does not ship with automated tests yet. Build the frontend to verify that TypeScript compiles successfully.

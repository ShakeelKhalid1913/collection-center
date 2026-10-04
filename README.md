# Lab Dash Pro — MVP (PHP + MariaDB + Tailwind)

Lab management MVP with **three main staff user types** (portal-locked):

1. **Diagnostic Center** — X-Ray, CT Scan, Ultrasound, ECG  
2. **Laboratory** — samples, results, verification, pathology reports  
3. **Collection Center** — patient registration, lab entries, receipts  

Admin portal remains for system setup (users, catalog, settings).

**Each account can only access its own portal.** Cross-portal URLs return HTTP 403.

Data lives in **MariaDB / MySQL** (`app/database/schema.sql`). Repositories under `app/repositories/`.

## Seeded logins (password `1913`)

| Email | Portal |
|-------|--------|
| `staff@citylab.pk` | Collection Center |
| `lab@citylab.pk` | Laboratory |
| `imaging@citylab.pk` | Diagnostic Center |
| `admin@citylab.pk` | Admin |

## Run locally

Requires PHP 8.1+ and MariaDB/MySQL.

1. Start MariaDB and set env vars (or use defaults in `app/config/database.php`: `root` / `1913` / `collection_center_db`).
2. Bootstrap schema + seed:

```bash
cd D:\working\collection-center
php app/database/setup.php
# or open http://localhost:8080/database/setup.php after starting the server
```

3. Start the app:

```bash
php -S localhost:8080 api/index.php
```

Open [http://localhost:8080/](http://localhost:8080/) → **Login**.

> App PHP lives in `app/`. `api/index.php` is the front controller.

## Environment variables

| Variable | Purpose | Example |
|----------|---------|---------|
| `DB_HOST` | Database host | `127.0.0.1` or Render internal host |
| `DB_PORT` | Port | `3306` |
| `DB_NAME` | Database name | `collection_center_db` |
| `DB_USER` | Username | `root` |
| `DB_PASS` | Password | *(your secret)* |

## Deploy on Render

Render fits this stack better than Vercel (persistent PHP + MySQL + stable sessions).

### 1. Create a MySQL database

In Render → **New → MySQL** (or use any managed MariaDB/MySQL). Note host, port, database, user, password.

### 2. Create a Web Service

1. Push this repo to GitHub.
2. Render → **New → Web Service** → connect the repo.
3. Settings:

| Setting | Value |
|---------|--------|
| Runtime | **Docker** *or* Native PHP if available; easiest is a PHP Dockerfile / community PHP blueprint |
| Build command | *(none required for plain PHP)* |
| Start command | `php -S 0.0.0.0:$PORT api/index.php` |

> If Render’s native PHP runtime is not available on your plan, use a small Dockerfile that installs PHP + extensions (`pdo_mysql`) and runs the same start command.

### 3. Set environment variables on the Web Service

```
DB_HOST=<your-mysql-host>
DB_PORT=3306
DB_NAME=<database-name>
DB_USER=<user>
DB_PASS=<password>
```

### 4. Run setup once

After the first deploy, open:

`https://<your-service>.onrender.com/database/setup.php`

This creates tables and seeds the four demo users. Then sign in at `/login.php`.

### Optional Dockerfile (Render)

```dockerfile
FROM php:8.3-cli
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /app
COPY . .
EXPOSE 10000
CMD php -S 0.0.0.0:${PORT:-10000} api/index.php
```

## Core flow

`Patient → Tests → Receipt → Sample → Result → Verification → Report → Print / WhatsApp`

Report preview: **Laboratory → Reports → Generate Report → Preview** (print CSS in `assets/css/app.css`).

WhatsApp delivery uses `wa.me` links (Business API can be added later).

## Auth lock

- Login uses the user’s **stored portal** only (no portal picker on sign-in).
- Every `render_page(..., $portal, ...)` calls `require_auth($portal)`.
- Mismatch → 403 with link back to the user’s own dashboard.

## What persists to the DB

- Signup / login (bcrypt)
- Patient register + quick-register
- Lab entries (tests, billing, sample stub, pending result stub)
- Sample status updates
- Results entry + verification
- Imaging studies + findings
- Settings / report template
- Admin test & package create
- Dashboard / nav counts from SQL aggregates

## Still optional / later

- Dompdf server-side PDF (browser print works now)
- WhatsApp Business API
- Photo upload storage

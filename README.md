# LabFlow LMS — MVP (PHP + Tailwind)

Responsive front-end MVP for a focused Lab Management System with **four portals**:

1. **Collection Center** — patient registration, lab entries, receipts  
2. **Main Lab** — samples, results, verification, reports  
3. **X-Ray / CT Scan** — imaging workflow (shared patients)  
4. **Admin** — org/branches, users, catalog (UI shell)

Data is **mocked** in `data/mock.php`. MySQL schema sketch: `database/schema.mysql.sql`.

## Run locally

Requires PHP 8.1+.

```bash
cd D:\working\collection-center
php -S localhost:8080 -t .
```

Open [http://localhost:8080/](http://localhost:8080/) for the landing page, then **Login** → any email/password.

## Core flow (UI)

`Patient → Tests → Receipt → Sample → Result → Verification → Report → Print / WhatsApp`

Report preview: **Main Lab → Reports → Generate Report → Preview** (high-contrast print CSS in `assets/css/app.css`).

## Next backend steps

- PDO MySQL connection + repositories per entity  
- Session auth with real users/roles  
- Dompdf or similar for server-side PDF  
- WhatsApp Business API provider configuration  

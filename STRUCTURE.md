# Proposed Professional & Clean Architecture

A clean, industry-standard **MVC (Model-View-Controller) with Service Layer** structure. It separates web requests, business logic, templates, and database queries into distinct, easy-to-find locations.

---

## 1. Target Directory Layout

```
collection-center/
│
├── public/                         # Web server document root (Only public files here)
│   ├── index.php                   # Single entry point for ALL requests
│   └── assets/                     # Public assets
│       ├── css/
│       ├── js/
│       └── images/
│
├── config/                         # App configuration
│   ├── app.php                     # App settings, timezone, environment
│   └── database.php                # Database credentials and PDO options
│
├── src/                            # PHP Application Code (PSR-4 Autoloaded)
│   │
│   ├── Controllers/                # Handles HTTP requests & sends responses
│   │   ├── AuthController.php      # Login, Logout, Register
│   │   ├── Admin/                  # Admin portal controllers
│   │   │   ├── DashboardController.php
│   │   │   └── OrganizationController.php
│   │   ├── MainLab/                # Main Lab portal controllers
│   │   │   ├── PatientController.php
│   │   │   ├── OrderController.php
│   │   │   ├── ResultController.php
│   │   │   └── ReportController.php
│   │   ├── CollectionCenter/       # Collection center portal controllers
│   │   └── Imaging/                # Imaging portal controllers
│   │
│   ├── Services/                   # Pure Business Logic (No HTML, no direct HTTP)
│   │   ├── AuthService.php         # Password checks, permissions, session tokens
│   │   ├── ReportService.php       # Report generation, PDF/print rendering
│   │   └── BarcodeService.php      # Barcode generation
│   │
│   ├── Repositories/               # Database Queries (Only SQL & PDO queries)
│   │   ├── PatientRepository.php
│   │   ├── OrderRepository.php
│   │   ├── ResultRepository.php
│   │   └── UserRepository.php
│   │
│   ├── Models/                     # Data objects / Entities
│   │   ├── Patient.php
│   │   ├── Test.php
│   │   └── User.php
│   │
│   └── Middleware/                 # Guards & filters
│       ├── AuthMiddleware.php      # Redirects if not logged in
│       └── RoleMiddleware.php      # Verifies role (admin vs main-lab vs CC)
│
├── views/                          # HTML Templates & UI (No SQL, only presentation)
│   ├── layouts/                    # Common wraps
│   │   ├── main.php                # Standard shell (head, navbar, sidebar, footer)
│   │   └── auth.php                # Minimal layout for login/register
│   ├── partials/                   # Reusable snippets (alerts, modals, buttons)
│   ├── auth/                       # Login & signup pages
│   │   ├── login.php
│   │   └── signup.php
│   └── portals/                    # Portal views
│       ├── admin/
│       ├── main-lab/
│       │   ├── patients/           # list.php, create.php, edit.php
│       │   ├── results/            # entry.php, verification.php
│       │   └── reports/            # preview.php, print.php
│       ├── collection-center/
│       └── imaging/
│
├── routes/                         # Route Definitions
│   └── web.php                     # Maps URLs -> Controllers (e.g. GET /patients -> PatientController@index)
│
├── database/                       # Migrations & Seeding
│   ├── migrations/                 # Schema version files
│   └── seeds/                      # Default catalog & test data
│
├── storage/                        # Generated files & cache (Private, never public)
│   ├── logs/
│   ├── uploads/                    # Patient documents, staff photos
│   └── reports/                    # Generated PDFs
│
├── .env                            # Environment variables (DB password, secrets)
├── .env.example
├── composer.json                   # Autoloader configuration
└── README.md
```

---

## 2. Why This Is Simple & Understandable

| Problem in Current Code | How This Structure Solves It |
|---|---|
| **Everything is mixed** (HTML + SQL + Logic in one file) | **Separated:** SQL in `Repositories/`, Logic in `Services/`, HTML in `views/`. |
| **Don't know where a page lives** | Follow `routes/web.php`: URL points directly to `Controller@method`, which loads the view in `views/`. |
| **Security risk** (PHP files directly exposed to web) | Only `public/index.php` is exposed. Database and config files are hidden outside webroot. |
| **Repeated code** (`require_once` everywhere) | Composer autoloader loads any class automatically by namespace. |

---

## 3. Where Things Move (Old -> New)

| Current Location | New Location |
|---|---|
| `app/portals/main-lab/patients/records.php` | Controller: `src/Controllers/MainLab/PatientController.php`<br>View: `views/portals/main-lab/patients/list.php` |
| `app/repositories/*.php` | `src/Repositories/*.php` |
| `app/includes/bootstrap.php` | `src/Middleware/` + `public/index.php` |
| `app/includes/layout.php` | `views/layouts/main.php` |
| `app/includes/documents.php` | `src/Services/ReportService.php` |
| `app/config/database.php` | `config/database.php` |
| `assets/` | `public/assets/` |

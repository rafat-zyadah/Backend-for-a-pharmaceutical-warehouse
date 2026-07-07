# Pharmacy Warehouse — Backend API

Laravel 12 API for the pharmacy warehouse system (Supervisor / Invoicer / Rep).

**Documentation (repo root):** `../docs/USE_CASES_FINAL.md` · `../docs/STATE_MACHINES.md` · `../docs/Pharmacy_Warehouse_ERD.pdf`

## Stack

- PHP 8.2+
- Laravel 12
- Sanctum (API tokens)
- Spatie Permission (roles & permissions)
- PostgreSQL 16 (Docker) or SQLite (local quick start)
- Redis (optional, Docker)

## Architecture: initialization

| Layer | Responsibility |
|-------|----------------|
| `php artisan migrate` | **Schema only** — tables, indexes, constraints |
| `php artisan system:install` | **Mandatory system data** — roles, permissions, settings, default supervisor |
| `php artisan db:seed` | **Optional demo data** — sample invoicer/rep for local dev |
| HTTP runtime | **Business logic only** — no install/bootstrap checks |

## Project layout

```
backend/
├── app/
│   ├── Console/Commands/       # system:install
│   ├── Enums/
│   ├── Http/Controllers/Api/V1/
│   ├── Http/Middleware/
│   ├── Models/
│   ├── Support/Audit/
│   └── Support/Install/        # Install steps (idempotent)
├── config/install.php          # Permissions, roles, default settings catalog
├── database/migrations/
├── docker-compose.yml
└── routes/api.php
```

## Quick start (SQLite)

```bash
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan system:install
php artisan serve
```

API base: `http://localhost:8000/api/v1`

## Docker (PostgreSQL + Redis)

```bash
docker compose up -d
```

Set in `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pharmacy_warehouse
DB_USERNAME=pharmacy
DB_PASSWORD=pharmacy
```

Then:

```bash
php artisan migrate
php artisan system:install
```

## `system:install` (mandatory, idempotent)

Creates only if missing:

- Spatie **permissions** and **roles** (`supervisor`, `invoicer`, `rep`)
- **Application settings** (`low_stock_threshold`, `app_locale`, `currency_code`)
- **Default supervisor** user (UC-02)

Safe to run multiple times — no duplicate records.

### Default supervisor credentials (`.env`)

| Variable | Default |
|----------|---------|
| `PHARMACY_DEFAULT_SUPERVISOR_USERNAME` | `supervisor` |
| `PHARMACY_DEFAULT_SUPERVISOR_PASSWORD` | `password` |
| `PHARMACY_DEFAULT_SUPERVISOR_NAME` | Supervisor Admin |
| `PHARMACY_DEFAULT_SUPERVISOR_PHONE` | 0500000001 |

Login platform: `X-Client-Platform: web`

## Optional demo seed

```bash
php artisan db:seed
```

Creates demo `invoicer` and `rep` accounts only (not for production).

## API examples

**Health**

```http
GET /api/v1/health
```

**Login**

```http
POST /api/v1/auth/login
X-Client-Platform: web
Content-Type: application/json

{"login":"supervisor","password":"password"}
```

**Password recovery**

```http
POST /api/v1/auth/forgot-password
X-Client-Platform: mobile

POST /api/v1/auth/supervisor/recover-password
X-Client-Platform: web
Content-Type: application/json

{"login":"supervisor"}
```

`forgot-password` returns the active supervisor `name` + `phone` for rep/invoicer apps (UC-510).  
`recover-password` sends the supervisor password via email and WhatsApp log (UC-512).

**Token policy**

- Sanctum tokens do **not** expire by time (`expiration = null`)
- Logout deletes the current token only
- Password change revokes **all** tokens (`requires_relogin: true`)
- Suspend / delete revokes all tokens immediately

**Profile (UC-03)**

```http
GET /api/v1/me/profile
PATCH /api/v1/me/profile
Authorization: Bearer {token}
X-Client-Platform: web
```

**Users management (supervisor — UC-04 → UC-08)**

```http
GET    /api/v1/users/dashboard
GET    /api/v1/users?role=rep&status=active
POST   /api/v1/users
GET    /api/v1/users/{id}
PATCH  /api/v1/users/{id}
POST   /api/v1/users/{id}/suspend
POST   /api/v1/users/{id}/restore
DELETE /api/v1/users/{id}
GET    /api/v1/permissions/matrix
Authorization: Bearer {token}
X-Client-Platform: web
```

All user-management routes require Spatie permissions (`users.view`, `users.create`, …).

## Deployment checklist

```bash
php artisan migrate --force
php artisan system:install
php artisan config:cache
php artisan route:cache
```

## Tests

```bash
php artisan test
```

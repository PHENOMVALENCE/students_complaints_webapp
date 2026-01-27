# Student Complaint Resolution Management System (SCRMS)

A PHP/MySQL web app for submitting, tracking, and resolving student complaints. Roles: **student**, **department officer**, **teacher**, **admin**.

## Quick start

1. **Database:** Create MySQL DB `complaintsystem`, then run **`sql/fix_database.sql`** (see `docs/DATABASE_SETUP_INSTRUCTIONS.md`).
2. **SCRMS:** Run **`sql/scrms_database_updates.sql`** for attachments, feedback, notes, info requests.
3. **Config:** Edit **`config/connect.php`** (DB credentials, `BASE_PATH` if app is in a subdir).
4. **Uploads:** Ensure **`uploads/`** exists and is writable (created automatically on first upload).
5. **Admin user:** Insert an admin user (see `docs/DATABASE_SETUP_INSTRUCTIONS.md`).
6. **Run:** Open `http://localhost/complaint_system/` (or your base URL).

## Project structure

| Path | Purpose |
|------|--------|
| **`config/connect.php`** | DB connection, `APP_ROOT`, `BASE_PATH`, `base_url()` |
| **`connect.php`** | Root stub; forwards to `config/connect.php` |
| **`assets/css/`** | `theme.css`, `datatables-theme.css`, `style_*.css` |
| **`includes/`** | `datatables.inc.php` (DataTables init) |
| **`handlers/`** | POST/GET handlers (login, register, resolve, feedback, notes, requests, download, delete) |
| **`sql/`** | Schema and migrations (`fix_database.sql`, `scrms_database_updates.sql`, etc.) |
| **`docs/`** | `DOCUMENTATION.md`, `DATABASE_SETUP_INSTRUCTIONS.md`, `SCRMS_IMPLEMENTATION_GUIDE.md`, etc. |
| **`uploads/`** | Complaint attachments (e.g. `uploads/complaints/{id}/`) |

Entry points: **`index.php`** (login), **`register.php`**, and role-specific dashboards.

## Documentation

- **`docs/DOCUMENTATION.md`** — Full system docs (setup, roles, features, security, file layout).
- **`docs/DATABASE_SETUP_INSTRUCTIONS.md`** — DB setup and admin user.
- **`docs/SCRMS_IMPLEMENTATION_GUIDE.md`** — SCRMS features and usage.

## Base path

If the app runs in a subdir (e.g. `/complaint_system`), set **`BASE_PATH`** in `config/connect.php`:

```php
define('BASE_PATH', '/complaint_system');
```

Use `''` when the app is at the document root.

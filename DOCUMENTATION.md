# Student Complaint Resolution Management System (SCRMS)

## Documentation

This document describes the **Student Complaint Resolution Management System (SCRMS)**—a web-based complaint management platform for students, staff, and administrators. It covers setup, roles, features, DataTables, database, security, and file structure.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Requirements](#2-requirements)
3. [Installation & Setup](#3-installation--setup)
4. [Configuration](#4-configuration)
5. [User Roles & Access](#5-user-roles--access)
6. [Features by Role](#6-features-by-role)
7. [DataTables](#7-datatables)
8. [Database](#8-database)
9. [File Structure](#9-file-structure)
10. [Security](#10-security)
11. [Handlers & Endpoints](#11-handlers--endpoints)
12. [References](#12-references)

---

## 1. Overview

### Purpose

SCRMS allows students to submit, track, and provide feedback on complaints. Department officers and teachers handle complaints; administrators manage users, categories, departments, and reports.

### Tech Stack

- **Backend:** PHP (MySQLi)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **Libraries:** jQuery, DataTables, Font Awesome
- **Styling:** `theme.css` (CSS variables, dashboard layout)

### Key Capabilities

- Categorized complaint submission with optional attachments and anonymity
- Role-based dashboards and workflows
- Status lifecycle: Pending → In Progress → Resolved / Denied; **Awaiting Student Response** when staff request more information
- Feedback and ratings on resolved complaints
- Internal collaboration notes (staff/admin only)
- Information requests from staff to students, with student responses
- Search, filter, and DataTables on complaint/category/department/report tables
- Complaint history (audit trail)

---

## 2. Requirements

- **PHP** 7.4+ (8.x recommended), with extensions: `mysqli`, `json`, `mbstring`, `fileinfo`
- **MySQL** 5.7+ or **MariaDB** 10.2+
- **Web server:** Apache (e.g. XAMPP) or nginx with PHP-FPM
- **Browser:** Modern browser with JavaScript enabled (for DataTables, modals, etc.)

---

## 3. Installation & Setup

### 3.1 Quick Start

1. Place the project under the web root (e.g. `htdocs/complaint_system`).
2. Create the MySQL database and run the schema scripts (see [Database](#8-database)).
3. Configure `connect.php` (see [Configuration](#4-configuration)).
4. Create the `uploads` directory (see [SCRMS Implementation Guide](SCRMS_IMPLEMENTATION_GUIDE.md)).
5. Create an admin user (see [Database Setup Instructions](DATABASE_SETUP_INSTRUCTIONS.md)).
6. Open the app in a browser (e.g. `http://localhost/complaint_system/`).

### 3.2 Database Setup Order

1. **Base schema**  
   Use one of:
   - `fix_database.sql` (recommended for clean install)
   - `complete_database_schema.sql` (full rebuild)
   - `create_missing_tables.sql` (add missing tables only)

2. **SCRMS extensions**  
   Run `scrms_database_updates.sql` to add:
   - `complaint_attachments`, `complaint_feedback`, `collaboration_notes`, `information_requests`
   - `is_anonymous` on `complaints`

3. **Create admin user**  
   See [DATABASE_SETUP_INSTRUCTIONS.md](DATABASE_SETUP_INSTRUCTIONS.md) for `INSERT` examples and password hashing.

### 3.3 Uploads Directory

```bash
mkdir -p uploads/complaints
# Ensure writable by web server (e.g. chmod 755)
```

Uploads are stored under `uploads/complaints/{complaint_id}/`. The `uploads/.gitignore` excludes stored files from version control.

---

## 4. Configuration

### Database (`connect.php`)

```php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "complaintsystem";
```

Adjust these for your environment. Use prepared statements throughout; the app does not use a separate ORM.

---

## 5. User Roles & Access

| Role               | Description |
|--------------------|-------------|
| **admin**          | Full access: dashboards, user management, categories, departments, reports, all complaints, teacher approval. |
| **teacher**        | View all complaints; update status (in progress, resolve, deny). Must be approved by admin. |
| **department_officer** | View only complaints for their assigned department; update status; add notes; request information. |
| **student**        | Register, submit complaints, track own complaints, respond to information requests, submit feedback on resolved complaints. |

### Login Routing

- **admin** → `admin_dashboard.php`
- **teacher** → `teacher_dashboard.php`
- **department_officer** → `department_officer_dashboard.php`
- **student** → `student_dashboard.php`

Handled in `process_index.php` after login.

---

## 6. Features by Role

### 6.1 Student

- **Registration / login:** `register.php`, `index.php`, `process_register.php`, `process_index.php`
- **Submit complaint:** `create_complaint.php`  
  Title, category, department, description; optional file attachments (PDF/images, max 5MB each); optional anonymity.
- **Track complaints:** `track_complaints.php`  
  Filters: All, Pending, In Progress, Resolved, Denied, **Awaiting your response**.
- **View detail:** `view_complaint_detail.php`  
  Attachments, history, feedback form (if resolved), information requests and response form.
- **Respond to information request:** Form in `view_complaint_detail.php` → `respond_to_request.php`
- **Submit feedback:** Form in `view_complaint_detail.php` → `submit_feedback.php`  
  Rating 1–5 and optional text.

### 6.2 Department Officer

- **Dashboard:** `department_officer_dashboard.php`  
  Complaints for own department only; stats; search/filter (ID, title, student, status, category, date range).
- **View / update complaint:** `view_complaint_detail.php`, `department_officer_dashboard.php` (modal).  
  Status updates: In Progress, Resolve, Deny (with mandatory response).
- **Collaboration notes:** Add via `view_complaint_detail.php` → `add_collaboration_note.php`
- **Request information:** Form in `view_complaint_detail.php` → `request_information.php`  
  Sets status to **Awaiting Student Response**.

### 6.3 Teacher

- **Dashboard:** `teacher_dashboard.php`  
  All complaints; status updates (In Progress, Resolve, Deny) via modal.
- **View complaint:** `view_complaint_detail.php`
- **Respond (admin-style):** `respond_complaints.php` → `process_response.php`  
  Admin also uses this flow.

Teachers must be **approved** in `teacher_approval.php` / `process_approval.php` before full access.

### 6.4 Administrator

- **Overview:** `admin_dashboard.php`
- **Teacher approval:** `teacher_approval.php`, `process_approval.php`
- **Complaints:** `students_complaints.php` — all complaints; view, respond, delete.
- **User management:** `users_management.php` — roles, department assignment, delete user.
- **Departments / categories:** `manage_departments.php`, `manage_categories.php`
- **Reports:** `reports.php` — filters (date, department, status); stats by department and category; average resolution time.

### 6.5 Complaint Status Flow

```
pending
  → in_progress (staff/teacher)
  → awaiting_student_response (staff requests more info)
  → pending (after student responds; or stays awaiting if more requests pending)
  → resolved | denied (staff/teacher, with response)
```

---

## 7. DataTables

### Purpose

DataTables add **search**, **sorting**, and **pagination** to HTML tables across the app.

### Usage

1. **Include** the script once per page, before `</body>`:
   ```php
   <?php require_once 'includes/datatables.inc.php'; ?>
   ```
2. **Mark** tables with `class="datatable"` (and optional modifiers below).

### Tables Using DataTables

| Page                        | Table(s)              | Classes / Notes |
|-----------------------------|------------------------|-----------------|
| `students_complaints.php`   | Student submissions    | `datatable datatable-desc`, Actions column non-sortable |
| `department_officer_dashboard.php` | Department complaints | Same |
| `teacher_dashboard.php`     | All complaints         | Same |
| `manage_categories.php`     | Categories             | Same |
| `manage_departments.php`    | Departments            | Same |
| `reports.php`               | By Department, By Category | `datatable` only |

### Classes

- **`datatable`** — Enables DataTables (required).
- **`datatable-desc`** — Sort by first column **descending** (e.g. ID, newest first). Omit for default ascending (e.g. reports).
- **`data-orderable="false"`** on `<th>` — Disable sorting for that column (e.g. Actions).

### Assets

- **Include:** `includes/datatables.inc.php`  
  Loads jQuery 3.7.1, DataTables 1.13.7 (CSS + JS), and `datatables-theme.css`.
- **Theme:** `datatables-theme.css`  
  Overrides for layout, inputs, pagination, and table styling to match `theme.css`.

### Options (in `datatables.inc.php`)

- **Page length:** 10; **length menu:** 10, 25, 50, 100, All.
- **Order:** First column; desc if `datatable-desc`, else asc.
- **Language:** Custom strings for empty table, search, length menu, info, pagination.

### Empty Tables

Tables no longer use a “no data” `<tr><td colspan="…">` row. DataTables shows “No data available.” when there are no rows.

---

## 8. Database

### Core Tables

- **users** — Accounts; `role`, `approved`, `department_id` (for officers).
- **departments** — Departments for routing and officer assignment.
- **complaint_categories** — Categories for complaints.
- **complaints** — Main complaint record; `status`, `response`, `is_anonymous`, etc.
- **complaint_history** — Audit log of status changes and notable actions.

### SCRMS Tables (`scrms_database_updates.sql`)

- **complaint_attachments** — File metadata; links to `complaints`.
- **complaint_feedback** — Rating and feedback for resolved complaints.
- **collaboration_notes** — Internal staff notes per complaint.
- **information_requests** — Staff requests for more info; student response and status.

### Status Values

- `pending`
- `awaiting_student_response`
- `in_progress`
- `resolved`
- `denied`

---

## 9. File Structure

### Entry & Auth

| File                | Purpose |
|---------------------|--------|
| `index.php`         | Login form |
| `process_index.php` | Login handler; role-based redirect |
| `register.php`      | Student registration form |
| `process_register.php` | Registration handler |
| `logout.php`        | Session destroy, redirect to login |

### Dashboards

| File                          | Role(s) |
|-------------------------------|--------|
| `admin_dashboard.php`         | Admin |
| `student_dashboard.php`       | Student |
| `teacher_dashboard.php`       | Teacher |
| `department_officer_dashboard.php` | Department officer |

### Complaints

| File                    | Purpose |
|-------------------------|--------|
| `create_complaint.php`  | Submit complaint (with attachments, anonymity) |
| `track_complaints.php`  | Student: list and filter own complaints |
| `view_complaint_detail.php` | Detail view; attachments, history, notes, requests, feedback |
| `respond_complaints.php`| Admin/teacher respond UI |
| `process_response.php`  | Resolve/deny handler |
| `delete_complaints.php` | Delete complaint (admin) |

### SCRMS Handlers

| File                     | Purpose |
|--------------------------|--------|
| `submit_feedback.php`    | Submit feedback/rating for resolved complaint |
| `add_collaboration_note.php` | Add internal note |
| `request_information.php`   | Staff request more info from student |
| `respond_to_request.php`   | Student response to information request |
| `download_attachment.php`  | Secure download of complaint attachment |

### Admin

| File                   | Purpose |
|------------------------|--------|
| `users_management.php` | Users, roles, department assignment, delete |
| `manage_categories.php`| CRUD categories |
| `manage_departments.php` | CRUD departments |
| `reports.php`          | Reports and analytics |
| `teacher_approval.php` | Approve/reject teachers |
| `process_approval.php` | Approval handler |
| `delete_user.php`      | Delete user |

### Other

| File              | Purpose |
|-------------------|--------|
| `connect.php`     | DB connection |
| `profile.php`     | User profile |
| `theme.css`       | Global styles |
| `datatables-theme.css` | DataTables overrides |
| `includes/datatables.inc.php` | DataTables setup and init |

### SQL / Docs

- `fix_database.sql`, `complete_database_schema.sql`, `create_missing_tables.sql` — Base schema.
- `scrms_database_updates.sql` — SCRMS migrations.
- `DATABASE_SETUP_INSTRUCTIONS.md` — DB setup and admin user.
- `IMPLEMENTATION_SUMMARY.md` — Implementation overview.
- `SCRMS_IMPLEMENTATION_GUIDE.md` — SCRMS features and usage.

---

## 10. Security

- **Sessions:** Login sets `$_SESSION['username']`, `$_SESSION['role']`; pages check these before rendering.
- **Role checks:** Every restricted page validates `$_SESSION['role']` and redirects unauthorized users.
- **Prepared statements:** All dynamic SQL uses `mysqli` prepared statements.
- **Passwords:** `password_hash()` / `password_verify()`.
- **File uploads:** Allowed types (PDF, images); MIME checks; max size 5MB; unique names; stored under `uploads/complaints/{id}/`.
- **Downloads:** `download_attachment.php` checks role and complaint access before serving files.
- **Anonymity:** `is_anonymous` hides student identity from staff; admins always see identity.

---

## 11. Handlers & Endpoints

| Endpoint                 | Method | Purpose |
|--------------------------|--------|--------|
| `process_index.php`      | POST   | Login |
| `process_register.php`   | POST   | Student registration |
| `process_response.php`   | POST   | Resolve/deny complaint (admin/teacher) |
| `process_approval.php`   | POST   | Approve/reject teacher |
| `submit_feedback.php`    | POST   | Submit feedback for resolved complaint |
| `add_collaboration_note.php` | POST | Add collaboration note |
| `request_information.php`   | POST | Request more info from student |
| `respond_to_request.php`   | POST | Student response to request |
| `download_attachment.php`  | GET   | Download attachment (id in query) |
| `delete_complaints.php`  | GET    | Delete complaint (id in query; confirm) |
| `delete_user.php`       | POST   | Delete user |

All POST handlers validate `$_SERVER['REQUEST_METHOD']` and relevant `$_POST` fields; redirect with `$_SESSION['message']` on success/error.

---

## 12. References

- [DATABASE_SETUP_INSTRUCTIONS.md](DATABASE_SETUP_INSTRUCTIONS.md) — Database setup, admin user, table overview.
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) — Implemented features and SRS alignment.
- [SCRMS_IMPLEMENTATION_GUIDE.md](SCRMS_IMPLEMENTATION_GUIDE.md) — SCRMS features, DB migrations, usage, and testing checklist.

---

*Last updated for SCRMS with DataTables, information requests, collaboration notes, attachments, and feedback.*

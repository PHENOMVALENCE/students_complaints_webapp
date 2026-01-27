# Database Setup Instructions

## Current Situation
Your database dump shows that:
- ✅ `departments` table exists
- ✅ `complaint_categories` table exists  
- ❌ `users` table is missing (error: doesn't exist in engine)
- ❌ `complaints` table is missing (error: doesn't exist in engine)

## Solution Files Provided

I've created three SQL files for you:

### 1. `sql/fix_database.sql` (RECOMMENDED - Use this one)
- **Drops and recreates** the missing tables
- **Use this if:** You don't have important data in users/complaints tables, or the tables are corrupted
- **Warning:** This will delete any existing data in users and complaints tables

### 2. `sql/create_missing_tables.sql`
- **Creates tables only if they don't exist**
- **Use this if:** You want to preserve existing data (though the error suggests tables don't exist)
- Has complex foreign key handling

### 3. `complete_database_schema.sql`
- **Complete database from scratch**
- **Use this if:** You want to rebuild everything
- Includes all tables with sample data

## Recommended Steps

### Option A: Quick Fix (Recommended)
1. Open phpMyAdmin
2. Select your `complaintsystem` database
3. Go to SQL tab
4. Run `sql/fix_database.sql`
5. Done! ✅

### Option B: If You Have Existing Data
1. **Backup first!** Export your database
2. Check if you have data in users/complaints tables:
   ```sql
   SELECT COUNT(*) FROM users;
   SELECT COUNT(*) FROM complaints;
   ```
3. If tables are empty or corrupted, use `sql/fix_database.sql`
4. If you need to preserve data, you'll need to:
   - Export existing data
   - Run the fix script
   - Re-import data (may need to adjust for new schema)

## After Running the Script

### 1. Create Admin User
You'll need to create at least one admin user to access the system:

```sql
INSERT INTO users (username, password, role, approved) 
VALUES ('Admin', '$2y$10$iTj3YkBLuINxSzU4NXs1jOTGjrG7K4dXqKc6hv7eaU2q78hkVa8cO', 'admin', 1);
```

Password for above: `Admin` (hashed with bcrypt)

Or create your own:
```sql
INSERT INTO users (username, password, role, approved) 
VALUES ('your_admin_username', '$2y$10$YOUR_HASHED_PASSWORD', 'admin', 1);
```

To hash a password in PHP:
```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

### 2. Create Department Officers (Optional)
```sql
-- Create a department officer
INSERT INTO users (username, password, role, approved, department_id) 
VALUES ('officer_username', '$2y$10$HASHED_PASSWORD', 'department_officer', 1, 1);
-- department_id = 1 means Academic Affairs (adjust as needed)
```

### 3. Verify Tables
Run this to verify all tables exist:
```sql
SHOW TABLES;
```

You should see:
- `complaint_categories`
- `complaints`
- `complaint_history`
- `departments`
- `users`

## Table Structure Summary

### `users` Table
- `user_id` (Primary Key)
- `username` (Unique)
- `password` (Hashed)
- `role` (student, teacher, admin, department_officer)
- `approved` (0 or 1)
- `department_id` (Foreign Key to departments, NULL for non-officers)

### `complaints` Table
- `complaint_id` (Primary Key)
- `title` (Required)
- `student_username` (Foreign Key to users)
- `complaint` (Description text)
- `category_id` (Foreign Key to complaint_categories, optional)
- `department_id` (Foreign Key to departments, required)
- `routed_at` (Timestamp when routed)
- `status` (pending, in_progress, resolved)
- `response` (Department officer response)
- `created_at` (Auto timestamp)
- `updated_at` (Auto timestamp)

### `complaint_history` Table
- `history_id` (Primary Key)
- `complaint_id` (Foreign Key to complaints)
- `action` (submitted, in_progress, resolve, etc.)
- `performed_by` (Foreign Key to users.username)
- `old_status` (Previous status)
- `new_status` (New status)
- `notes` (Additional notes)
- `created_at` (Auto timestamp)

## Troubleshooting

### Error: "Table doesn't exist in engine"
This usually means the table files are corrupted. Solution: Drop and recreate the table.

### Error: "Foreign key constraint fails"
Make sure:
1. `departments` table exists and has data
2. `complaint_categories` table exists
3. Run tables in order: users → complaints → complaint_history

### Error: "Duplicate entry"
- Check if table already exists
- Use `DROP TABLE IF EXISTS` before creating

## Need Help?

If you encounter issues:
1. Check MySQL error logs
2. Verify all foreign key referenced tables exist
3. Ensure you have proper permissions
4. Try running statements one at a time to isolate the issue

---

**Ready to proceed?** Use `sql/fix_database.sql` - it's the simplest solution! 🚀


# SCRMS Implementation Guide

This document outlines the implementation of the Student Complaint Resolution Management System (SCRMS) based on the functional requirements.

## Database Setup

### Step 1: Run Database Migration

Execute the SQL script to add new tables and columns:

```sql
-- Run sql/scrms_database_updates.sql in phpMyAdmin or MySQL command line
```

This script creates:
- `complaint_attachments` table for file uploads
- `complaint_feedback` table for ratings and feedback
- `collaboration_notes` table for internal staff notes
- `information_requests` table for staff-to-student requests
- Adds `is_anonymous` column to `complaints` table

### Step 2: Create Uploads Directory

Create the uploads directory structure:

```bash
mkdir -p uploads/complaints
chmod 755 uploads/complaints
```

Or create it via PHP (it will be created automatically on first upload).

## Implemented Features

### Student Features (FR 1.x)

#### ✅ FR 1.1: Secure Registration and Authentication
- Already implemented in existing system
- Password hashing with `password_hash()`
- Session management

#### ✅ FR 1.2: Categorized Complaint Submission
- Enhanced complaint form with category selection
- Department routing
- Title and description fields

#### ✅ FR 1.3: Evidence Attachment
- **File Upload Support**: Students can upload multiple files (PDF, images)
- **File Validation**: 
  - Allowed types: PDF, JPG, PNG, GIF
  - Maximum size: 5MB per file
- **Storage**: Files stored in `uploads/complaints/{complaint_id}/`
- **Database**: Attachments linked via `complaint_attachments` table

**Location**: `create_complaint.php`

#### ✅ FR 1.4: Real-time Status Tracking
- Dashboard with status filters
- Real-time status updates
- Timeline view in complaint details

**Location**: `track_complaints.php`, `view_complaint_detail.php`

#### ✅ FR 1.5: Resolution Feedback and Rating
- **Rating System**: 1-5 star rating
- **Feedback Comments**: Optional text feedback
- **Access**: Only available for resolved complaints
- **One-time Submission**: Each complaint can only be rated once

**Location**: `view_complaint_detail.php` (form), `submit_feedback.php` (handler)

#### ✅ FR 1.6: Toggle Anonymity
- **Anonymity Option**: Checkbox in complaint submission form
- **Identity Masking**: Student name hidden from department staff
- **Admin Visibility**: Administrators can always see student identity
- **Database**: Stored in `is_anonymous` column

**Location**: `create_complaint.php`, `view_complaint_detail.php`

### Administrator Features (FR 2.x)

#### ✅ FR 2.1: User Account and Role Management
- Already implemented in `users_management.php`
- Teacher approval system in `teacher_approval.php`

#### ✅ FR 2.2: Complaint Triage and Assignment
- Manual assignment via department selection
- Automatic routing based on department

#### ✅ FR 2.3: System-Wide Monitoring
- Dashboard with statistics
- Real-time metrics

**Location**: `admin_dashboard.php`, `reports.php`

#### ✅ FR 2.4: Comprehensive Report Generation
- Filter by department, status, date range
- Statistics by department and category
- Average resolution times

**Location**: `reports.php`

#### ✅ FR 2.5: Category and Department Configuration
- Already implemented in `manage_categories.php` and `manage_departments.php`

#### ✅ FR 2.6: Audit Trail Access
- Complaint history tracking
- Activity logs in `complaint_history` table

**Location**: `view_complaint_detail.php` (timeline section)

### Staff Features (FR 3.x)

#### ✅ FR 3.1: Task Dashboard
- Department-specific complaint view
- Workload statistics
- Priority ordering

**Location**: `department_officer_dashboard.php`

#### ✅ FR 3.2: Status Update and Workflow
- Status transitions: Pending → In Progress → Resolved/Denied
- Automatic timestamping
- Status history tracking

**Location**: `department_officer_dashboard.php`, `process_response.php`

#### ✅ FR 3.3: Resolution Logging
- Mandatory response field when resolving
- Resolution remarks stored in `response` column
- Validation prevents closing without remarks

**Location**: `process_response.php`, `department_officer_dashboard.php`

#### ✅ FR 3.4: Collaboration Notes (Internal)
- **Internal Notes**: Staff can add notes visible only to staff/admin
- **Privacy**: Notes not visible to students
- **Multi-user**: Multiple staff can add notes
- **Timestamps**: Each note has creation date and author

**Location**: `view_complaint_detail.php` (notes section), `add_collaboration_note.php` (handler)

#### ✅ FR 3.5: Information Request
- **Request Interface**: Staff can request more information from students
- **Status Change**: Automatically changes complaint to "awaiting_student_response"
- **Student Response**: Students can respond to requests
- **Status Reversion**: Returns to "pending" after student responds

**Location**: 
- Request: `view_complaint_detail.php` (form), `request_information.php` (handler)
- Response: `view_complaint_detail.php` (form), `respond_to_request.php` (handler)

#### ✅ FR 3.6: Search and Filter Functionality
- **Multi-criteria Search**: 
  - Complaint ID
  - Title
  - Description
  - Student username
- **Advanced Filtering**:
  - Status (Pending, In Progress, Resolved, Denied, Awaiting Response)
  - Category
  - Date range (from/to)
- **Combined Filters**: All filters work together

**Location**: `department_officer_dashboard.php`

## File Structure

### New Files Created

1. **Database & Setup**:
   - `scrms_database_updates.sql` - Database migration script
   - `SCRMS_IMPLEMENTATION_GUIDE.md` - This file

2. **Student Features**:
   - `submit_feedback.php` - Handle feedback submission

3. **Staff Features**:
   - `add_collaboration_note.php` - Add internal collaboration notes
   - `request_information.php` - Request information from students
   - `respond_to_request.php` - Student response handler

4. **Uploads**:
   - `uploads/.gitignore` - Ignore uploaded files in git

### Modified Files

1. **Complaint Submission**:
   - `create_complaint.php` - Added file upload and anonymity toggle

2. **Complaint Viewing**:
   - `view_complaint_detail.php` - Added attachments, feedback, notes, requests

3. **Staff Dashboard**:
   - `department_officer_dashboard.php` - Added search/filter functionality

## Usage Instructions

### For Students

1. **Submitting a Complaint with Evidence**:
   - Go to "Submit Complaint"
   - Fill in title, category, department, and description
   - Click "Choose Files" to upload supporting documents (PDF/images)
   - Check "Submit anonymously" if desired
   - Click "Submit Complaint"

2. **Providing Feedback**:
   - Go to "Track Complaints"
   - Click "View Details" on a resolved complaint
   - Rate the resolution (1-5 stars)
   - Add optional feedback comments
   - Click "Submit Feedback"

3. **Responding to Information Requests**:
   - If staff requests more information, you'll see a request in complaint details
   - Enter your response
   - Click "Submit Response"

### For Staff

1. **Adding Collaboration Notes**:
   - Open complaint details
   - Scroll to "Collaboration Notes" section
   - Enter note text
   - Click "Add Note"

2. **Requesting Information**:
   - Open complaint details
   - Scroll to "Request Information from Student" section
   - Enter request message
   - Click "Send Request"
   - Complaint status changes to "Awaiting Student Response"

3. **Searching and Filtering**:
   - On department dashboard, use the search/filter form
   - Enter search terms (ID, title, student name)
   - Select status, category, or date range
   - Click "Search & Filter"
   - Click "Reset" to clear filters

## Status Values

The system now supports these status values:
- `pending` - Initial status
- `awaiting_student_response` - Information requested from student
- `in_progress` - Being worked on
- `resolved` - Completed successfully
- `denied` - Rejected/denied

## Security Considerations

1. **File Uploads**:
   - File type validation (only PDF/images)
   - File size limits (5MB)
   - Unique filenames to prevent conflicts
   - Files stored outside web root recommended for production

2. **Anonymity**:
   - Student identity hidden from staff when anonymous
   - Admin always sees identity for system management
   - Proper access control checks

3. **Access Control**:
   - Department officers only see their department's complaints
   - Students only see their own complaints
   - Collaboration notes only visible to staff/admin

## Notes

- The uploads directory should be protected in production (outside web root or with .htaccess)
- Consider adding email notifications for information requests
- File size limits can be adjusted in `create_complaint.php`
- Database migration should be run before using new features

## Testing Checklist

- [ ] Submit complaint with file attachments
- [ ] Submit anonymous complaint
- [ ] View complaint details with attachments
- [ ] Provide feedback on resolved complaint
- [ ] Add collaboration note (staff)
- [ ] Request information from student (staff)
- [ ] Respond to information request (student)
- [ ] Search and filter complaints (staff)
- [ ] Verify anonymity masking works correctly
- [ ] Test file upload validation (type and size)

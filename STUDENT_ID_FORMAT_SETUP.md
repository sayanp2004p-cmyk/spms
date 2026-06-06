# Student ID Format System - Setup Guide

## Overview
The student registration system now uses a **configurable student ID format** based on templates stored in the database per user. This replaces the hardcoded `YC[##]/[MMYY][First Letter]` format.

## Database Setup

### Required Fields (already in sql/setup.sql)
The following columns must exist in the `users` table:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_format VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_prefix VARCHAR(50) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id_required TINYINT(1) DEFAULT 0;
```

### Set Format for a User
Run this SQL to configure a user's student ID format:

```sql
-- Example 1: YC[##]/[MM][YY][F] format
UPDATE users SET 
  student_id_format = '{PREFIX}{SEQ}/{MM}{YY}{F}',
  student_id_prefix = 'YC',
  student_id_required = 1
WHERE id = 1;

-- Example 2: [YYYY]-[SEQ]-[F][L] format
UPDATE users SET 
  student_id_format = '{YYYY}-{SEQ}-{F}{L}',
  student_id_prefix = NULL,
  student_id_required = 1
WHERE id = 2;

-- Example 3: Custom format with full names
UPDATE users SET 
  student_id_format = '{PREFIX}-{FIRST}{SEQ}',
  student_id_prefix = 'STU',
  student_id_required = 1
WHERE id = 3;
```

## Format Keywords Reference

| Keyword | Description | Example |
|---------|-------------|---------|
| `{YYYY}` | Full 4-digit year | 2026 |
| `{YY}` | Two-digit year | 26 |
| `{MM}` | Month (01-12) | 02 |
| `{DD}` | Day (01-31) | 15 |
| `{SEQ}` | Sequential number (01, 02, ...) | 01, 02, 99 |
| `{FIRST}` | First name | John |
| `{LAST}` | Last name | Doe |
| `{F}` | First initial (uppercase) | J |
| `{L}` | Last initial (uppercase) | D |
| `{PREFIX}` | Custom prefix from database | YC, STU |

## Example Formats

### Format 1: Legacy-style (YC[##]/[MM][YY][F])
- Template: `{PREFIX}{SEQ}/{MM}{YY}{F}`
- Prefix: `YC`
- Example: `YC01/0226J`, `YC02/0226A`

### Format 2: Year-based (YYYY-SEQ-Initials)
- Template: `{YYYY}-{SEQ}-{F}{L}`
- Prefix: (none)
- Example: `2026-01-JD`, `2026-02-AB`

### Format 3: Full names (PREFIX-FirstName-SEQ)
- Template: `{PREFIX}-{FIRST}{SEQ}`
- Prefix: `STU`
- Example: `STU-John01`, `STU-Alice02`

### Format 4: Date-based (YYMMDD-SEQ)
- Template: `{YY}{MM}{DD}-{SEQ}`
- Prefix: (none)
- Example: `260215-01`, `260215-02`

## Features

### Auto-Generation
When user enters first name, last name, and admission date, the system automatically generates the student ID based on the configured format.

### Gap-Filling
The "Use deleted ID sequence" checkbox allows the system to fill in gaps from previously deleted student IDs:
- Without gap-fill: Next ID is always sequential (01, 02, 03, ...)
- With gap-fill: System reuses deleted ID numbers (01, 03, 05, ... if 02, 04 were deleted)

### Search & Edit
Users can:
- Search for existing students by student ID
- Edit student records
- Manual ID entry if needed

## PHP Implementation Details

### New Functions (php/get_application.php)

```php
get_user_id_config($pdo, $user_id)
  → Returns: ['student_id_format', 'student_id_prefix', 'student_id_required']

replace_format_keywords($format, $first_name, $last_name, $admission_date, $seq_number, $prefix)
  → Returns: Generated student ID with keywords replaced

get_next_sequential_number($pdo, $user_id, $format, $first_name, $last_name, $admission_date, $prefix, $fill_gaps)
  → Returns: Next sequential number (considering deleted IDs if fill_gaps=true)

generate_format_pattern($format)
  → Returns: Regex pattern for extracting sequence numbers from existing IDs
```

### New API Endpoints

#### Get User's ID Configuration
```
GET /php/get_application.php?action=get_user_id_config

Response:
{
  "success": true,
  "data": {
    "student_id_format": "{PREFIX}{SEQ}/{MM}{YY}{F}",
    "student_id_prefix": "YC",
    "student_id_required": 1
  }
}
```

#### Generate Student ID
```
GET /php/get_application.php?action=generate_id
  &first_name=John
  &last_name=Doe
  &admission_date=2026-02-15
  &fill_gaps=false

Response:
{
  "success": true,
  "data": {
    "student_id": "YC01/0226J",
    "seq_number": 1
  }
}
```

## Testing the System

### Test Case 1: Basic Generation
1. Go to Add Student page
2. Config should auto-load with user's format
3. Enter First Name: John
4. Enter Last Name: Doe
5. Enter Admission Date: 2026-02-15
6. Student ID should auto-generate based on format

### Test Case 2: Gap-Filling
1. Create multiple students (generates IDs: 01, 02, 03)
2. Delete student with ID 02
3. Create new student with "Use deleted ID sequence" checked
4. New ID should use 02 instead of 04

### Test Case 3: Search & Edit
1. Enter an existing Student ID in the search field
2. System should fetch the student data
3. Modify details
4. Save to update

## Troubleshooting

### No Student ID Appears
- Ensure user has `student_id_format` set in database
- Check browser console for errors
- Verify first name, last name, and admission date are filled

### Wrong Format Generated
- Check user's `student_id_format` in database
- Verify keywords are spelled correctly: `{YYYY}`, `{SEQ}`, etc.
- Date format should be YYYY-MM-DD

### Cannot Save Student
- Ensure Student ID is unique for the user
- If editing, verify you're changing appropriate fields
- Check for database constraints

## Migration from Old System

If upgrading from the old hardcoded system:

```sql
-- Set default format for existing users to maintain compatibility
UPDATE users SET 
  student_id_format = '{PREFIX}{SEQ}/{MM}{YY}{F}',
  student_id_prefix = 'YC',
  student_id_required = 1
WHERE student_id_format IS NULL;
```

## Customization

To use a different default format for new users, modify the DEFAULT in the ALTER TABLE statement or update the database migration scripts.

## Notes

- Keywords are case-sensitive: use `{FIRST}` not `{first}`
- {SEQ} is padded with leading zeros to 2 digits (01, 02, ..., 99, 100, ...)
- Name-based keywords ({FIRST}, {LAST}, {F}, {L}) use admission_date field to extract date components
- Format validation happens on server-side; invalid formats return error

# Ebook Library Implementation

## Overview
A standalone DIY tool for the CfCbazar platform that allows users to browse, search, and download ebooks. Admins (status 1) and Contributors (status 3) can upload new ebooks.

## Features
- **Search & Browse**: Search by title, author, description, or tags
- **Filter by Category**: Filter ebooks by category
- **Filter by File Type**: Filter by PDF, DOC, DOCX, or ZIP
- **Email-based Downloads**: Users must provide email to download (for tracking)
- **Upload Management**: Admins and contributors can upload new ebooks
- **Edit Functionality**: Admins and contributors can edit ebook details
- **Admin Management**: Admins can view all ebooks (including inactive) and delete ebooks
- **Download Tracking**: Records all downloads with email and IP address
- **Pagination**: Efficient browsing with paginated results
- **Security**: CSRF protection, file type validation, size limits

## Files Structure
```
/diy/library/
├── index.php          # Main library page with search and browse
├── upload.php         # Upload form for admins/contributors
├── edit.php           # Edit form for admins/contributors
├── download.php       # Download handler with email requirement
├── setup.php          # Database setup script
├── schema.sql         # SQL schema for tables
├── .htaccess          # Security configuration
├── uploads/           # Uploaded files directory
│   └── .htaccess      # Protect uploaded files from direct access
└── README.md          # This file
```

## Database Schema

### ebooks table
- `id`: Primary key
- `title`: Ebook title
- `description`: Description text
- `author`: Author name
- `file_path`: Relative path to uploaded file
- `file_type`: File type (pdf, doc, docx, zip)
- `file_size`: File size in bytes
- `upload_date`: Upload timestamp
- `uploaded_by`: Email of uploader
- `download_count`: Number of downloads
- `category`: Category for filtering
- `tags`: Comma-separated tags
- `is_active`: Active status (0/1)

### ebook_downloads table
- `id`: Primary key
- `ebook_id`: Foreign key to ebooks
- `email`: Email of downloader
- `download_date`: Download timestamp
- `ip_address`: IP address of downloader

## Installation

1. **Run Setup Script**: Visit `/diy/library/setup.php` in your browser to create database tables and directories
2. **Verify Tables**: Check that `ebooks` and `ebook_downloads` tables are created
3. **Test Upload**: Login as admin/contributor and test uploading an ebook
4. **Test Download**: Test the download functionality with email requirement

## User Roles & Permissions

### Admin (Status 1)
- Can upload ebooks
- Can view all ebooks
- Can download ebooks

### Contributor (Status 3)
- Can upload ebooks
- Can view all ebooks
- Can download ebooks

### Regular Users (Status 5, 4, 2, 0)
- Can view all ebooks
- Can download ebooks (with email requirement)
- Cannot upload ebooks

## File Upload Restrictions

- **Allowed File Types**: PDF, DOC, DOCX, ZIP
- **Maximum File Size**: 50MB
- **Storage Location**: `/diy/library/uploads/`
- **Security**: Files protected from direct access via .htaccess

## Security Features

1. **CSRF Protection**: All forms use CSRF tokens
2. **File Type Validation**: Server-side validation of file types
3. **Size Limits**: Maximum file size enforced
4. **Direct Access Prevention**: .htaccess rules prevent direct file access
5. **SQL Injection Prevention**: Prepared statements used throughout
6. **Email Validation**: Server-side email validation for downloads
7. **Authentication Check**: Upload pages restricted to authorized users

## API/Integration Points

### Integration with Existing CfCbazar System
- Uses existing authentication system (`getUserStatus()`)
- Uses existing layout system (`include_header()`, `include_footer()`)
- Uses existing CSRF protection (`csrf_token()`)
- Uses existing visit tracking (`trackVisit()`)
- Follows existing DIY tool patterns

### Main DIY Index Integration
- Added to `/diy/index.php` as a new card
- Follows the same visual style and structure as other tools

## Usage Examples

### For Users
1. Navigate to `/diy/library/index.php`
2. Use search bar to find specific ebooks
3. Use filters to narrow down by category or file type
4. Click "Download" on desired ebook
5. Enter email address to complete download

### For Admins/Contributors
1. Navigate to `/diy/library/index.php`
2. Click "Upload New Ebook" button
3. Fill in ebook details (title, author, description, etc.)
4. Select file (PDF, DOC, DOCX, or ZIP)
5. Click "Upload Ebook" to add to library
6. Click "Edit" button on any ebook to modify details
7. Admins can click "Manage All Ebooks" to view inactive ebooks
8. Admins can delete ebooks from the edit page

## Customization

### Changing File Size Limit
Edit `upload.php` line 19:
```php
$maxFileSize = 50 * 1024 * 1024; // Change 50 to desired MB
```

### Adding More File Types
Edit both `upload.php` and the database schema to include new file types.

### Changing Permissions
Edit the permission check in `upload.php` line 20:
```php
$canUpload = in_array($userStatus, [1, 3], true); // Add/remove status codes
```

## Troubleshooting

### Uploads Not Working
- Check that `/diy/library/uploads/` directory exists and is writable
- Verify PHP upload limits in php.ini
- Check .htaccess permissions

### Database Errors
- Run `setup.php` to ensure tables are created
- Check database connection in `config.php`
- Verify user has CREATE TABLE permissions

### Download Issues
- Check that files exist in uploads directory
- Verify .htaccess is not blocking legitimate downloads
- Check file permissions on uploaded files

## Future Enhancements

Potential improvements for future versions:
- User reviews and ratings
- Advanced search with filters
- User favorites/bookmarks
- Bulk upload functionality
- Automatic thumbnail generation
- Categories management system
- Download statistics dashboard
- Email notifications for new uploads
- Integration with existing user system for upload history

## Support

For issues or questions, refer to the main CfCbazar documentation or contact the development team.
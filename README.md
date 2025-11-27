# Gallium Solutions Limited - Loan Management System

A comprehensive, cloud-ready loan management system for efficient loan processing, tracking, and administration.

## Features

- **Multi-User Support**: Admin, Loan Officer, and Customer roles
- **Cloud-Ready**: Configured for shared cloud server deployment
- **Real-Time Data**: Shared database for multiple users across different computers
- **Validation**: Age verification (18+), no backdating, comprehensive input validation
- **Self-Registration**: Users create their own accounts with credentials
- **Loan Management**: Complete loan lifecycle from application to completion
- **Payment Tracking**: Automated payment schedules and tracking
- **Audit Trail**: Complete activity logging
- **Notifications**: In-system notifications for important events

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB equivalent)
- Web server (Apache/Nginx)
- PDO MySQL extension

## Installation

### 1. Database Setup

1. Create a new database (or use existing):
   ```sql
   CREATE DATABASE gallium_loans CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Run the installation script:
   - Navigate to `install_database.php` in your browser
   - Or import `gallium_loans.sql` directly into your database

3. **IMPORTANT**: Delete `install_database.php` after installation for security

### 2. Configuration

1. Update `config.php` with your database credentials:
   ```php
   $host = 'your-database-host';
   $dbname = 'gallium_loans';
   $username = 'your-database-user';
   $password = 'your-database-password';
   ```

2. For cloud deployment, use environment variables:
   ```bash
   export DB_HOST=your-cloud-db-host
   export DB_NAME=gallium_loans
   export DB_USER=your-db-user
   export DB_PASS=your-db-password
   ```

3. Or create a `.env` file (copy from `.env.example`) and configure it

### 3. File Permissions

Set appropriate file permissions:
```bash
chmod 644 config.php
chmod 755 uploads/ (if using file uploads)
```

### 4. First User Registration

1. Navigate to `register.php`
2. Create your first admin account:
   - Select "Administrator" as account type
   - Fill in all required fields
   - Remember your credentials for login

## Cloud Deployment

### Shared Server Setup

This system is designed to work on shared cloud servers where multiple users access the same database from different computers.

1. **Database Configuration**:
   - Use a cloud database service (AWS RDS, Google Cloud SQL, etc.)
   - Or use a shared MySQL server accessible from all client computers
   - Update `config.php` with the cloud database host

2. **Application Deployment**:
   - Upload all files to your cloud server
   - Ensure all users can access the same application URL
   - Or deploy on each computer pointing to the same database

3. **Environment Variables** (Recommended):
   ```bash
   # Set in your server environment or .env file
   DB_HOST=your-cloud-db-host.com
   DB_NAME=gallium_loans
   DB_USER=shared_user
   DB_PASS=secure_password
   APP_ENV=production
   ```

### Multi-Computer Access

- **Option 1**: All computers access the same web application URL
- **Option 2**: Each computer has the application installed, but all point to the same database server

## User Roles

### Administrator
- Full system access
- Manage all loans
- Manage users and officers
- View reports and analytics
- System configuration

### Loan Officer
- Manage assigned loans
- Approve/reject loan applications
- Record payments
- View customer information
- Generate reports for assigned loans

### Customer
- Apply for loans
- View loan status
- Make payments
- View payment history
- Update profile

## Validation Rules

- **Age Requirement**: All loan applicants must be 18 years or older
- **No Backdating**: Dates cannot be set in the past (application date, approval date, payment date)
- **Email Validation**: Valid email format required
- **Phone Validation**: 9-15 digit phone numbers
- **Loan Amount**: Minimum UGX 1,000, Maximum UGX 100,000,000

## Security Features

- Password hashing using bcrypt
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- Session management
- Audit logging
- Role-based access control

## File Structure

```
/
├── admin/              # Admin dashboard and management pages
├── officer/            # Loan officer dashboard and pages
├── assets/             # CSS, images, and static assets
├── includes/           # Header, footer, and shared components
├── config.php          # Database configuration
├── functions.php       # Core functions
├── index.php           # Landing page
├── login.php           # Login page
├── register.php        # Registration page
├── dashboard.php       # Customer dashboard
├── gallium_loans.sql   # Database schema
└── install_database.php # Installation script
```

## Troubleshooting

### Database Connection Issues
- Verify database credentials in `config.php`
- Check database server is accessible
- Ensure MySQL user has proper permissions
- Check firewall settings for cloud databases

### Permission Errors
- Ensure web server has read/write permissions
- Check file ownership
- Verify database user has CREATE, INSERT, UPDATE, DELETE permissions

### Session Issues
- Check PHP session configuration
- Verify session directory is writable
- Check cookie settings in browser

## Support

For issues or questions:
1. Check the error logs
2. Verify all requirements are met
3. Ensure database is properly configured
4. Check file permissions

## License

Proprietary - Gallium Solutions Limited

## Version

Current Version: 1.0.0

---

**Important**: After installation, delete `install_database.php` and `.installed` files for security.


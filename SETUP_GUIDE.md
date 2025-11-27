# Gallium Solutions Limited - Quick Setup Guide

## Step 1: Database Installation

1. **Create Database**:
   - Open phpMyAdmin or your MySQL client
   - Create a new database named `gallium_loans`
   - Or use the SQL file: Import `gallium_loans.sql` into your database

2. **Run Installation Script** (Alternative):
   - Navigate to: `http://your-domain/install_database.php`
   - Follow the on-screen instructions
   - **IMPORTANT**: Delete `install_database.php` after installation

## Step 2: Configure Database Connection

Edit `config.php`:

```php
$host = 'your-database-host';      // e.g., 'localhost' or 'db.example.com'
$dbname = 'gallium_loans';
$username = 'your-database-user';
$password = 'your-database-password';
```

### For Cloud Deployment:

Set environment variables:
```bash
export DB_HOST=your-cloud-db-host
export DB_NAME=gallium_loans
export DB_USER=your-db-user
export DB_PASS=your-db-password
```

Or create a `.env` file (copy from `.env.example`)

## Step 3: Create First Admin Account

1. Navigate to: `http://your-domain/register.php`
2. Fill in the registration form:
   - **Account Type**: Select "Administrator"
   - **Full Name**: Your name
   - **Email**: Your email address
   - **Phone**: Your phone number
   - **Password**: Create a strong password (min 6 characters)
3. Click "Register"
4. You will be redirected to login

## Step 4: Login

1. Navigate to: `http://your-domain/login.php`
2. Enter your email and password
3. You will be redirected to the Admin Dashboard

## Step 5: Complete Customer Profile (For Loan Applications)

If you want to apply for loans as a customer:

1. Login as a customer
2. Go to Profile page
3. Fill in:
   - **Date of Birth**: Must be 18+ years old
   - **National ID**: (Optional)
   - **Gender**: Select your gender
   - **Address**: Your address
   - **Occupation**: Your occupation
   - **Employer**: Your employer name
   - **Monthly Income**: Your monthly income
4. Save profile

## Important Notes

### Age Requirement
- All loan applicants must be **18 years or older**
- The system will automatically validate age from date of birth

### No Backdating
- Application dates cannot be in the past
- Approval dates cannot be in the past
- Payment dates cannot be in the past
- All dates must be today or future dates

### Self-Created Credentials
- Users create their own accounts during registration
- No pre-existing credentials needed
- Each user chooses their own email and password

### Cloud Deployment
- System is designed for shared cloud servers
- Multiple users can access from different computers
- All users share the same database in real-time
- Configure database host to point to cloud database

## Security Checklist

After installation:

- [ ] Delete `install_database.php`
- [ ] Delete `.installed` file (if exists)
- [ ] Set proper file permissions on `config.php` (644)
- [ ] Change default database credentials
- [ ] Enable HTTPS/SSL for production
- [ ] Set up regular database backups
- [ ] Review and configure `.env` file for production

## Troubleshooting

### "Database connection failed"
- Check database credentials in `config.php`
- Verify database server is running
- Check database user has proper permissions
- Verify database name is correct

### "Table doesn't exist"
- Run the database installation script
- Or import `gallium_loans.sql` manually

### "Access denied"
- Check user account is active
- Verify user type matches required role
- Check session is not expired

### Age validation error
- Ensure date of birth makes applicant 18+ years old
- Check date format is YYYY-MM-DD

### Date validation error
- Cannot select past dates
- Use today's date or future dates only

## Next Steps

1. Create additional user accounts (officers, customers)
2. Configure loan settings (interest rates, terms)
3. Start processing loan applications
4. Set up payment schedules
5. Generate reports

## Support

For issues:
1. Check error logs
2. Verify all requirements are met
3. Review this setup guide
4. Check database connection

---

**Version**: 1.0.0  
**Last Updated**: 2025


# Quick Start Guide - Gallium Solutions Limited Loan Management System

## Prerequisites
- WAMP Server installed and running
- PHP 7.4 or higher
- MySQL/MariaDB
- Web browser

---

## Step 1: Start WAMP Server

1. **Launch WAMP Server** from your desktop or Start menu
2. **Wait for the icon** in the system tray to turn **GREEN** (not orange or red)
3. If it's orange/red, click it and select "Start All Services"

---

## Step 2: Set Up the Database

### Option A: Using phpMyAdmin (Recommended)

1. **Open phpMyAdmin**:
   - Click the WAMP icon in system tray
   - Select "phpMyAdmin"
   - Or go to: `http://localhost/phpmyadmin`

2. **Create Database**:
   - Click "New" in the left sidebar
   - Database name: `gallium_loans`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Import Database Schema**:
   - Select the `gallium_loans` database
   - Click "Import" tab
   - Click "Choose File"
   - Select: `gallium_loans_localhost.sql` from your project folder
   - Click "Go" at the bottom
   - Wait for "Import has been successfully finished" message

### Option B: Using MySQL Command Line

1. Open Command Prompt
2. Navigate to your project folder:
   ```cmd
   cd C:\wamp64\www\Web\lb
   ```
3. Run MySQL import:
   ```cmd
   mysql -u root -p < gallium_loans_localhost.sql
   ```
   (Press Enter when prompted for password, or enter your MySQL root password)

---

## Step 3: Configure Database Connection

1. **Open** `config.php` in your project folder
2. **Verify** these settings (should work with default WAMP):
   ```php
   $host = 'localhost';
   $dbname = 'gallium_loans';
   $username = 'root';
   $password = '';  // Empty for default WAMP
   ```
3. **Save** the file

---

## Step 4: Access the Application

1. **Open your web browser**
2. **Navigate to**:
   ```
   http://localhost/Web/lb/
   ```
   Or:
   ```
   http://localhost/Web/lb/index.php
   ```

---

## Step 5: Create Your First Admin Account

1. **Click "Register"** or go to:
   ```
   http://localhost/Web/lb/register.php
   ```

2. **Fill in the registration form**:
   - **Full Name**: Your name
   - **Email**: Your email address
   - **Phone**: Your phone number (e.g., 0777123456)
   - **Password**: Create a password (minimum 6 characters)
   - **Confirm Password**: Re-enter your password
   - **Account Type**: Select **"Administrator"**

3. **Click "Register"**

4. You'll be redirected to the login page

---

## Step 6: Login

1. **Go to login page**:
   ```
   http://localhost/Web/lb/login.php
   ```

2. **Enter your credentials**:
   - Email: The email you used during registration
   - Password: Your password

3. **Click "Login"**

4. You'll be redirected to the **Admin Dashboard**

---

## Step 7: Start Using the System

### As Admin, you can now:

1. **Add Customers**:
   - Go to: Admin Dashboard → "Add Customer"
   - Fill in customer details
   - Click "Add Customer"

2. **Create Loans**:
   - Go to: Admin Dashboard → "Loans" → "Add New Loan"
   - Select a customer
   - Enter loan details

3. **View Reports**:
   - Go to: Admin Dashboard → "Reports"
   - View performance charts and analytics

4. **Manage Officers**:
   - Go to: Admin Dashboard → "Officers"
   - View and manage loan officers

---

## Quick Access URLs

- **Home Page**: `http://localhost/Web/lb/`
- **Login**: `http://localhost/Web/lb/login.php`
- **Register**: `http://localhost/Web/lb/register.php`
- **Admin Dashboard**: `http://localhost/Web/lb/admin/dashboard.php`
- **Check Database**: `http://localhost/Web/lb/check_database.php`

---

## Troubleshooting

### "Database connection failed"
- ✅ Check WAMP is running (green icon)
- ✅ Verify database `gallium_loans` exists in phpMyAdmin
- ✅ Check `config.php` has correct credentials
- ✅ Visit `http://localhost/Web/lb/check_database.php` for diagnostics

### "Table doesn't exist"
- ✅ Import `gallium_loans_localhost.sql` into the database
- ✅ Make sure you selected the correct database before importing

### "Access Denied" or "Forbidden"
- ✅ Check file permissions
- ✅ Verify WAMP is running
- ✅ Check Apache error logs in WAMP

### Page shows "404 Not Found"
- ✅ Verify the URL path is correct
- ✅ Check if the file exists in the folder
- ✅ Make sure WAMP is pointing to the correct directory

### Can't register / "Database error"
- ✅ Visit `http://localhost/Web/lb/check_database.php`
- ✅ Verify all tables exist
- ✅ Check database connection settings

---

## Next Steps

1. ✅ Create additional user accounts (officers, customers)
2. ✅ Add customers to the system
3. ✅ Create loan applications
4. ✅ Process payments
5. ✅ Generate reports

---

## Important Notes

- **Age Requirement**: All loan applicants must be 18+ years old
- **No Backdating**: Dates cannot be in the past
- **Self-Registration**: Users create their own accounts
- **Cloud Ready**: System can be deployed to cloud servers

---

## Need Help?

1. Check `SETUP_GUIDE.md` for detailed setup instructions
2. Visit `check_database.php` to diagnose database issues
3. Check WAMP error logs
4. Verify all files are in the correct directory

---

**System Version**: 1.0.0  
**Company**: Gallium Solutions Limited  
**Ready for**: Localhost & Cloud Deployment


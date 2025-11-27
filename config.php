<?php
// Gallium Solutions Limited - Cloud-Ready Database Configuration
// Supports environment variables for cloud deployment

// Get database credentials from environment variables or use defaults
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'gallium_loans';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

// For cloud deployment, you can also use a config file approach
// Uncomment and configure if using a separate config file:
/*
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $host = $env['DB_HOST'] ?? $host;
    $dbname = $env['DB_NAME'] ?? $dbname;
    $username = $env['DB_USER'] ?? $username;
    $password = $env['DB_PASS'] ?? $password;
}
*/

try {
    // Use UTF8MB4 for full Unicode support
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    // First connect without database to check connection
    $pdo_temp = new PDO($dsn, $username, $password, $options);
    
    // Try to select the database
    try {
        $pdo_temp->exec("USE `$dbname`");
        $pdo = $pdo_temp;
        
        // Set timezone
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Database doesn't exist, but connection works
        // This is okay for check_database.php
        if (basename($_SERVER['PHP_SELF']) === 'check_database.php') {
            $pdo = $pdo_temp;
        } else {
            throw new PDOException("Database '$dbname' does not exist. Please create it or import the schema.", 0, $e);
        }
    }
    
} catch (PDOException $e) {
    // Log error securely (don't expose credentials in production)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Only die if not on check_database page
    if (basename($_SERVER['PHP_SELF']) !== 'check_database.php') {
        // User-friendly error message
        if (getenv('APP_ENV') === 'production') {
            die("Database connection failed. Please contact the administrator.");
        } else {
            die("Database connection failed: " . $e->getMessage() . "<br><br><a href='check_database.php'>Check Database Connection</a>");
        }
    }
}

// Application constants
define('APP_NAME', 'Gallium Solutions Limited');
define('APP_VERSION', '1.0.0');
define('MIN_LOAN_AGE', 18); // Minimum age for loan eligibility
define('DEFAULT_INTEREST_RATE', 10.00); // Default interest rate percentage

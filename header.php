<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo defined('APP_NAME') ? APP_NAME : 'Loan Management System'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="index.php" class="logo">
                    <i class="fas fa-university"></i> <?php echo defined('APP_NAME') ? APP_NAME : 'Loan Management'; ?>
                </a>
                <ul class="nav-links">
                    <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                    <li><a href="loans.php" <?php echo basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'class="active"' : ''; ?>>Loans</a></li>
                    <li><a href="customers.php" <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'class="active"' : ''; ?>>Customers</a></li>
                    <li><a href="reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'class="active"' : ''; ?>>Reports</a></li>
                </ul>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="btn btn-login">Admin Panel</a>
                        <?php elseif (isOfficer()): ?>
                            <a href="officer/dashboard.php" class="btn btn-login">Officer Panel</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-register">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-login">Login</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="notification notification-success">
                <?php echo $_SESSION['success_message']; ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="notification notification-error">
                <?php echo $_SESSION['error_message']; ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
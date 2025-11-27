<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
        exit();
    } elseif (isOfficer()) {
        header("Location: officer/dashboard.php");
        exit();
    } elseif (isCustomer()) {
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Professional Loan Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="landing-container">
        <header class="landing-header">
            <div class="landing-nav">
                <a href="index.php" class="landing-logo">
                    <?php 
                    $logo_paths = ['image/GSL.png', 'assets/images/logo.png', 'assets/images/GSL.png', 'image/logo.png'];
                    $logo_path = null;
                    foreach ($logo_paths as $path) {
                        if (file_exists($path)) {
                            $logo_path = $path;
                            break;
                        }
                    }
                    if ($logo_path): ?>
                        <img src="<?php echo $logo_path; ?>" alt="<?php echo APP_NAME; ?> Logo" style="max-height: 50px; object-fit: contain;">
                    <?php else: ?>
                        <div class="landing-logo-text">
                            <span class="company-name"><?php echo APP_NAME; ?></span>
                            <span class="tagline">Professional Loan Management</span>
                        </div>
                    <?php endif; ?>
                </a>
                <div class="landing-auth-buttons">
                    <a href="login.php" class="btn-auth btn-auth-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn-auth btn-auth-register">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </div>
            </div>
        </header>
        
        <div class="landing-hero">
            <div class="hero-content">
                <div class="hero-logo">
                    <?php 
                    $logo_paths = ['image/GSL.png', 'assets/images/logo.png', 'assets/images/GSL.png', 'image/logo.png'];
                    $logo_path = null;
                    foreach ($logo_paths as $path) {
                        if (file_exists($path)) {
                            $logo_path = $path;
                            break;
                        }
                    }
                    if ($logo_path): ?>
                        <img src="<?php echo $logo_path; ?>" alt="<?php echo APP_NAME; ?> Logo" class="hero-logo-image">
                    <?php else: ?>
                        <i class="fas fa-university" style="font-size: 5rem; color: white; opacity: 0.9;"></i>
                    <?php endif; ?>
                </div>
                <h1><?php echo APP_NAME; ?></h1>
                <p class="subtitle">Professional Loan Management System</p>
                <p class="description">
                    Streamline your lending operations with our comprehensive loan management solution. 
                    Efficient processing, real-time tracking, and powerful analytics for better decision-making.
                </p>
                <div class="cta-buttons">
                    <a href="login.php" class="btn-large btn-primary-large">
                        <i class="fas fa-sign-in-alt"></i> Access System
                    </a>
                    <a href="register.php" class="btn-large btn-outline-large">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                </div>
            </div>
        </div>
        
        <section class="stats-section">
            <div class="stats-container">
                <div class="stat-item">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Secure & Reliable</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">24/7</div>
                    <div class="stat-label">System Availability</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">Real-time</div>
                    <div class="stat-label">Data Synchronization</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">Cloud</div>
                    <div class="stat-label">Based Platform</div>
                </div>
            </div>
        </section>
        
        <section class="features">
            <div class="features-container">
                <div class="features-header">
                    <h2>Why Choose Our System?</h2>
                    <p>Comprehensive features designed for modern loan management</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-file-invoice-dollar feature-icon"></i>
                        <h3>Loan Management</h3>
                        <p>Complete loan lifecycle management from application to repayment with automated calculations and scheduling</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-users feature-icon"></i>
                        <h3>Customer Management</h3>
                        <p>Comprehensive customer database with complete loan history, documents, and performance tracking</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <h3>Reports & Analytics</h3>
                        <p>Detailed reports and analytics with visual charts to track performance, collections, and business insights</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-shield-alt feature-icon"></i>
                        <h3>Secure & Reliable</h3>
                        <p>Role-based access control with admin and officer permissions, ensuring data security and compliance</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-cloud feature-icon"></i>
                        <h3>Cloud-Based</h3>
                        <p>Access your system from anywhere, anytime. Real-time data synchronization across multiple devices</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-mobile-alt feature-icon"></i>
                        <h3>Responsive Design</h3>
                        <p>Fully responsive interface that works seamlessly on desktop, tablet, and mobile devices</p>
                    </div>
                </div>
            </div>
        </section>
        
        <section style="background: #f9fafb; padding: 60px 20px; border-top: 1px solid #e5e7eb;">
            <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
                <h2 style="color: #1e3a8a; margin-bottom: 10px; font-size: 28px;">Staff Access</h2>
                <p style="color: #6b7280; margin-bottom: 40px; font-size: 16px;">Administrators and Loan Officers have dedicated login portals</p>
                <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap;">
                    <a href="admin/login.php" style="display: inline-block; padding: 20px 40px; background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);">
                        <i class="fas fa-shield-alt" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                        <strong style="display: block; font-size: 18px; margin-bottom: 5px;">Administrator Login</strong>
                        <span style="font-size: 14px; opacity: 0.9;">Admin Access Only</span>
                    </a>
                    <a href="officer/login.php" style="display: inline-block; padding: 20px 40px; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);">
                        <i class="fas fa-user-tie" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                        <strong style="display: block; font-size: 18px; margin-bottom: 5px;">Officer Login</strong>
                        <span style="font-size: 14px; opacity: 0.9;">Loan Officer Access</span>
                    </a>
                </div>
            </div>
        </section>
        
        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>

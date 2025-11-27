<?php
// Standardized Sidebar Template
// This ensures consistent sidebar structure across all pages

// Get current theme if not already set
if (!isset($current_theme)) {
    if (isset($_SESSION['theme'])) {
        $current_theme = $_SESSION['theme'];
    } else {
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../functions.php';
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $theme = $stmt->fetchColumn();
                $current_theme = $theme ?: 'light';
            } catch (PDOException $e) {
                $current_theme = 'light';
            }
        } else {
            $current_theme = 'light';
        }
    }
}

$is_admin = ($user_type === 'admin');
$is_officer = ($user_type === 'officer');
$is_customer = ($user_type === 'customer');

// Get unread notification count for officers if not provided
if ($is_officer && !isset($unread_count)) {
    $unread_count = 0;
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $unread_count = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            $unread_count = 0;
        }
    }
}
?>

<!-- Toggle button that appears when sidebar is collapsed (outside sidebar) -->
<button class="sidebar-toggle-collapsed" onclick="toggleSidebar()" aria-label="Toggle Sidebar" title="Toggle Sidebar" style="display: none;">
    <i class="fas fa-bars"></i>
</button>

<div class="<?php echo $is_admin ? 'admin-sidebar' : ($is_officer ? 'officer-sidebar' : 'sidebar'); ?>">
    <div class="<?php echo $is_admin ? 'admin-logo' : ($is_officer ? 'officer-logo' : 'sidebar-logo'); ?>">
        <div class="sidebar-controls" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div style="font-weight: 700; font-size: var(--font-size-lg); color: var(--white);">
                <?php echo $is_admin ? 'Admin' : ($is_officer ? 'Officer' : 'Dashboard'); ?>
            </div>
            <div style="display: flex; gap: var(--spacing-sm);">
                <button onclick="toggleTheme()" class="sidebar-theme-toggle" title="Toggle Theme">
                    <i class="fas fa-<?php echo isset($current_theme) && $current_theme === 'dark' ? 'sun' : 'moon'; ?>"></i>
                </button>
                <button class="sidebar-toggle-simple" onclick="toggleSidebar()" aria-label="Toggle Sidebar" title="Toggle Sidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="sidebar-divider"></div>
    
    <ul class="<?php echo $is_admin ? 'admin-nav' : ($is_officer ? 'officer-nav' : 'sidebar-nav'); ?>">
        <?php if ($is_admin || $is_officer): ?>
            <li><a href="<?php echo $base_path; ?><?php echo $is_admin ? 'admin/' : 'officer/'; ?>dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="<?php echo $base_path; ?><?php echo $is_admin ? 'admin/' : 'officer/'; ?>customers.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'customers.php' || basename($_SERVER['PHP_SELF']) == 'customer_account.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Customers</a></li>
            <li><a href="<?php echo $base_path; ?><?php echo $is_admin ? 'admin/' : 'officer/'; ?>add_customer.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'add_customer.php') ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Add Customer</a></li>
            <?php if ($is_admin): ?>
                <li><a href="<?php echo $base_path; ?>admin/officers.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'officers.php') ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Officers</a></li>
                <li><a href="<?php echo $base_path; ?>admin/send_notification.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'send_notification.php') ? 'active' : ''; ?>"><i class="fas fa-paper-plane"></i> Send Notification</a></li>
            <?php endif; ?>
            <li><a href="<?php echo $base_path; ?><?php echo $is_admin ? 'admin/' : 'officer/'; ?>loans.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'loans.php') ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Loans</a></li>
            <li><a href="<?php echo $base_path; ?><?php echo $is_admin ? 'admin/' : 'officer/'; ?>payments.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
            <?php if ($is_admin): ?>
                <li><a href="<?php echo $base_path; ?>admin/reports.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <?php endif; ?>
            <?php if ($is_officer): ?>
                <li><a href="<?php echo $base_path; ?>officer/notifications.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Notifications <?php if (isset($unread_count) && $unread_count > 0): ?><span class="notification-badge"><?php echo $unread_count; ?></span><?php endif; ?></a></li>
                <li><a href="<?php echo $base_path; ?>officer/change_password.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'change_password.php') ? 'active' : ''; ?>"><i class="fas fa-key"></i> Change Password</a></li>
            <?php endif; ?>
        <?php elseif ($is_customer): ?>
            <li><a href="dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="apply_loan.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'apply_loan.php') ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Apply for Loan</a></li>
            <li><a href="my_loans.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_loans.php') ? 'active' : ''; ?>"><i class="fas fa-list"></i> My Loans</a></li>
            <li><a href="payments.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
            <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> Profile</a></li>
            <li><a href="notifications.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Notifications</a></li>
        <?php endif; ?>
        <li><a href="<?php echo $base_path; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="sidebar-footer-info">
            <strong><?php echo $app_name; ?></strong>
            <span class="version">v<?php echo $app_version; ?></span>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const containerClass = '.<?php echo $is_admin ? 'admin' : ($is_officer ? 'officer' : 'dashboard'); ?>-container';
    const storageKey = '<?php echo $is_admin ? 'sidebarCollapsed' : ($is_officer ? 'officerSidebarCollapsed' : 'customerSidebarCollapsed'); ?>';
    
    // Define toggleSidebar globally
    function toggleSidebarFunction() {
        const container = document.querySelector(containerClass);
        if (!container) {
            console.error('Container not found:', containerClass);
            return;
        }
        
        const isCollapsed = container.classList.contains('sidebar-collapsed');
        
        if (isCollapsed) {
            container.classList.remove('sidebar-collapsed');
        } else {
            container.classList.add('sidebar-collapsed');
        }
        
        localStorage.setItem(storageKey, container.classList.contains('sidebar-collapsed') ? 'true' : 'false');
        
        // Force a reflow to ensure CSS changes apply
        void container.offsetWidth;
        
        // Update icons
        const simpleIcon = document.querySelector('.sidebar-toggle-simple i');
        if (simpleIcon) {
            if (container.classList.contains('sidebar-collapsed')) {
                simpleIcon.className = 'fas fa-chevron-right';
            } else {
                simpleIcon.className = 'fas fa-chevron-left';
            }
        }
        
        // Show/hide the collapsed toggle button
        const collapsedToggle = document.querySelector('.sidebar-toggle-collapsed');
        if (collapsedToggle) {
            if (container.classList.contains('sidebar-collapsed')) {
                collapsedToggle.style.display = 'flex';
            } else {
                collapsedToggle.style.display = 'none';
            }
        }
    }
    
    // Assign to window for global access
    window.toggleSidebar = toggleSidebarFunction;
    
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector(containerClass);
        if (container) {
            const isCollapsed = localStorage.getItem(storageKey) === 'true';
            const collapsedToggle = document.querySelector('.sidebar-toggle-collapsed');
            
            if (isCollapsed) {
                container.classList.add('sidebar-collapsed');
                const simpleIcon = document.querySelector('.sidebar-toggle-simple i');
                if (simpleIcon) {
                    simpleIcon.className = 'fas fa-chevron-right';
                }
                if (collapsedToggle) {
                    collapsedToggle.style.display = 'flex';
                }
            } else {
                const simpleIcon = document.querySelector('.sidebar-toggle-simple i');
                if (simpleIcon) {
                    simpleIcon.className = 'fas fa-chevron-left';
                }
                if (collapsedToggle) {
                    collapsedToggle.style.display = 'none';
                }
            }
        }
    });
})();

// Theme toggle function
window.toggleTheme = function() {
    const currentPath = window.location.pathname + window.location.search;
    window.location.href = '<?php echo $base_path; ?>toggle_theme.php?redirect=' + encodeURIComponent(currentPath);
};
</script>

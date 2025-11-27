<?php
if (!isset($user_id)) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

if ($user_id) {
    $user = getUserById($user_id);
    // Check session first (for immediate updates), then database, then default
    if (isset($_SESSION['theme'])) {
        $current_theme = $_SESSION['theme'];
    } elseif ($user && isset($user['theme_preference'])) {
        $current_theme = $user['theme_preference'];
        $_SESSION['theme'] = $current_theme; // Sync to session
    } else {
        $current_theme = 'light';
        $_SESSION['theme'] = 'light';
    }
} else {
    $current_theme = $_SESSION['theme'] ?? 'light';
    $_SESSION['theme'] = $current_theme;
}
?>

<style>
    <?php if ($current_theme === 'dark'): ?>
    /* ============================================
       COMPREHENSIVE DARK MODE STYLES
       Maintains layout, changes only colors
       ============================================ */
    
    /* Base Elements */
    body.theme-dark,
    body[class*="theme-dark"] {
        background: #0f172a !important;
        color: #f1f5f9 !important;
    }
    
    /* Override wildcard border - be more specific */
    body.theme-dark *:not(.btn-primary):not(.btn-secondary):not(.btn-register) {
        border-color: #334155 !important;
    }
    
    /* Headings */
    body.theme-dark h1,
    body.theme-dark h2,
    body.theme-dark h3,
    body.theme-dark h4,
    body.theme-dark h5,
    body.theme-dark h6 {
        color: #f1f5f9 !important;
    }
    
    /* Paragraphs and Text */
    body.theme-dark p,
    body.theme-dark span,
    body.theme-dark div,
    body.theme-dark label,
    body.theme-dark small {
        color: #cbd5e1 !important;
    }
    
    /* Containers */
    body.theme-dark .admin-container,
    body.theme-dark .officer-container,
    body.theme-dark .dashboard-container {
        background: #0f172a !important;
    }
    
    /* Sidebars - Keep readable in dark mode with same blue gradient */
    body.theme-dark .admin-sidebar,
    body.theme-dark .officer-sidebar,
    body.theme-dark .sidebar {
        background: linear-gradient(180deg, #1e3a8a 0%, #0d1b3d 100%) !important;
        border-right-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Sidebar Logo */
    body.theme-dark .admin-logo,
    body.theme-dark .officer-logo,
    body.theme-dark .sidebar-logo {
        color: #ffffff !important;
        border-bottom-color: rgba(255, 255, 255, 0.2) !important;
    }
    
    /* Sidebar Navigation Links */
    body.theme-dark .admin-nav a,
    body.theme-dark .officer-nav a,
    body.theme-dark .sidebar-nav a {
        color: rgba(255, 255, 255, 0.9) !important;
    }
    
    body.theme-dark .admin-nav a:hover,
    body.theme-dark .officer-nav a:hover,
    body.theme-dark .sidebar-nav a:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }
    
    body.theme-dark .admin-nav a.active,
    body.theme-dark .officer-nav a.active,
    body.theme-dark .sidebar-nav a.active {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
    }
    
    /* Sidebar Footer */
    body.theme-dark .sidebar-footer {
        border-top-color: rgba(255, 255, 255, 0.2) !important;
        background: rgba(0, 0, 0, 0.15) !important;
    }
    
    body.theme-dark .sidebar-footer-info {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    body.theme-dark .sidebar-footer-info strong {
        color: #ffffff !important;
    }
    
    /* Sidebar Icons */
    body.theme-dark .admin-sidebar .fas,
    body.theme-dark .admin-sidebar .far,
    body.theme-dark .officer-sidebar .fas,
    body.theme-dark .officer-sidebar .far,
    body.theme-dark .sidebar .fas,
    body.theme-dark .sidebar .far {
        color: rgba(255, 255, 255, 0.9) !important;
    }
    
    /* Main Content Areas */
    body.theme-dark .admin-main,
    body.theme-dark .officer-main,
    body.theme-dark .main-content {
        background: #0f172a !important;
        color: #f1f5f9 !important;
    }
    
    /* Cards */
    body.theme-dark .card,
    body.theme-dark .stat-card,
    body.theme-dark .performance-card,
    body.theme-dark .kpi-card-reports,
    body.theme-dark .feature-card {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .card-header {
        background: #0f172a !important;
        color: #f1f5f9 !important;
        border-bottom-color: #334155 !important;
    }
    
    body.theme-dark .card-title,
    body.theme-dark .card-title h2,
    body.theme-dark .card-title h3 {
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .kpi-card-reports .kpi-value {
        color: #f1f5f9 !important;
        font-weight: 700 !important;
    }
    
    body.theme-dark .kpi-card-reports .kpi-label {
        color: #94a3b8 !important;
    }
    
    body.theme-dark .chart-panel-reports {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .chart-panel-reports h3 {
        color: #f1f5f9 !important;
    }
    
    /* Page Headers */
    body.theme-dark .page-header,
    body.theme-dark .account-header {
        border-bottom-color: #334155 !important;
    }
    
    body.theme-dark .page-header h1,
    body.theme-dark .page-header h2,
    body.theme-dark .page-header p {
        color: #f1f5f9 !important;
    }
    
    /* Forms */
    body.theme-dark .form-control,
    body.theme-dark input[type="text"],
    body.theme-dark input[type="email"],
    body.theme-dark input[type="tel"],
    body.theme-dark input[type="number"],
    body.theme-dark input[type="date"],
    body.theme-dark input[type="password"],
    body.theme-dark textarea,
    body.theme-dark select {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #475569 !important;
    }
    
    body.theme-dark .form-control:focus,
    body.theme-dark input:focus,
    body.theme-dark textarea:focus,
    body.theme-dark select:focus {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }
    
    body.theme-dark .form-label,
    body.theme-dark label {
        color: #cbd5e1 !important;
    }
    
    /* Tables */
    body.theme-dark .table,
    body.theme-dark .booking-table {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .table th,
    body.theme-dark .booking-table th {
        background: #0f172a !important;
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .table td,
    body.theme-dark .booking-table td {
        color: #cbd5e1 !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .table tr:hover,
    body.theme-dark .booking-table tr:hover {
        background: #334155 !important;
    }
    
    /* Buttons */
    body.theme-dark .btn-outline {
        border-color: #475569 !important;
        color: #f1f5f9 !important;
        background: transparent !important;
    }
    
    body.theme-dark .btn-outline:hover {
        background: #334155 !important;
        color: #f1f5f9 !important;
        border-color: #475569 !important;
    }
    
    body.theme-dark .btn-primary {
        background: #1e3a8a !important;
        color: #f1f5f9 !important;
        border-color: #1e3a8a !important;
    }
    
    body.theme-dark .btn-primary:hover {
        background: #1e40af !important;
        border-color: #1e40af !important;
    }
    
    /* Alerts */
    body.theme-dark .alert {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .alert-success {
        background: #1e3a2e !important;
        border-color: #10b981 !important;
        color: #6ee7b7 !important;
    }
    
    body.theme-dark .alert-danger {
        background: #3a1e1e !important;
        border-color: #ef4444 !important;
        color: #fca5a5 !important;
    }
    
    body.theme-dark .alert-warning {
        background: #3a2e1e !important;
        border-color: #f59e0b !important;
        color: #fcd34d !important;
    }
    
    body.theme-dark .alert-info {
        background: #1e293b !important;
        border-color: #3b82f6 !important;
        color: #93c5fd !important;
    }
    
    
    /* Stats and Values */
    body.theme-dark .stat-card .value,
    body.theme-dark .stat-card-value,
    body.theme-dark .value {
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .stat-card .change,
    body.theme-dark .stat-card-label,
    body.theme-dark .stat-card-description {
        color: #94a3b8 !important;
    }
    
    /* Links */
    body.theme-dark a {
        color: #60a5fa !important;
    }
    
    body.theme-dark a:hover {
        color: #93c5fd !important;
    }
    
    /* Status Badges */
    body.theme-dark .status-pending,
    body.theme-dark .status-approved,
    body.theme-dark .status-active,
    body.theme-dark .status-completed,
    body.theme-dark .status-rejected {
        color: #f1f5f9 !important;
    }
    
    
    /* Toggle Button */
    body.theme-dark .sidebar-toggle {
        background: #1e3a8a !important;
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .sidebar-toggle:hover {
        background: #1e40af !important;
    }
    
    /* Theme Toggle Button */
    body.theme-dark .theme-toggle-btn {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .theme-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.2) !important;
    }
    
    /* Charts Container */
    body.theme-dark .chart-container {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .chart-container h2 {
        color: #f1f5f9 !important;
    }
    
    /* Recent Bookings/Sections */
    body.theme-dark .recent-bookings,
    body.theme-dark .section {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .recent-bookings h2,
    body.theme-dark .section h2 {
        color: #f1f5f9 !important;
    }
    
    /* Search and Filter */
    body.theme-dark .search-box,
    body.theme-dark .filter-box {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f1f5f9 !important;
    }
    
    /* Modals and Overlays */
    body.theme-dark .modal,
    body.theme-dark .modal-content {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .modal-header {
        border-bottom-color: #334155 !important;
    }
    
    body.theme-dark .modal-footer {
        border-top-color: #334155 !important;
    }
    
    /* Empty States */
    body.theme-dark .empty-state {
        color: #94a3b8 !important;
    }
    
    /* Icons - maintain visibility */
    body.theme-dark .fas,
    body.theme-dark .far,
    body.theme-dark .fab {
        color: inherit !important;
    }
    
    /* Inline Styles Override - for elements with style attributes */
    body.theme-dark [style*="color"] {
        /* Preserve important colors but adjust others */
    }
    
    /* Ensure white backgrounds become dark */
    body.theme-dark [style*="background: white"],
    body.theme-dark [style*="background:#fff"],
    body.theme-dark [style*="background: #fff"],
    body.theme-dark [style*="background-color: white"],
    body.theme-dark [style*="background-color:#fff"],
    body.theme-dark [style*="background-color: #fff"] {
        background: #1e293b !important;
    }
    
    /* Ensure light gray backgrounds become dark */
    body.theme-dark [style*="background: #f"],
    body.theme-dark [style*="background-color: #f"] {
        background: #1e293b !important;
    }
    
    /* Text colors in inline styles */
    body.theme-dark [style*="color: #6b7280"],
    body.theme-dark [style*="color:#6b7280"],
    body.theme-dark [style*="color: #4b5563"],
    body.theme-dark [style*="color:#4b5563"] {
        color: #cbd5e1 !important;
    }
    
    /* Grid and Layout - maintain structure */
    body.theme-dark .stats-grid,
    body.theme-dark .features-grid {
        /* Layout unchanged, only colors change */
    }
    
    /* Reports Page Specific */
    body.theme-dark .kpi-card-reports {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .kpi-card-reports .kpi-value {
        color: #f1f5f9 !important;
        font-weight: 700 !important;
        font-size: 1.5rem !important;
    }
    
    body.theme-dark .kpi-card-reports .kpi-label {
        color: #94a3b8 !important;
        font-size: 0.875rem !important;
    }
    
    body.theme-dark .chart-panel-reports {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .chart-panel-reports h3 {
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .chart-panel-reports .chart-header {
        background: #0f172a !important;
        color: #f1f5f9 !important;
        border-bottom-color: #334155 !important;
    }
    
    /* Officer Loan Stats in Dark Theme */
    body.theme-dark .officer-loan-stats .loan-stat-item {
        background: #0f172a !important;
        border-color: #334155 !important;
    }
    
    body.theme-dark .officer-loan-stats .stat-value {
        color: #f1f5f9 !important;
    }
    
    body.theme-dark .officer-loan-stats .stat-label {
        color: #94a3b8 !important;
    }
    
    /* Customer Account Page */
    body.theme-dark .account-header {
        border-bottom-color: #334155 !important;
    }
    
    /* Ensure all text remains visible */
    body.theme-dark strong,
    body.theme-dark b {
        color: #f1f5f9 !important;
    }
    
    body.theme-dark em,
    body.theme-dark i {
        color: inherit !important;
    }
    
    /* Lists */
    body.theme-dark ul,
    body.theme-dark ol {
        color: #cbd5e1 !important;
    }
    
    body.theme-dark li {
        color: #cbd5e1 !important;
    }
    
    /* Dropdowns */
    body.theme-dark select option {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    
    /* Placeholders */
    body.theme-dark ::placeholder {
        color: #64748b !important;
        opacity: 0.7;
    }
    
    body.theme-dark ::-webkit-input-placeholder {
        color: #64748b !important;
        opacity: 0.7;
    }
    
    body.theme-dark ::-moz-placeholder {
        color: #64748b !important;
        opacity: 0.7;
    }
    
    body.theme-dark :-ms-input-placeholder {
        color: #64748b !important;
        opacity: 0.7;
    }
    
    /* Cards */
    body.theme-dark .card {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    body.theme-dark .card-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
        border-bottom-color: #334155 !important;
    }
    body.theme-dark .card-title {
        color: #f1f5f9 !important;
    }
    body.theme-dark .card-body {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    
    /* Chart Wrapper */
    body.theme-dark .chart-wrapper {
        background: #1e293b !important;
    }
    
    /* Tabs */
    body.theme-dark .tab-container {
        background: #1e293b !important;
        border-bottom-color: #334155 !important;
    }
    body.theme-dark .tab-button {
        color: #cbd5e1 !important;
    }
    body.theme-dark .tab-button:hover {
        background: #334155 !important;
        color: #f1f5f9 !important;
    }
    body.theme-dark .tab-button.active {
        color: #93c5fd !important;
        border-bottom-color: #3b82f6 !important;
        background: #334155 !important;
    }
    body.theme-dark .tab-content {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    
    /* Empty State */
    body.theme-dark .empty-state {
        color: #cbd5e1 !important;
    }
    body.theme-dark .empty-state i {
        color: #475569 !important;
    }
    
    /* Modern Customer/Officer Cards */
    body.theme-dark .customer-card-modern,
    body.theme-dark .officer-card-modern {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    body.theme-dark .customer-card-header-modern,
    body.theme-dark .officer-card-header-modern {
        background: linear-gradient(135deg, #1e3a8a 0%, #0d1b3d 100%) !important;
    }
    body.theme-dark .customer-card-body-modern,
    body.theme-dark .officer-card-body-modern {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    body.theme-dark .customer-contact-info,
    body.theme-dark .officer-contact-info {
        color: #cbd5e1 !important;
    }
    body.theme-dark .contact-item {
        color: #cbd5e1 !important;
    }
    body.theme-dark .customer-employment,
    body.theme-dark .customer-loan-stats,
    body.theme-dark .officer-loan-stats {
        background: #0f172a !important;
    }
    body.theme-dark .employment-item .value {
        color: #f1f5f9 !important;
    }
    body.theme-dark .customer-card-footer-modern,
    body.theme-dark .officer-card-footer-modern {
        background: #0f172a !important;
        border-top-color: #334155 !important;
    }
    body.theme-dark .loan-stat-item .stat-value {
        color: #93c5fd !important;
    }
    body.theme-dark .loan-stat-item .stat-label {
        color: #94a3b8 !important;
    }
    <?php endif; ?>
</style>

<script>
    window.toggleTheme = function() {
        const currentTheme = document.body.classList.contains('theme-dark') ? 'dark' : 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        let basePath = '';
        if (window.location.pathname.includes('/admin/')) {
            basePath = '../';
        } else if (window.location.pathname.includes('/officer/')) {
            basePath = '../';
        }
        
        fetch(basePath + 'toggle_theme.php?theme=' + newTheme)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    console.error('Theme toggle failed:', data.message);
                    alert('Failed to change theme: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Theme toggle error:', error);
                alert('Error changing theme. Please try again.');
            });
    };
</script>

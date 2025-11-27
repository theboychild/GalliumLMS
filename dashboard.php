<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$user_id = $_SESSION['user_id'];

// Loan officer statistics
$total_loans = 0;
$pending_loans = 0;
$approved_loans = 0;
$total_collections = 0;

try {
    $total_loans = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE officer_id = ?");
    $total_loans->execute([$user_id]);
    $total_loans = $total_loans->fetchColumn() ?: 0;
    
    $pending_loans = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE officer_id = ? AND status = 'pending'");
    $pending_loans->execute([$user_id]);
    $pending_loans = $pending_loans->fetchColumn() ?: 0;
    
    $approved_loans = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE officer_id = ? AND status = 'approved'");
    $approved_loans->execute([$user_id]);
    $approved_loans = $approved_loans->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Tables don't exist yet
}

// Get recent loans assigned to this officer
$recent_loans = [];
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.customer_name, c.phone
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.officer_id = ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet
}

$user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

$notifications = getUserNotifications($user_id, 5);
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <?php include '../includes/theme_support.php'; ?>
</head>
<body class="theme-<?php echo $current_theme; ?>">
    <div class="officer-container">
        <?php 
        $user_type = 'officer';
        $app_name = APP_NAME;
        $app_version = APP_VERSION;
        $base_path = '../';
        $unread_count = $unread_count ?? 0;
        include '../includes/sidebar_template.php'; 
        ?>
        
        <div class="officer-main">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-tachometer-alt"></i> Welcome, <?php echo htmlspecialchars($user_info['username']); ?>!</h1>
                    <p>Loan Officer Dashboard - Manage your assigned loans and customers</p>
                </div>
                <?php if ($unread_count > 0): ?>
                    <div style="margin-top: var(--spacing-md);">
                        <a href="notifications.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-bell"></i>
                            <span><?php echo $unread_count; ?> New</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Loans</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($total_loans); ?></div>
                    <div class="change">Loans assigned to you</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Pending Loans</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($pending_loans); ?></div>
                    <div class="change">Awaiting review</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Approved Loans</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($approved_loans); ?></div>
                    <div class="change">Successfully approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Collections</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($total_collections); ?></div>
                    <div class="change">Total collected</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Loans</h2>
                </div>
                <div class="card-body">
                <?php if (empty($recent_loans)): ?>
                    <p style="text-align: center; padding: 2rem; color: #6b7280;">No loans found. Loans assigned to you will appear here once the loans table is created.</p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_loans as $loan): ?>
                            <tr>
                                <td>#<?php echo $loan['loan_id']; ?></td>
                                <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                <td>UGX <?php echo number_format(round($loan['loan_amount']), 0); ?></td>
                                <td><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                                <td class="status-<?php echo $loan['status']; ?>">
                                    <?php echo ucfirst($loan['status']); ?>
                                </td>
                                <td>
                                    <a href="loans.php?view=<?php echo $loan['loan_id']; ?>" class="btn btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Ensure toggle functions are available
        document.addEventListener('DOMContentLoaded', function() {
            // Verify sidebar toggle is available
            if (typeof toggleSidebar === 'undefined') {
                console.error('toggleSidebar function not found');
            }
            
            // Verify theme toggle is available
            if (typeof toggleTheme === 'undefined') {
                console.error('toggleTheme function not found');
            }
            
            // Force re-initialization if needed
            const container = document.querySelector('.officer-container');
            if (container) {
                const isCollapsed = localStorage.getItem('officerSidebarCollapsed') === 'true';
                if (isCollapsed) {
                    container.classList.add('sidebar-collapsed');
                }
            }
        });
    </script>
</body>
</html>


<?php

session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$user_id = $_SESSION['user_id'];

// Loan management statistics using functions
$stats = getLoanStatistics();
$total_loans = $stats['total_loans'];
$total_customers = 0;
try {
    $total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Table doesn't exist yet
}
$total_officers = 0;
try {
    $total_officers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'officer'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Table doesn't exist yet
}
$total_pending = $stats['pending_loans'];

// Get pending password reset requests
$password_reset_requests = [];
$pending_password_requests_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT prr.*, u.username, u.email, u.user_type
        FROM password_reset_requests prr
        JOIN users u ON prr.user_id = u.user_id
        WHERE prr.status = 'pending'
        ORDER BY prr.requested_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $password_reset_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_password_requests_count = count($password_reset_requests);
} catch (PDOException $e) {
    // Table might not exist
}

// Get recent loans (if loans table exists, otherwise show placeholder)
$recent_loans = [];
try {
    $recent_loans = $pdo->query("
        SELECT l.*, c.customer_name, c.phone
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        ORDER BY l.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet, will be empty array
}

// Revenue/Collection data
$collection_data = [];
try {
    $collection_data = $pdo->query("
        SELECT DATE(payment_date) as date, SUM(payment_amount) as daily_collection
        FROM loan_payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet
}

// Get unread notifications for display
$unread_notifications = [];
$unread_count = 0;
try {
    $unread_notifications = getUserNotifications($user_id, true); // Get only unread
    $unread_count = count($unread_notifications);
} catch (PDOException $e) {
    error_log("Failed to fetch notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <?php include '../includes/theme_support.php'; ?>
</head>
<body class="theme-<?php echo $current_theme; ?>">
    <div class="admin-container">
        <?php 
        $user_type = 'admin';
        $app_name = APP_NAME;
        $app_version = APP_VERSION;
        $base_path = '../';
        include '../includes/sidebar_template.php'; 
        ?>
        
        <div class="admin-main">
            <div class="page-header">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p>Overview of loan management system</p>
                </div>
            </div>
            
            <?php if ($unread_count > 0): ?>
            <div id="notification-toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 400px;">
                <?php foreach (array_slice($unread_notifications, 0, 5) as $notification): ?>
                <div class="notification-toast" style="background: var(--light-gray); border-left: 4px solid var(--brand-blue); padding: var(--spacing-md); margin-bottom: var(--spacing-sm); border-radius: var(--radius-md); box-shadow: var(--shadow-md); animation: slideIn 0.3s ease-out;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong style="color: var(--brand-blue); display: block; margin-bottom: var(--spacing-xs);">
                                <i class="fas fa-bell"></i> <?php echo htmlspecialchars($notification['title']); ?>
                            </strong>
                            <p style="color: var(--dark-gray); font-size: 0.9rem; margin: 0;"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small style="color: var(--medium-gray); display: block; margin-top: var(--spacing-xs);">
                                <?php echo formatDate($notification['created_at']); ?>
                            </small>
                        </div>
                        <button onclick="dismissNotification(<?php echo $notification['notification_id']; ?>, this)" style="background: none; border: none; color: var(--medium-gray); cursor: pointer; padding: 0; margin-left: var(--spacing-sm); font-size: 1.2rem;">&times;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h3>Total Loans</h3>
                    <div class="value"><?php echo number_format($total_loans); ?></div>
                    <div class="change">Active loans in system</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Customers</h3>
                    <div class="value"><?php echo number_format($total_customers); ?></div>
                    <div class="change">Registered customers</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-tie"></i>
                    <h3>Loan Officers</h3>
                    <div class="value"><?php echo number_format($total_officers); ?></div>
                    <div class="change">Active officers</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3>Pending Loans</h3>
                    <div class="value"><?php echo number_format($total_pending); ?></div>
                    <div class="change">Awaiting approval</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-bar"></i> Collections (Last 7 Days)</h2>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="collectionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <?php if ($pending_password_requests_count > 0): ?>
            <div class="card" style="border-left: 4px solid var(--warning-yellow);">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-key"></i> Password Reset Requests 
                        <span class="badge status-pending" style="margin-left: var(--spacing-sm);"><?php echo $pending_password_requests_count; ?> Pending</span>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Officer</th>
                                    <th>Email</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($password_reset_requests as $request): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($request['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?></td>
                                        <td>
                                            <a href="officers.php?generate_code=1&id=<?php echo $request['user_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-key"></i> Generate Code
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($password_reset_requests) >= 5): ?>
                        <div style="text-align: center; margin-top: var(--spacing-md);">
                            <a href="officers.php" class="btn btn-outline">View All Officers</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-list"></i> Recent Loans</h2>
                </div>
                <div class="card-body">
                <?php if (empty($recent_loans)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No loans found. Loans will appear here once the loans table is created.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
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
                                    <a href="loans.php?view=<?php echo $loan['loan_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
    // Collection graph
    const collectionCtx = document.getElementById('collectionChart');
    if (collectionCtx) {
        const collectionChart = new Chart(collectionCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php echo !empty($collection_data) ? implode(',', array_map(function($item) { return "'" . date('M j', strtotime($item['date'])) . "'"; }, $collection_data)) : "'No Data'"; ?>],
                datasets: [{
                    label: 'Daily Collections (UGX)',
                    data: [<?php echo !empty($collection_data) ? implode(',', array_column($collection_data, 'daily_collection')) : '0'; ?>],
                    backgroundColor: 'rgba(30, 58, 138, 0.7)',
                    borderColor: 'rgba(30, 58, 138, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2.5,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'UGX ' + (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return 'UGX ' + (value / 1000).toFixed(1) + 'K';
                                }
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        }
        </script>
        
        <script>
        // Notification dismissal
        function dismissNotification(notificationId, button) {
            fetch('../api/dismiss_notification.php?id=' + notificationId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.closest('.notification-toast').style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => {
                        button.closest('.notification-toast').remove();
                    }, 300);
                }
            })
            .catch(error => {
                console.error('Error dismissing notification:', error);
                button.closest('.notification-toast').remove();
            });
        }
        
        // Auto-dismiss notifications after 10 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.notification-toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }, 10000);
            });
        });
        </script>
        
        <style>
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        </style>
</body>
</html>
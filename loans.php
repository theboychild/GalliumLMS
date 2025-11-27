<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$is_admin = false;
$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;
$success_message = '';

// Officers cannot approve/reject/disburse loans - only admins can
// Removed loan action handlers for officers

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = "l.officer_id = ?";
$params = [$user_id];

if ($status_filter !== 'all') {
    $where .= " AND l.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where .= " AND (c.customer_name LIKE ? OR l.loan_id = ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    if (is_numeric($search)) {
        $params[] = intval($search);
    } else {
        $params[] = 0;
    }
}

// Get loans - handle case where loan_term_type might not exist
// First check if the column exists
$check_column = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
if ($check_column) {
    // Column exists, use full query
    $sql = "
        SELECT l.*, c.customer_name, c.phone, c.email, u.username as officer_name,
               CASE 
                   WHEN l.loan_term_type = 'weeks' THEN CONCAT(l.loan_term_weeks, ' weeks')
                   ELSE CONCAT(COALESCE(l.loan_term_months, 0), ' months')
               END as term_display
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        LEFT JOIN users u ON l.officer_id = u.user_id
        WHERE $where
        ORDER BY l.created_at DESC
        LIMIT 100
    ";
} else {
    // Column doesn't exist, use fallback
    $sql = "
        SELECT l.*, c.customer_name, c.phone, c.email, u.username as officer_name,
               CONCAT(COALESCE(l.loan_term_months, 0), ' months') as term_display
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        LEFT JOIN users u ON l.officer_id = u.user_id
        WHERE $where
        ORDER BY l.created_at DESC
        LIMIT 100
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-file-invoice-dollar"></i> My Loans</h1>
                    <p>View and manage loans assigned to you</p>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Customer name or Loan ID" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="defaulted" <?php echo $status_filter === 'defaulted' ? 'selected' : ''; ?>>Defaulted</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <a href="loans.php" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> My Loans</h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Interest Rate</th>
                            <th>Term</th>
                            <th>Application Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: var(--spacing-2xl); color: var(--medium-gray);">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;"></i>
                                    <p>No loans found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            // Check for overdue loans and send notifications (only once per page load)
                            if (!isset($_SESSION['overdue_checked'])) {
                                checkAndNotifyOverdueLoans();
                                $_SESSION['overdue_checked'] = true;
                            }
                            
                            foreach ($loans as $loan): 
                                // Check if loan has overdue installments
                                $overdue_stmt = $pdo->prepare("
                                    SELECT MIN(due_date) as next_overdue_date
                                    FROM loan_schedule 
                                    WHERE loan_id = ? 
                                    AND status != 'paid' 
                                    AND due_date < CURDATE()
                                ");
                                $overdue_stmt->execute([$loan['loan_id']]);
                                $overdue_data = $overdue_stmt->fetch(PDO::FETCH_ASSOC);
                                $is_overdue = !empty($overdue_data['next_overdue_date']);
                                $row_style = $is_overdue ? 'background-color: #ffebee; color: #c62828;' : '';
                            ?>
                                <tr style="<?php echo $row_style; ?>">
                                    <td>#<?php echo $loan['loan_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($loan['customer_name']); ?></strong><br>
                                        <small style="color: <?php echo $is_overdue ? '#b71c1c' : 'var(--medium-gray)'; ?>;"><?php echo htmlspecialchars($loan['phone']); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                    <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                    <td><?php echo $loan['term_display'] ?? (($loan['loan_term_months'] ?? 0) . ' months'); ?></td>
                                    <td><?php echo formatDate($loan['application_date']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $loan['status']; ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle" style="color: #c62828; margin-left: 5px;" title="Past Due"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: var(--spacing-xs);">
                                            <a href="../admin/customer_account.php?id=<?php echo $loan['customer_id']; ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                        </table>
                    </div>
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


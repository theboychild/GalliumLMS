<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdminOrOfficer();

$is_admin = isAdmin();
$user_id = $_SESSION['user_id'];
$base_path = $is_admin ? '' : '../';
$errors = [];
$success = false;
$success_message = '';

// Handle loan actions
if (isset($_GET['approve'])) {
    $loan_id = intval($_GET['approve']);
    $result = approveLoan($loan_id, $user_id);
    if ($result['success']) {
        $success = true;
        $success_message = "Loan #$loan_id approved successfully.";
    } else {
        $errors[] = $result['message'];
    }
}

if (isset($_GET['disburse'])) {
    $loan_id = intval($_GET['disburse']);
    $result = disburseLoan($loan_id, $user_id);
    if ($result['success']) {
        $success = true;
        $success_message = "Loan #$loan_id disbursed and activated successfully.";
    } else {
        $errors[] = $result['message'];
    }
}

if (isset($_GET['reject'])) {
    $loan_id = intval($_GET['reject']);
    $rejection_reason = $_GET['reason'] ?? 'Not specified';
    $result = rejectLoan($loan_id, $user_id, $rejection_reason);
    if ($result['success']) {
        $success = true;
        $success_message = "Loan #$loan_id rejected.";
    } else {
        $errors[] = $result['message'];
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = "1=1";
$params = [];

if (!$is_admin) {
    $where .= " AND l.officer_id = ?";
    $params[] = $user_id;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        // Active loans: status is 'active' AND not fully paid (not 'completed')
        $where .= " AND l.status = 'active' AND l.status != 'completed'";
    } else {
        $where .= " AND l.status = ?";
        $params[] = $status_filter;
    }
}

if (!empty($search)) {
    $search_param = "%$search%";
    $search_conditions = [];
    $search_params = [];
    
    // Search by customer name
    $search_conditions[] = "c.customer_name LIKE ?";
    $search_params[] = $search_param;
    
    // Search by loan ID if numeric
    if (is_numeric($search)) {
        $search_conditions[] = "l.loan_id = ?";
        $search_params[] = intval($search);
    }
    
    // Search by date (YYYY-MM-DD or DD/MM/YYYY)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
        $search_conditions[] = "DATE(l.application_date) = ?";
        $search_params[] = $search;
    } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)) {
        $date_parts = explode('/', $search);
        $date_search = $date_parts[2] . '-' . $date_parts[0] . '-' . $date_parts[1];
        $search_conditions[] = "DATE(l.application_date) = ?";
        $search_params[] = $date_search;
    }
    
    if (!empty($search_conditions)) {
        $where .= " AND (" . implode(' OR ', $search_conditions) . ")";
        $params = array_merge($params, $search_params);
    }
}

// Get loans with payment status - handle missing loan_term_type column
try {
    // Check if loan_term_type column exists
    $col_check = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
    
    if ($col_check) {
        // Column exists, use full query
        $sql = "
            SELECT l.*, c.customer_name, c.phone, c.email, u.username as officer_name,
                   COALESCE(SUM(lp.payment_amount), 0) as total_paid,
                   (l.loan_amount + (l.loan_amount * l.interest_rate / 100 * 
                    CASE 
                        WHEN l.loan_term_type = 'weeks' THEN COALESCE(l.loan_term_weeks, 0)
                        ELSE COALESCE(l.loan_term_months, 0)
                    END)) as total_due,
                   CASE 
                       WHEN l.loan_term_type = 'weeks' THEN CONCAT(l.loan_term_weeks, ' weeks')
                       ELSE CONCAT(COALESCE(l.loan_term_months, 0), ' months')
                   END as term_display,
                   (SELECT MIN(ls.due_date) 
                    FROM loan_schedule ls 
                    WHERE ls.loan_id = l.loan_id 
                    AND ls.status != 'paid' 
                    AND ls.due_date < CURDATE()) as next_overdue_date
            FROM loans l
            LEFT JOIN customers c ON l.customer_id = c.customer_id
            LEFT JOIN users u ON l.officer_id = u.user_id
            LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
            WHERE $where
            GROUP BY l.loan_id
            ORDER BY l.created_at DESC
            LIMIT 100
        ";
    } else {
        // Column doesn't exist, use fallback query
        $sql = "
            SELECT l.*, c.customer_name, c.phone, c.email, u.username as officer_name,
                   COALESCE(SUM(lp.payment_amount), 0) as total_paid,
                   (l.loan_amount + (l.loan_amount * l.interest_rate / 100 * 
                    CASE 
                        WHEN l.loan_term_type = 'weeks' THEN COALESCE(l.loan_term_weeks, 0)
                        ELSE COALESCE(l.loan_term_months, 0)
                    END)) as total_due,
                   CONCAT(COALESCE(l.loan_term_months, 0), ' months') as term_display,
                   (SELECT MIN(ls.due_date) 
                    FROM loan_schedule ls 
                    WHERE ls.loan_id = l.loan_id 
                    AND ls.status != 'paid' 
                    AND ls.due_date < CURDATE()) as next_overdue_date
            FROM loans l
            LEFT JOIN customers c ON l.customer_id = c.customer_id
            LEFT JOIN users u ON l.officer_id = u.user_id
            LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
            WHERE $where
            GROUP BY l.loan_id
            ORDER BY l.created_at DESC
            LIMIT 100
        ";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Loans query error: " . $e->getMessage());
    // Fallback to simplest query
    $sql = "
        SELECT l.*, c.customer_name, c.phone, c.email, u.username as officer_name,
               COALESCE(SUM(lp.payment_amount), 0) as total_paid,
               (l.loan_amount + (l.loan_amount * l.interest_rate / 100 * COALESCE(l.loan_term_months, 0))) as total_due,
               CONCAT(COALESCE(l.loan_term_months, 0), ' months') as term_display
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        LEFT JOIN users u ON l.officer_id = u.user_id
        LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
        WHERE $where
        GROUP BY l.loan_id
        ORDER BY l.created_at DESC
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get loan statistics
$stats = getLoanStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <?php include '../includes/theme_support.php'; ?>
</head>
<body class="theme-<?php echo $current_theme; ?>">
    <div class="admin-container">
        <?php 
        $user_type = $is_admin ? 'admin' : 'officer';
        $app_name = APP_NAME;
        $app_version = APP_VERSION;
        $base_path = '../';
        include '../includes/sidebar_template.php'; 
        ?>
        
        <div class="admin-main">
            
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-file-invoice-dollar"></i> Loans Management</h1>
                    <p>View, approve, and manage all loan applications</p>
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
                        <input type="text" name="search" id="search" class="form-control" placeholder="Customer name, Loan ID, or date (YYYY-MM-DD)" value="<?php echo htmlspecialchars($search); ?>">
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
                    <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Loans List</h2>
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
                            <th>Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: var(--spacing-2xl); color: var(--medium-gray);">
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
                                $is_overdue = !empty($loan['next_overdue_date']) && strtotime($loan['next_overdue_date']) < strtotime(date('Y-m-d'));
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
                                    <td><?php echo $loan['officer_name'] ? htmlspecialchars($loan['officer_name']) : 'Unassigned'; ?></td>
                                    <td>
                                        <div style="display: flex; gap: var(--spacing-xs);">
                                            <?php if ($loan['status'] === 'pending'): ?>
                                                <a href="loans.php?approve=<?php echo $loan['loan_id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this loan?');">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="loans.php?reject=<?php echo $loan['loan_id']; ?>&reason=Not%20approved" class="btn btn-danger btn-sm" onclick="return confirm('Reject this loan?');">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            <?php elseif ($loan['status'] === 'approved'): ?>
                                                <a href="loans.php?disburse=<?php echo $loan['loan_id']; ?>" class="btn btn-primary btn-sm" onclick="return confirm('Disburse and activate this loan?');">
                                                    <i class="fas fa-money-bill-wave"></i> Disburse
                                                </a>
                                            <?php endif; ?>
                                            <a href="customer_account.php?id=<?php echo $loan['customer_id']; ?>" class="btn btn-outline btn-sm">
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
        // Sidebar toggle functionality
        
        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                document.querySelector('.admin-container').classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>


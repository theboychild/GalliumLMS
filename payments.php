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

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $loan_id = intval($_POST['loan_id']);
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $transaction_reference = sanitize($_POST['transaction_reference'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    $result = recordLoanPayment($loan_id, $payment_amount, $payment_date, $payment_method, $transaction_reference, $user_id, $notes);
    
    if ($result['success']) {
        $success = true;
        $success_message = "Payment of UGX " . formatCurrency($payment_amount) . " recorded successfully.";
    } else {
        $errors[] = $result['message'];
    }
}

// Get filter parameters
$loan_id_filter = $_GET['loan_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Build query
$where = "lp.payment_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if (!$is_admin) {
    $where .= " AND lp.received_by = ?";
    $params[] = $user_id;
}

if (!empty($loan_id_filter)) {
    $where .= " AND lp.loan_id = ?";
    $params[] = intval($loan_id_filter);
}

// Get payments
$sql = "
    SELECT lp.*, l.loan_id, l.loan_amount, c.customer_name, c.phone, u.username as received_by_name
    FROM loan_payments lp
    LEFT JOIN loans l ON lp.loan_id = l.loan_id
    LEFT JOIN customers c ON l.customer_id = c.customer_id
    LEFT JOIN users u ON lp.received_by = u.user_id
    WHERE $where
    ORDER BY lp.payment_date DESC, lp.created_at DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active loans for payment form
$active_loans_sql = "
    SELECT l.loan_id, l.loan_amount, c.customer_name
    FROM loans l
    LEFT JOIN customers c ON l.customer_id = c.customer_id
    WHERE l.status = 'active'
";
if (!$is_admin) {
    $active_loans_sql .= " AND l.officer_id = ?";
}
$active_loans_sql .= " ORDER BY l.loan_id DESC LIMIT 50";

$stmt = $pdo->prepare($active_loans_sql);
if ($is_admin) {
    $stmt->execute();
} else {
    $stmt->execute([$user_id]);
}
$active_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_collected = !empty($payments) ? array_sum(array_column($payments, 'payment_amount')) : 0;
$total_principal = !empty($payments) ? array_sum(array_column($payments, 'principal_amount')) : 0;
$total_interest = !empty($payments) ? array_sum(array_column($payments, 'interest_amount')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-money-bill-wave"></i> Payments Management</h1>
                    <p>Record and track loan payments</p>
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
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-line"></i> Payment Summary</h2>
                </div>
                <div class="card-body">
                    <div class="stats-row">
                        <div class="stat-mini-card">
                            <h4>Total Collected</h4>
                            <div class="value"><?php echo formatCurrency($total_collected); ?></div>
                        </div>
                        <div class="stat-mini-card">
                            <h4>Principal Collected</h4>
                            <div class="value"><?php echo formatCurrency($total_principal); ?></div>
                        </div>
                        <div class="stat-mini-card">
                            <h4>Interest Collected</h4>
                            <div class="value"><?php echo formatCurrency($total_interest); ?></div>
                        </div>
                        <div class="stat-mini-card">
                            <h4>Total Payments</h4>
                            <div class="value"><?php echo count($payments); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="payment-form-section">
                <h2 style="margin-bottom: var(--spacing-lg); color: var(--primary-blue);">
                    <i class="fas fa-plus-circle"></i> Record New Payment
                </h2>
                <form method="POST" class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <input type="hidden" name="record_payment" value="1">
                    <div class="form-group">
                        <label for="loan_id">Loan *</label>
                        <select name="loan_id" id="loan_id" class="form-control" required>
                            <option value="">Select Loan</option>
                            <?php foreach ($active_loans as $loan): ?>
                                <option value="<?php echo $loan['loan_id']; ?>">
                                    #<?php echo $loan['loan_id']; ?> - <?php echo htmlspecialchars($loan['customer_name']); ?> (<?php echo formatCurrency($loan['loan_amount']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (UGX) *</label>
                        <input type="number" name="payment_amount" id="payment_amount" class="form-control" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transaction_reference">Transaction Reference</label>
                        <input type="text" name="transaction_reference" id="transaction_reference" class="form-control" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Payment
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="loan_id">Loan ID</label>
                        <input type="number" name="loan_id" id="loan_id" class="form-control" placeholder="Filter by loan ID" value="<?php echo htmlspecialchars($loan_id_filter); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-list"></i> Payment History</h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Loan ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: var(--spacing-2xl); color: var(--medium-gray);">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;"></i>
                                    <p>No payments found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['payment_id']; ?></td>
                                    <td><a href="<?php echo $base_path . ($is_admin ? 'customer_account.php' : 'officer/customer_account.php'); ?>?id=<?php echo $payment['loan_id']; ?>" style="color: var(--primary-blue);">#<?php echo $payment['loan_id']; ?></a></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong><br>
                                        <small style="color: var(--medium-gray);"><?php echo htmlspecialchars($payment['phone']); ?></small>
                                    </td>
                                    <td><strong><?php echo formatCurrency($payment['payment_amount']); ?></strong></td>
                                    <td><?php echo formatCurrency($payment['principal_amount']); ?></td>
                                    <td><?php echo formatCurrency($payment['interest_amount']); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <span style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $payment['payment_method']); ?></span>
                                        <?php if ($payment['transaction_reference']): ?>
                                            <br><small style="color: var(--medium-gray);">Ref: <?php echo htmlspecialchars($payment['transaction_reference']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $payment['received_by_name'] ? htmlspecialchars($payment['received_by_name']) : 'System'; ?></td>
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
</body>
</html>


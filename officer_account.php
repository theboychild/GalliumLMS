<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$user_id = $_SESSION['user_id'];
$officer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$officer_id) {
    header("Location: officers.php");
    exit();
}

$errors = [];
$success = false;

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_officer'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone']);
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!validatePhone($phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (excluding current officer)
    if (empty($errors) && !empty($email)) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $officer_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "An officer with this email already exists";
        }
    }
    
    // Check if phone already exists (excluding current officer)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE phone = ? AND user_id != ?");
        $stmt->execute([$phone, $officer_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "An officer with this phone number already exists";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, email = ?, phone = ?
                WHERE user_id = ? AND user_type = 'officer'
            ");
            $stmt->execute([$username, $email, $phone, $officer_id]);
            
            logAudit($user_id, 'Update Officer', 'users', $officer_id, null, ['username' => $username]);
            $success = true;
            // Refresh officer data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_type = 'officer'");
            $stmt->execute([$officer_id]);
            $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $errors[] = "Error updating officer: " . $e->getMessage();
        }
    }
}

// Get officer details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_type = 'officer'");
    $stmt->execute([$officer_id]);
    $officer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$officer) {
        header("Location: officers.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: officers.php");
    exit();
}

// Get officer's loans - handle case where loan_term_type might not exist
$check_column = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
if ($check_column) {
    // Column exists, use full query
    $sql = "
        SELECT l.*, c.customer_name, c.phone, c.email,
               CASE 
                   WHEN l.loan_term_type = 'weeks' THEN CONCAT(l.loan_term_weeks, ' weeks')
                   ELSE CONCAT(COALESCE(l.loan_term_months, 0), ' months')
               END as term_display
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.officer_id = ?
        ORDER BY l.created_at DESC
    ";
} else {
    // Column doesn't exist, use fallback
    $sql = "
        SELECT l.*, c.customer_name, c.phone, c.email,
               CONCAT(COALESCE(l.loan_term_months, 0), ' months') as term_display
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.officer_id = ?
        ORDER BY l.created_at DESC
    ";
}
$stmt = $pdo->prepare($sql);
$stmt->execute([$officer_id]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get officer's payments received
$stmt = $pdo->prepare("
    SELECT lp.*, l.loan_id, c.customer_name
    FROM loan_payments lp
    LEFT JOIN loans l ON lp.loan_id = l.loan_id
    LEFT JOIN customers c ON l.customer_id = c.customer_id
    WHERE lp.received_by = ?
    ORDER BY lp.payment_date DESC
    LIMIT 50
");
$stmt->execute([$officer_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate performance metrics
$total_loans = count($loans);
$active_loans = 0;
$pending_loans = 0;
$approved_loans = 0;
$completed_loans = 0;
$total_loan_amount = 0;
$total_collected = 0;
$total_customers = 0;

$customer_ids = [];
foreach ($loans as $loan) {
    if (!in_array($loan['customer_id'], $customer_ids)) {
        $customer_ids[] = $loan['customer_id'];
        $total_customers++;
    }
    
    if ($loan['status'] === 'active' || $loan['status'] === 'approved') {
        $active_loans++;
    }
    if ($loan['status'] === 'pending') {
        $pending_loans++;
    }
    if ($loan['status'] === 'approved') {
        $approved_loans++;
    }
    if ($loan['status'] === 'completed') {
        $completed_loans++;
    }
    
    $total_loan_amount += $loan['loan_amount'];
}

foreach ($payments as $payment) {
    $total_collected += $payment['payment_amount'];
}

// Get recent activity
$recent_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT action, table_name, created_at
        FROM audit_log
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$officer_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Account - <?php echo htmlspecialchars($officer['username']); ?> - <?php echo APP_NAME; ?></title>
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
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <h1><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($officer['username']); ?></h1>
                        <p>Officer Account & Performance Dashboard</p>
                    </div>
                    <div>
                        <a href="officers.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Officers
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid" style="margin-bottom: var(--spacing-xl);">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Loans</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($total_loans); ?></div>
                    <div class="change">All time loans</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Active Loans</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($active_loans); ?></div>
                    <div class="change">Currently active</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Customers</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo number_format($total_customers); ?></div>
                    <div class="change">Unique customers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Disbursed</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo formatCurrency($total_loan_amount); ?></div>
                    <div class="change">Total loan amount</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Collected</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="value" style="color: var(--success-green);"><?php echo formatCurrency($total_collected); ?></div>
                    <div class="change">Payments received</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Approval Rate</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="value">
                        <?php 
                        $approval_rate = $total_loans > 0 ? ($approved_loans / $total_loans * 100) : 0;
                        echo number_format($approval_rate, 1); 
                        ?>%
                    </div>
                    <div class="change">Loan approval rate</div>
                </div>
            </div>
            
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
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p><strong>Officer information updated successfully!</strong></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <h2 class="card-title"><i class="fas fa-user"></i> Officer Information</h2>
                        <button onclick="toggleEdit()" class="btn btn-primary" id="editToggleBtn">
                            <i class="fas fa-edit"></i> Edit Information
                        </button>
                    </div>
                </div>
                <div class="card-body">
                
                <form method="POST" id="editForm" style="display: none;">
                    <input type="hidden" name="update_officer" value="1">
                    
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
                        <div class="form-group">
                            <label for="username">Username <span class="required-indicator">*</span></label>
                            <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($officer['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($officer['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone <span class="required-indicator">*</span></label>
                            <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($officer['phone']); ?>" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end;">
                        <button type="button" onclick="toggleEdit()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
                
                <div id="infoDisplay" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                    <div class="info-item">
                        <label>Username</label>
                        <div class="info-value"><?php echo htmlspecialchars($officer['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <div class="info-value"><?php echo htmlspecialchars($officer['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <div class="info-value"><?php echo htmlspecialchars($officer['phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <div class="info-value">
                            <span class="status-badge <?php echo ($officer['is_active'] ?? 1) == 1 ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ($officer['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Password Change</label>
                        <div class="info-value">
                            <span class="status-badge <?php echo ($officer['can_change_password'] ?? 0) == 1 ? 'status-active' : 'status-pending'; ?>">
                                <?php echo ($officer['can_change_password'] ?? 0) == 1 ? 'Allowed' : 'Locked'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Member Since</label>
                        <div class="info-value"><?php echo date('M j, Y', strtotime($officer['created_at'])); ?></div>
                    </div>
                </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Loan History</h2>
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
                                    <?php foreach ($loans as $loan): ?>
                                        <tr>
                                            <td>#<?php echo $loan['loan_id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($loan['customer_name']); ?></strong><br>
                                                <small style="color: var(--medium-gray);"><?php echo htmlspecialchars($loan['phone']); ?></small>
                                            </td>
                                            <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                            <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                            <td><?php echo $loan['term_display'] ?? (($loan['loan_term_months'] ?? 0) . ' months'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($loan['application_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $loan['status']; ?>">
                                                    <?php echo ucfirst($loan['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="customer_account.php?id=<?php echo $loan['customer_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View Customer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Recent Payments Received</h2>
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
                                    <th>Date</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: var(--spacing-2xl); color: var(--medium-gray);">
                                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;"></i>
                                            <p>No payments found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>#<?php echo $payment['payment_id']; ?></td>
                                            <td>#<?php echo $payment['loan_id']; ?></td>
                                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['payment_amount']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
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
        function toggleEdit() {
            const form = document.getElementById('editForm');
            const display = document.getElementById('infoDisplay');
            const btn = document.getElementById('editToggleBtn');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                display.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            } else {
                form.style.display = 'none';
                display.style.display = 'grid';
                btn.innerHTML = '<i class="fas fa-edit"></i> Edit Information';
            }
        }
    </script>
</body>
</html>


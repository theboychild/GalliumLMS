<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdminOrOfficer();

$is_admin = isAdmin();
$user_id = $_SESSION['user_id'];
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    $redirect_url = $is_admin ? 'customers.php' : '../officer/customers.php';
    header("Location: " . $redirect_url);
    exit();
}

$customer = getCustomerById($customer_id);
if (!$customer) {
    $redirect_url = $is_admin ? 'customers.php' : '../officer/customers.php';
    header("Location: " . $redirect_url);
    exit();
}

$errors = [];
$success = false;

// Handle loan update (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_loan']) && $is_admin) {
    $loan_id = intval($_POST['loan_id']);
    $new_interest_rate = floatval($_POST['interest_rate']);
    
    // Validate
    if ($new_interest_rate < 0 || $new_interest_rate > 100) {
        $errors[] = "Interest rate must be between 0 and 100";
    }
    
    // Get loan details
    $loan_stmt = $pdo->prepare("SELECT * FROM loans WHERE loan_id = ? AND customer_id = ?");
    $loan_stmt->execute([$loan_id, $customer_id]);
    $loan_to_update = $loan_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan_to_update) {
        $errors[] = "Loan not found";
    }
    
    if (empty($errors)) {
        try {
            // Update interest rate
            $update_stmt = $pdo->prepare("UPDATE loans SET interest_rate = ? WHERE loan_id = ?");
            $update_stmt->execute([$new_interest_rate, $loan_id]);
            
            // Recalculate loan schedule
            if (function_exists('generateLoanSchedule')) {
                // Delete old schedule
                $pdo->prepare("DELETE FROM loan_schedule WHERE loan_id = ?")->execute([$loan_id]);
                
                // Determine loan term and term type
                $loan_term = 0;
                $term_type = 'months';
                if (isset($loan_to_update['loan_term_type']) && $loan_to_update['loan_term_type'] === 'weeks' && isset($loan_to_update['loan_term_weeks'])) {
                    $loan_term = intval($loan_to_update['loan_term_weeks']);
                    $term_type = 'weeks';
                } elseif (isset($loan_to_update['loan_term_months'])) {
                    $loan_term = intval($loan_to_update['loan_term_months']);
                    $term_type = 'months';
                }
                
                if ($loan_term > 0) {
                    // Generate new schedule with updated interest rate
                    generateLoanSchedule(
                        $loan_id,
                        $loan_to_update['loan_amount'],
                        $new_interest_rate,
                        $loan_term,
                        $loan_to_update['application_date'],
                        $term_type
                    );
                }
            }
            
            logAudit($user_id, 'Update Loan Interest Rate', 'loans', $loan_id, null, [
                'old_rate' => $loan_to_update['interest_rate'],
                'new_rate' => $new_interest_rate
            ]);
            
            $success = true;
            // Refresh loans
            $stmt = $pdo->prepare("
                SELECT l.*, u.username as officer_name 
                FROM loans l 
                LEFT JOIN users u ON l.officer_id = u.user_id 
                WHERE l.customer_id = ? 
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$customer_id]);
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $errors[] = "Error updating loan: " . $e->getMessage();
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $customer_name = sanitize($_POST['customer_name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $occupation = sanitize($_POST['occupation'] ?? '');
    $employer = sanitize($_POST['employer'] ?? '');
    $monthly_income = !empty($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : null;
    
    // Validation
    if (empty($customer_name)) {
        $errors[] = "Customer name is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!validatePhone($phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if phone already exists (excluding current customer)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ?");
        $stmt->execute([$phone, $customer_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "A customer with this phone number already exists";
        }
    }
    
    if (empty($errors)) {
        try {
            // Get next of kin fields
            $next_of_kin_name = sanitize($_POST['next_of_kin_name'] ?? '');
            $next_of_kin_phone = sanitize($_POST['next_of_kin_phone'] ?? '');
            $next_of_kin_relationship = sanitize($_POST['next_of_kin_relationship'] ?? '');
            $next_of_kin_address = sanitize($_POST['next_of_kin_address'] ?? '');
            
            // Check if next_of_kin columns exist
            $col_check = $pdo->query("SHOW COLUMNS FROM customers LIKE 'next_of_kin_name'")->fetch();
            
            if ($col_check) {
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET customer_name = ?, phone = ?, email = ?, address = ?, 
                        occupation = ?, employer = ?, monthly_income = ?,
                        next_of_kin_name = ?, next_of_kin_phone = ?, 
                        next_of_kin_relationship = ?, next_of_kin_address = ?
                    WHERE customer_id = ?
                ");
                $stmt->execute([$customer_name, $phone, $email, $address, $occupation, $employer, $monthly_income, $next_of_kin_name, $next_of_kin_phone, $next_of_kin_relationship, $next_of_kin_address, $customer_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET customer_name = ?, phone = ?, email = ?, address = ?, 
                        occupation = ?, employer = ?, monthly_income = ?
                    WHERE customer_id = ?
                ");
                $stmt->execute([$customer_name, $phone, $email, $address, $occupation, $employer, $monthly_income, $customer_id]);
            }
            
            // Update user account if exists
            if ($customer['user_id']) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE user_id = ?");
                $stmt->execute([$customer_name, $email, $phone, $customer['user_id']]);
            }
            
            logAudit($user_id, 'Update Customer', 'customers', $customer_id, null, ['customer_name' => $customer_name]);
            $success = true;
            $customer = getCustomerById($customer_id); // Refresh data
        } catch (PDOException $e) {
            $errors[] = "Error updating customer: " . $e->getMessage();
        }
    }
}

// Get customer's loans - handle missing loan_term_type column
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
    
    if ($col_check) {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username as officer_name 
            FROM loans l 
            LEFT JOIN users u ON l.officer_id = u.user_id 
            WHERE l.customer_id = ? 
            ORDER BY l.created_at DESC
        ");
    } else {
        // Fallback: add loan_term_type as 'months' for old loans
        $stmt = $pdo->prepare("
            SELECT l.*, u.username as officer_name, 'months' as loan_term_type
            FROM loans l 
            LEFT JOIN users u ON l.officer_id = u.user_id 
            WHERE l.customer_id = ? 
            ORDER BY l.created_at DESC
        ");
    }
    $stmt->execute([$customer_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching loans: " . $e->getMessage());
    // Fallback query
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as officer_name, 'months' as loan_term_type
        FROM loans l 
        LEFT JOIN users u ON l.officer_id = u.user_id 
        WHERE l.customer_id = ? 
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check and update overdue loans
checkAndUpdateOverdueLoans();

// Get previous and next customer IDs for navigation
$stmt = $pdo->prepare("
    SELECT customer_id FROM customers 
    WHERE customer_id < ? 
    ORDER BY customer_id DESC 
    LIMIT 1
");
$stmt->execute([$customer_id]);
$prev_customer = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT customer_id FROM customers 
    WHERE customer_id > ? 
    ORDER BY customer_id ASC 
    LIMIT 1
");
$stmt->execute([$customer_id]);
$next_customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get customer documents
$documents = getCustomerDocuments($customer_id);

// Calculate performance metrics
$total_loans = count($loans);
$active_loans = 0;
$total_borrowed = 0;
$total_paid = 0;
$total_outstanding = 0;
$on_time_payments = 0;
$late_payments = 0;

foreach ($loans as $loan) {
    if ($loan['status'] === 'approved' || $loan['status'] === 'active') {
        $active_loans++;
    }
    $total_borrowed += $loan['loan_amount'];
    
    // Get payments for this loan
    $payment_stmt = $pdo->prepare("SELECT SUM(payment_amount) as total_paid FROM loan_payments WHERE loan_id = ?");
    $payment_stmt->execute([$loan['loan_id']]);
    $payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    $paid = $payment_data['total_paid'] ?? 0;
    $total_paid += $paid;
    
    // Calculate outstanding: interest = (rate/100) * principal * loan_term
    $loan_term = 0;
    if (isset($loan['loan_term_type']) && $loan['loan_term_type'] === 'weeks' && isset($loan['loan_term_weeks'])) {
        $loan_term = intval($loan['loan_term_weeks']);
    } elseif (isset($loan['loan_term_months'])) {
        $loan_term = intval($loan['loan_term_months']);
    }
    
    if ($loan_term > 0) {
        $total_interest = ($loan['interest_rate'] / 100) * $loan['loan_amount'] * $loan_term;
    } else {
        // Fallback if loan_term is not available
        $total_interest = ($loan['interest_rate'] / 100) * $loan['loan_amount'];
    }
    $total_due = $loan['loan_amount'] + $total_interest;
    $outstanding = max(0, $total_due - $paid);
    $total_outstanding += $outstanding;
}

// Calculate total overpayment for all loans
$total_overpayment_all = 0;
foreach ($loans as $loan) {
    $total_overpayment_all += getLoanOverpayment($loan['loan_id']);
}

// Calculate age
$age = 0;
if ($customer['date_of_birth']) {
    $birth_date = new DateTime($customer['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Account - <?php echo htmlspecialchars($customer['customer_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <h1><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($customer['customer_name']); ?></h1>
                        <p>Customer Account & Performance Dashboard</p>
                    </div>
                    <div style="display: flex; gap: var(--spacing-md); align-items: center;">
                        <a href="<?php echo $is_admin ? 'add_loan.php?customer_id=' . $customer_id : '../officer/add_loan.php?customer_id=' . $customer_id; ?>" class="btn" style="background: var(--accent-gold); color: var(--white);">
                            <i class="fas fa-file-invoice-dollar"></i> New Loan
                        </a>
                        <a href="customers.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
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
                    <p><strong>Customer information updated successfully!</strong></p>
                </div>
            <?php endif; ?>
            
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
                        <h3>Total Borrowed</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="value"><?php echo formatCurrency($total_borrowed); ?></div>
                    <div class="change">Total amount borrowed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Paid</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="value" style="color: var(--success-green);"><?php echo formatCurrency($total_paid); ?></div>
                    <div class="change">Amount repaid</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Outstanding</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="value" style="color: var(--danger-red);"><?php echo formatCurrency($total_outstanding); ?></div>
                    <div class="change">Remaining balance</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Repayment Rate</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="value">
                        <?php 
                        $repayment_rate = $total_borrowed > 0 ? ($total_paid / $total_borrowed * 100) : 0;
                        echo number_format($repayment_rate, 1); 
                        ?>%
                    </div>
                    <div class="change">Payment performance</div>
                </div>
                
                <?php if ($total_overpayment_all > 0): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Overpayment</h3>
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                    <div class="value" style="color: var(--success-green);"><?php echo formatCurrency($total_overpayment_all); ?></div>
                    <div class="change">Excess payment amount</div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <h2 class="card-title"><i class="fas fa-user"></i> Customer Information</h2>
                        <button onclick="toggleEdit()" class="btn btn-primary" id="editToggleBtn">
                            <i class="fas fa-edit"></i> Edit Information
                        </button>
                    </div>
                </div>
                <div class="card-body">
                
                <form method="POST" id="editForm" style="display: none;">
                    <input type="hidden" name="update_customer" value="1">
                    
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
                        <div class="form-group">
                            <label for="customer_name">Full Name *</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control" value="<?php echo htmlspecialchars($customer['customer_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
                        <div class="form-group">
                            <label for="occupation">Occupation</label>
                            <input type="text" name="occupation" id="occupation" class="form-control" value="<?php echo htmlspecialchars($customer['occupation'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="employer">Employer</label>
                            <input type="text" name="employer" id="employer" class="form-control" value="<?php echo htmlspecialchars($customer['employer'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="monthly_income">Monthly Income (UGX)</label>
                            <input type="number" name="monthly_income" id="monthly_income" class="form-control" value="<?php echo $customer['monthly_income'] ?? ''; ?>" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                        <button type="button" onclick="toggleEdit()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
                
                <div id="infoDisplay" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Full Name</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                    </div>
                    
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Phone Number</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['phone']); ?></div>
                    </div>
                    
                    <?php if ($customer['email']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Email Address</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['email']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['national_id']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">National ID</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['national_id']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['next_of_kin_name']) || !empty($customer['next_of_kin_phone'])): ?>
                    <div style="grid-column: 1 / -1; padding: var(--spacing-md); background: var(--light-blue); border-radius: var(--radius-md); border-left: 4px solid var(--brand-blue);">
                        <label style="display: block; font-size: var(--font-size-sm); color: var(--brand-blue); font-weight: 600; margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-users"></i> Next of Kin Information
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md);">
                            <?php if ($customer['next_of_kin_name']): ?>
                            <div>
                                <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); margin-bottom: var(--spacing-xs);">Name</label>
                                <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['next_of_kin_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($customer['next_of_kin_phone']): ?>
                            <div>
                                <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); margin-bottom: var(--spacing-xs);">Phone</label>
                                <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['next_of_kin_phone']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($customer['next_of_kin_relationship']): ?>
                            <div>
                                <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); margin-bottom: var(--spacing-xs);">Relationship</label>
                                <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['next_of_kin_relationship']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($customer['next_of_kin_address']): ?>
                            <div style="grid-column: 1 / -1;">
                                <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); margin-bottom: var(--spacing-xs);">Address</label>
                                <div style="font-size: var(--font-size-sm); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['next_of_kin_address']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['date_of_birth']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Date of Birth / Age</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo formatDate($customer['date_of_birth']); ?> (<?php echo $age; ?> years)</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['gender']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Gender</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo ucfirst($customer['gender']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['address']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md); grid-column: 1 / -1;">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Address</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['address']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['occupation']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Occupation</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['occupation']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['employer']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Employer</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($customer['employer']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['monthly_income']): ?>
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Monthly Income</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo formatCurrency($customer['monthly_income']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="padding: var(--spacing-md); background: var(--light-gray); border-radius: var(--radius-md);">
                        <label style="display: block; font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--spacing-xs);">Registered</label>
                        <div style="font-size: var(--font-size-base); font-weight: 600; color: var(--dark-gray);"><?php echo formatDate($customer['created_at']); ?></div>
                    </div>
                </div>
                </div>
            </div>
            
            <div class="card" style="margin-top: var(--spacing-xl);">
                <div class="card-header">
                    <h2 class="card-title">Loan History (<?php echo count($loans); ?>)</h2>
                </div>
                
                <?php if (empty($loans)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>No Loans Found</h3>
                        <p>This customer hasn't applied for any loans yet.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Amount</th>
                                <th>Interest</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): 
                                $payment_stmt = $pdo->prepare("SELECT SUM(payment_amount) as total_paid FROM loan_payments WHERE loan_id = ?");
                                $payment_stmt->execute([$loan['loan_id']]);
                                $payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);
                                $paid = $payment_data['total_paid'] ?? 0;
                                
                                // Calculate interest: interest = (rate/100) * principal * loan_term
                                $loan_term = 0;
                                if (isset($loan['loan_term_type']) && $loan['loan_term_type'] === 'weeks' && isset($loan['loan_term_weeks'])) {
                                    $loan_term = intval($loan['loan_term_weeks']);
                                } elseif (isset($loan['loan_term_months'])) {
                                    $loan_term = intval($loan['loan_term_months']);
                                }
                                
                                if ($loan_term > 0) {
                                    $total_interest = ($loan['interest_rate'] / 100) * $loan['loan_amount'] * $loan_term;
                                } else {
                                    // Fallback if loan_term is not available
                                    $total_interest = ($loan['interest_rate'] / 100) * $loan['loan_amount'];
                                }
                                $total_due = $loan['loan_amount'] + $total_interest;
                                $outstanding = max(0, $total_due - $paid);
                            ?>
                                <tr>
                                    <td>#<?php echo $loan['loan_id']; ?></td>
                                    <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                    <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                    <td>
                                        <?php 
                                        $loan_term_type = $loan['loan_term_type'] ?? 'months';
                                        if ($loan_term_type === 'weeks') {
                                            echo ($loan['loan_term_weeks'] ?? 0) . ' weeks';
                                        } else {
                                            echo ($loan['loan_term_months'] ?? 0) . ' months';
                                        }
                                        ?>
                                    </td>
                                    <td class="status-<?php echo $loan['status']; ?>">
                                        <?php echo ucfirst($loan['status']); ?>
                                    </td>
                                    <td><?php echo formatCurrency($paid); ?></td>
                                    <td style="color: <?php echo $outstanding > 0 ? 'var(--danger-red)' : 'var(--success-green)'; ?>; font-weight: 600;">
                                        <?php echo formatCurrency($outstanding); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: var(--spacing-xs);">
                                            <a href="<?php echo $is_admin ? 'loans.php?view=' . $loan['loan_id'] : '../officer/loans.php?view=' . $loan['loan_id']; ?>" class="btn btn-sm btn-primary">
                                                View
                                            </a>
                                            <?php if ($is_admin): ?>
                                            <button onclick="openEditLoanModal(<?php echo $loan['loan_id']; ?>, <?php echo $loan['interest_rate']; ?>)" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <?php
            // Get all payments for this customer's loans
            $payment_history_sql = "
                SELECT lp.*, l.loan_id, l.loan_amount, u.username as received_by_name
                FROM loan_payments lp
                LEFT JOIN loans l ON lp.loan_id = l.loan_id
                LEFT JOIN users u ON lp.received_by = u.user_id
                WHERE l.customer_id = ?
                ORDER BY lp.payment_date DESC, lp.created_at DESC
            ";
            $payment_history_stmt = $pdo->prepare($payment_history_sql);
            $payment_history_stmt->execute([$customer_id]);
            $payment_history = $payment_history_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="card" style="margin-top: var(--spacing-xl);">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Payment History (<?php echo count($payment_history); ?>)</h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($payment_history)): ?>
                        <div class="empty-state" style="padding: var(--spacing-2xl);">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;"></i>
                            <p>No payments found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Loan ID</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Received By</th>
                                        <th>Overpayment</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_overpayment = 0;
                                    foreach ($payment_history as $payment): 
                                        // Get overpayment for this payment
                                        $overpayment = 0;
                                        if (isset($payment['overpayment'])) {
                                            $overpayment = floatval($payment['overpayment']);
                                        } else {
                                            // Extract from notes if available
                                            if (!empty($payment['notes']) && strpos($payment['notes'], 'Overpayment:') !== false) {
                                                preg_match('/Overpayment:\s*([\d,]+\.?\d*)/', $payment['notes'], $matches);
                                                if (!empty($matches[1])) {
                                                    $overpayment = floatval(str_replace(',', '', $matches[1]));
                                                }
                                            }
                                        }
                                        $total_overpayment += $overpayment;
                                    ?>
                                        <tr>
                                            <td>#<?php echo $payment['payment_id']; ?></td>
                                            <td>#<?php echo $payment['loan_id']; ?></td>
                                            <td><?php echo formatCurrency($payment['payment_amount']); ?></td>
                                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                            <td><?php echo htmlspecialchars($payment['received_by_name'] ?? 'N/A'); ?></td>
                                            <td style="color: <?php echo $overpayment > 0 ? 'var(--success-green)' : 'var(--medium-gray)'; ?>; font-weight: <?php echo $overpayment > 0 ? '600' : '400'; ?>;">
                                                <?php echo $overpayment > 0 ? formatCurrency($overpayment) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['notes'])): ?>
                                                    <div style="max-width: 300px; word-wrap: break-word;">
                                                        <i class="fas fa-sticky-note" style="color: var(--brand-blue); margin-right: var(--spacing-xs);"></i>
                                                        <span style="font-size: var(--font-size-sm); color: var(--dark-gray);"><?php echo htmlspecialchars($payment['notes']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--medium-gray); font-style: italic;">No notes</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card" style="margin-top: var(--spacing-xl);">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-upload"></i> Customer Documents (<?php echo count($documents); ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Documents Found</h3>
                            <p>This customer hasn't uploaded any documents yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--spacing-lg);">
                            <?php foreach ($documents as $doc): 
                                $file_ext = strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION));
                                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                                // Try multiple path resolutions
                                $absolute_path = null;
                                $possible_paths = [
                                    __DIR__ . '/../' . $doc['file_path'],
                                    __DIR__ . '/' . $doc['file_path'],
                                    realpath(__DIR__ . '/../' . $doc['file_path'])
                                ];
                                
                                foreach ($possible_paths as $path) {
                                    if ($path && file_exists($path)) {
                                        $absolute_path = $path;
                                        break;
                                    }
                                }
                                $file_exists = ($absolute_path !== null && file_exists($absolute_path));
                                $view_url = '../view_file.php?id=' . $doc['document_id'] . '&customer_id=' . $customer_id;
                                $download_url = '../view_file.php?id=' . $doc['document_id'] . '&customer_id=' . $customer_id . '&download=1';
                            ?>
                                <div class="document-card" style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: var(--spacing-lg); box-shadow: var(--shadow-sm); transition: var(--transition-base);">
                                    <div style="display: flex; align-items: start; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                                        <div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); background: var(--light-gray); flex-shrink: 0;">
                                            <?php if ($is_image): ?>
                                                <i class="fas fa-image" style="color: var(--brand-blue); font-size: 1.5rem;"></i>
                                            <?php elseif ($file_ext === 'pdf'): ?>
                                                <i class="fas fa-file-pdf" style="color: var(--brand-red); font-size: 1.5rem;"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file" style="color: var(--medium-gray); font-size: 1.5rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <strong style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: var(--spacing-xs); color: var(--dark-gray);"><?php echo htmlspecialchars($doc['document_name']); ?></strong>
                                            <small style="color: var(--medium-gray);"><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</small>
                                        </div>
                                    </div>
                                    <?php if ($doc['description']): ?>
                                        <p style="font-size: 0.9rem; color: var(--medium-gray); margin: 0 0 var(--spacing-md) 0; line-height: 1.5;"><?php echo htmlspecialchars($doc['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($file_exists): ?>
                                        <div style="display: flex; gap: var(--spacing-sm); margin-bottom: var(--spacing-sm);">
                                            <a href="<?php echo htmlspecialchars($view_url); ?>" target="_blank" class="btn btn-sm" style="flex: 1; text-align: center; background: var(--brand-blue); color: white; text-decoration: none;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="<?php echo htmlspecialchars($download_url); ?>" class="btn btn-sm btn-outline" style="flex: 1; text-align: center; text-decoration: none;">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div style="padding: var(--spacing-sm); background: #fee; border: 1px solid #fcc; border-radius: 4px; margin-bottom: var(--spacing-sm);">
                                            <small style="color: #c33;"><i class="fas fa-exclamation-triangle"></i> File not found on server</small>
                                        </div>
                                    <?php endif; ?>
                                    <div style="padding-top: var(--spacing-sm); border-top: 1px solid var(--border-color);">
                                        <small style="color: var(--medium-gray); display: flex; align-items: center; gap: var(--spacing-xs);">
                                            <i class="fas fa-clock"></i> <?php echo formatDate($doc['created_at']); ?>
                                            <?php if ($doc['uploaded_by_name']): ?>
                                                <span style="margin-left: var(--spacing-xs);">by <?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--spacing-xl); padding: var(--spacing-lg); background: var(--light-gray); border-radius: var(--radius-md);">
                <div>
                    <?php if (!empty($prev_customer) && isset($prev_customer['customer_id'])): ?>
                        <a href="customer_account.php?id=<?php echo $prev_customer['customer_id']; ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous Customer
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            <i class="fas fa-chevron-left"></i> Previous Customer
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="<?php echo $is_admin ? 'customers.php' : '../officer/customers.php'; ?>" class="btn btn-primary">
                        <i class="fas fa-list"></i> Back to Customers List
                    </a>
                </div>
                <div>
                    <?php if (!empty($next_customer) && isset($next_customer['customer_id'])): ?>
                        <a href="customer_account.php?id=<?php echo $next_customer['customer_id']; ?>" class="btn btn-outline">
                            Next Customer <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            Next Customer <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
            <div style="margin-top: var(--spacing-xl); text-align: center;">
                <a href="export_customer_pdf.php?id=<?php echo $customer_id; ?>" class="btn btn-primary" style="background: var(--brand-red); color: var(--white);">
                    <i class="fas fa-file-pdf"></i> Export Customer Data as PDF
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Loan Modal (Admin Only) -->
    <?php if ($is_admin): ?>
    <div id="editLoanModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: var(--spacing-2xl); border-radius: var(--radius-lg); max-width: 500px; width: 90%; box-shadow: var(--shadow-xl);">
            <h2 style="margin-bottom: var(--spacing-lg); color: var(--primary-blue);">
                <i class="fas fa-edit"></i> Edit Loan Interest Rate
            </h2>
            <form method="POST" id="editLoanForm">
                <input type="hidden" name="update_loan" value="1">
                <input type="hidden" name="loan_id" id="edit_loan_id">
                
                <div class="form-group">
                    <label for="edit_interest_rate">Interest Rate (%) *</label>
                    <input type="number" name="interest_rate" id="edit_interest_rate" class="form-control" 
                           min="0" max="100" step="0.01" required>
                    <small style="color: var(--medium-gray);">Changing the interest rate will recalculate the loan schedule.</small>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                    <button type="button" onclick="closeEditLoanModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Loan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        function toggleEdit() {
            const form = document.getElementById('editForm');
            const display = document.getElementById('infoDisplay');
            const btn = document.getElementById('editToggleBtn');
            
            if (!form || !display || !btn) {
                console.error('Toggle edit: Required elements not found');
                return;
            }
            
            const isHidden = form.style.display === 'none' || form.style.display === '';
            
            if (isHidden) {
                // Show form, hide display
                form.style.display = 'block';
                display.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel Edit';
                btn.classList.add('btn-danger');
                btn.classList.remove('btn-primary');
            } else {
                // Hide form, show display
                form.style.display = 'none';
                display.style.display = 'grid';
                btn.innerHTML = '<i class="fas fa-edit"></i> Edit Information';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
            }
        }
        
        // Ensure display is visible on page load
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editForm');
            const display = document.getElementById('infoDisplay');
            if (form && display) {
                form.style.display = 'none';
                display.style.display = 'grid';
            }
        });
        
        <?php if ($is_admin): ?>
        function openEditLoanModal(loanId, currentRate) {
            document.getElementById('edit_loan_id').value = loanId;
            document.getElementById('edit_interest_rate').value = currentRate;
            document.getElementById('editLoanModal').style.display = 'flex';
        }
        
        function closeEditLoanModal() {
            document.getElementById('editLoanModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editLoanModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditLoanModal();
            }
        });
        <?php endif; ?>
        
    </script>
</body>
</html>



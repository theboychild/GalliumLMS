<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$is_admin = false;
$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get customer_id from URL
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customer_id) {
    header("Location: customers.php");
    exit();
}

// Get customer details
$customer = getCustomerById($customer_id);
if (!$customer) {
    header("Location: customers.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_amount = !empty($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : null;
    $interest_rate = !empty($_POST['interest_rate']) ? floatval($_POST['interest_rate']) : DEFAULT_INTEREST_RATE;
    $loan_term_type = !empty($_POST['loan_term_type']) ? sanitize($_POST['loan_term_type']) : 'months';
    $loan_term_months = !empty($_POST['loan_term_months']) ? intval($_POST['loan_term_months']) : null;
    $loan_term_weeks = !empty($_POST['loan_term_weeks']) ? intval($_POST['loan_term_weeks']) : null;
    $application_date = !empty($_POST['application_date']) ? $_POST['application_date'] : date('Y-m-d');
    $loan_purpose = sanitize($_POST['loan_purpose'] ?? '');
    
    // Validation
    if (empty($loan_amount) || $loan_amount < 1000) {
        $errors[] = "Loan amount must be at least UGX 1,000";
    }
    
    if ($loan_term_type === 'weeks') {
        if (empty($loan_term_weeks) || $loan_term_weeks < 1 || $loan_term_weeks > 260) {
            $errors[] = "Loan term must be between 1 and 260 weeks";
        }
    } else {
        if (empty($loan_term_months) || $loan_term_months < 1 || $loan_term_months > 60) {
            $errors[] = "Loan term must be between 1 and 60 months";
        }
    }
    
    // Validate application date (no backdating)
    $date_validation = validateApplicationDate($application_date);
    if (!$date_validation['valid']) {
        $errors[] = $date_validation['message'];
    }
    
    if (empty($errors)) {
        try {
            $officer_id = $is_admin ? $user_id : $user_id;
            
            // Check if loan_term_type columns exist
            $col_check = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
            
            if ($col_check) {
                $stmt = $pdo->prepare("
                    INSERT INTO loans (customer_id, officer_id, loan_amount, interest_rate, loan_term_type, loan_term_months, loan_term_weeks, purpose, application_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $customer_id, 
                    $officer_id, 
                    $loan_amount, 
                    $interest_rate, 
                    $loan_term_type,
                    $loan_term_months,
                    $loan_term_weeks,
                    $loan_purpose, 
                    $application_date
                ]);
            } else {
                // Fallback for older schema
                $stmt = $pdo->prepare("
                    INSERT INTO loans (customer_id, officer_id, loan_amount, interest_rate, loan_term_months, purpose, application_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $customer_id, 
                    $officer_id, 
                    $loan_amount, 
                    $interest_rate, 
                    $loan_term_months,
                    $loan_purpose, 
                    $application_date
                ]);
            }
            
            $loan_id = $pdo->lastInsertId();
            
            // Generate loan schedule
            if (function_exists('generateLoanSchedule')) {
                if ($loan_term_type === 'weeks') {
                    generateLoanSchedule($loan_id, $loan_amount, $interest_rate, $loan_term_weeks, $application_date, 'weeks');
                } else {
                    generateLoanSchedule($loan_id, $loan_amount, $interest_rate, $loan_term_months, $application_date, 'months');
                }
            }
            
            logAudit($user_id, 'Create Loan', 'loans', $loan_id, null, ['customer_id' => $customer_id, 'amount' => $loan_amount]);
            
            // Notify admin if officer created the loan
            if (!$is_admin) {
                try {
                    $admin_users = $pdo->query("SELECT user_id FROM users WHERE user_type = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($admin_users as $admin_id) {
                        sendNotification(
                            $admin_id,
                            'New Loan Application Pending Approval',
                            "Officer " . htmlspecialchars($_SESSION['username'] ?? 'Unknown') . " has created a new loan application #$loan_id for customer '" . htmlspecialchars($customer['customer_name']) . "' with amount UGX " . number_format(round($loan_amount), 0) . ". Please review and approve.",
                            'system',
                            $_SESSION['user_id']
                        );
                    }
                } catch (PDOException $e) {
                    error_log("Failed to send notification: " . $e->getMessage());
                }
            }
            
            header("Location: customer_account.php?id=$customer_id&success=loan_added");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error creating loan: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Loan - <?php echo htmlspecialchars($customer['customer_name']); ?> - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-file-invoice-dollar"></i> Add New Loan</h1>
                    <p>Create a new loan application for <?php echo htmlspecialchars($customer['customer_name']); ?></p>
                </div>
                <div>
                    <a href="customer_account.php?id=<?php echo $customer_id; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Customer
                    </a>
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
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Loan Application Details</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="loan_amount">Loan Amount (UGX) <span class="required-indicator">*</span></label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control" min="1000" step="0.01" required placeholder="Enter loan amount (minimum: 1,000)">
                            </div>
                            
                            <div class="form-group">
                                <label for="interest_rate">Interest Rate (%) <span class="required-indicator">*</span></label>
                                <input type="number" name="interest_rate" id="interest_rate" class="form-control" min="0" max="100" step="0.01" value="<?php echo DEFAULT_INTEREST_RATE; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="loan_term_type">Loan Term Type <span class="required-indicator">*</span></label>
                                <select name="loan_term_type" id="loan_term_type" class="form-control" required onchange="toggleTermInput()">
                                    <option value="months">Months</option>
                                    <option value="weeks">Weeks</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="term_months_group">
                                <label for="loan_term_months" id="term_months_label">Loan Term (Months) <span class="required-indicator">*</span></label>
                                <input type="number" name="loan_term_months" id="loan_term_months" class="form-control" min="1" max="60" placeholder="Enter loan term (1-60)">
                            </div>
                            
                            <div class="form-group" id="term_weeks_group" style="display: none;">
                                <label for="loan_term_weeks" id="term_weeks_label">Loan Term (Weeks) <span class="required-indicator">*</span></label>
                                <input type="number" name="loan_term_weeks" id="loan_term_weeks" class="form-control" min="1" max="260" placeholder="Enter loan term (1-260)">
                            </div>
                            
                            <div class="form-group">
                                <label for="application_date">Application Date <span class="required-indicator">*</span></label>
                                <input type="date" name="application_date" id="application_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="loan_purpose">Loan Purpose</label>
                            <textarea name="loan_purpose" id="loan_purpose" class="form-control" rows="3" placeholder="Enter the purpose of this loan (optional)"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                            <a href="customer_account.php?id=<?php echo $customer_id; ?>" class="btn btn-outline">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Loan Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleTermInput() {
            const termType = document.getElementById('loan_term_type').value;
            const monthsGroup = document.getElementById('term_months_group');
            const weeksGroup = document.getElementById('term_weeks_group');
            const monthsInput = document.getElementById('loan_term_months');
            const weeksInput = document.getElementById('loan_term_weeks');
            
            if (termType === 'weeks') {
                monthsGroup.style.display = 'none';
                weeksGroup.style.display = 'block';
                monthsInput.removeAttribute('required');
                weeksInput.setAttribute('required', 'required');
            } else {
                monthsGroup.style.display = 'block';
                weeksGroup.style.display = 'none';
                weeksInput.removeAttribute('required');
                monthsInput.setAttribute('required', 'required');
            }
        }
        
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


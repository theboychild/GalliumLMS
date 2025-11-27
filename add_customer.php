<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdminOrOfficer();

$errors = [];
$success = false;
$is_admin = isAdmin();
$user_id = $_SESSION['user_id'];
$customer_added_name = '';
$loan_added = false;
$customer_warning = null;
$customer_id = null;
$loan_result = null;
$warnings = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = sanitize($_POST['customer_name']);
    $national_id = sanitize($_POST['national_id'] ?? '');
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $occupation = sanitize($_POST['occupation'] ?? '');
    $employer = sanitize($_POST['employer'] ?? '');
    $monthly_income = !empty($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : null;
    $security = sanitize($_POST['security'] ?? ''); // Security/collateral item
    
    // Next of kin details (optional)
    $next_of_kin_name = sanitize($_POST['next_of_kin_name'] ?? '');
    $next_of_kin_phone = sanitize($_POST['next_of_kin_phone'] ?? '');
    $next_of_kin_relationship = sanitize($_POST['next_of_kin_relationship'] ?? '');
    $next_of_kin_address = sanitize($_POST['next_of_kin_address'] ?? '');
    
    // Loan application details
    $loan_amount = !empty($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : null;
    $interest_rate = !empty($_POST['interest_rate']) ? floatval($_POST['interest_rate']) : DEFAULT_INTEREST_RATE;
    $loan_term_months = !empty($_POST['loan_term_months']) ? intval($_POST['loan_term_months']) : null;
    $loan_term_weeks = !empty($_POST['loan_term_weeks']) ? intval($_POST['loan_term_weeks']) : null;
    $loan_term_type = !empty($_POST['loan_term_type']) ? sanitize($_POST['loan_term_type']) : 'months';
    $application_date = !empty($_POST['application_date']) ? $_POST['application_date'] : date('Y-m-d');
    $loan_purpose = sanitize($_POST['loan_purpose'] ?? '');
    
    // Validation
    if (empty($customer_name)) {
        $errors[] = "Customer name is required";
    }
    
    if (empty($date_of_birth)) {
        $errors[] = "Date of birth is required";
    } else {
        $age_validation = validateAge($date_of_birth);
        if (!$age_validation['valid']) {
            $errors[] = $age_validation['message'];
        }
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!validatePhone($phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if phone already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "A customer with this phone number already exists";
        }
    }
    
    // Validate National ID format if provided
    if (empty($errors) && !empty($national_id)) {
        $national_id_validation = validateNationalId($national_id, $gender, $date_of_birth);
        if (!$national_id_validation['valid']) {
            $errors[] = $national_id_validation['message'];
        }
        
        // Check if national ID already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE national_id = ?");
            $stmt->execute([$national_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "A customer with this National ID already exists";
            }
        }
    }
    
    if (empty($errors)) {
        // Create user account for customer (optional - can be null)
        $user_id = null;
        
        // Optionally create user account if email provided
        if (!empty($email)) {
            try {
                // Check if user with this email exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user) {
                    $user_id = $existing_user['user_id'];
                } else {
                    // Create user account
                    $temp_password = bin2hex(random_bytes(8)); // Temporary password
                    $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, phone, password, user_type)
                        VALUES (?, ?, ?, ?, 'customer')
                    ");
                    $stmt->execute([$customer_name, $email, $phone, $hashed_password]);
                    $user_id = $pdo->lastInsertId();
                }
            } catch (PDOException $e) {
                // If user creation fails, continue without user_id (customer can still be created)
                error_log("User account creation error: " . $e->getMessage());
                $user_id = null;
            }
        }
        
        // Create customer profile
        $result = createCustomer($user_id, $customer_name, $national_id, $date_of_birth, $gender, $phone, $email, $address, $occupation, $employer, $monthly_income, $security, $next_of_kin_name, $next_of_kin_phone, $next_of_kin_relationship, $next_of_kin_address);
        
        if ($result['success']) {
            $customer_id = $result['customer_id'];
            
            // Create loan application if loan details provided
            if ($loan_amount && (($loan_term_type === 'months' && $loan_term_months) || ($loan_term_type === 'weeks' && $loan_term_weeks))) {
                // Validate loan amount
                $amount_validation = validateLoanAmount($loan_amount);
                if (!$amount_validation['valid']) {
                    $errors[] = $amount_validation['message'];
                }
                
                // Validate application date (no backdating)
                $date_validation = validateApplicationDate($application_date);
                if (!$date_validation['valid']) {
                    $errors[] = $date_validation['message'];
                }
                
                if (empty($errors)) {
                    // Create loan with officer assignment
                    try {
                        $amount_validation = validateLoanAmount($loan_amount);
                        $date_validation = validateApplicationDate($application_date);
                        
                        if ($amount_validation['valid'] && $date_validation['valid']) {
                            $officer_id = $is_admin ? null : $user_id; // Assign to officer if not admin
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO loans (customer_id, officer_id, loan_amount, interest_rate, loan_term_months, loan_term_weeks, loan_term_type, purpose, application_date, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            $stmt->execute([$customer_id, $officer_id, $amount_validation['amount'], $interest_rate, $loan_term_months, $loan_term_weeks, $loan_term_type, $loan_purpose, $application_date]);
                            $loan_id = $pdo->lastInsertId();
                            
                            // Generate loan schedule
                            if (function_exists('generateLoanSchedule')) {
                                $term_value = $loan_term_type === 'weeks' ? $loan_term_weeks : $loan_term_months;
                                generateLoanSchedule($loan_id, $amount_validation['amount'], $interest_rate, $term_value, $application_date, $loan_term_type);
                            }
                            
                            $loan_result = ['success' => true, 'loan_id' => $loan_id];
                        } else {
                            $loan_result = ['success' => false, 'message' => 'Loan validation failed'];
                        }
                    } catch (PDOException $e) {
                        $loan_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                    }
                    
                    if (!$loan_result['success']) {
                        $errors[] = "Customer created but loan creation failed: " . ($loan_result['message'] ?? 'Unknown error');
                    }
                }
            }
            
            if (empty($errors)) {
                // Handle file uploads
                if (isset($_FILES['customer_documents']) && !empty($_FILES['customer_documents']['name'])) {
                    $uploaded_files = $_FILES['customer_documents'];
                    
                    // Check if single file or multiple files
                    $is_array = is_array($uploaded_files['name']);
                    $file_count = $is_array ? count($uploaded_files['name']) : 1;
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        $file_error = $is_array ? $uploaded_files['error'][$i] : $uploaded_files['error'];
                        
                        if ($file_error === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $is_array ? $uploaded_files['name'][$i] : $uploaded_files['name'],
                                'type' => $is_array ? $uploaded_files['type'][$i] : $uploaded_files['type'],
                                'tmp_name' => $is_array ? $uploaded_files['tmp_name'][$i] : $uploaded_files['tmp_name'],
                                'error' => $file_error,
                                'size' => $is_array ? $uploaded_files['size'][$i] : $uploaded_files['size']
                            ];
                            
                            $description = sanitize($_POST['document_descriptions'][$i] ?? '');
                            $upload_result = uploadCustomerDocument($customer_id, $file, $description, $_SESSION['user_id']);
                            
                            if (!$upload_result['success']) {
                                $warnings[] = "File '{$file['name']}' upload failed: " . $upload_result['message'];
                                error_log("File upload failed for customer $customer_id: " . $upload_result['message']);
                            } else {
                                error_log("File uploaded successfully: " . $file['name'] . " for customer $customer_id");
                            }
                        } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                            $file_name = $is_array ? ($uploaded_files['name'][$i] ?? 'Unknown') : ($uploaded_files['name'] ?? 'Unknown');
                            $warnings[] = "File '{$file_name}' upload error code: {$file_error}";
                            error_log("File upload error for customer $customer_id: File '$file_name', Error code: $file_error");
                        }
                    }
                }
                
                // Log audit
                logAudit($_SESSION['user_id'], 'Add Customer', 'customers', $customer_id, null, ['customer_name' => $customer_name]);
                
                // Send notification if user account was created
                if ($user_id && !empty($email)) {
                    sendNotification($user_id, 'Account Created', 'Your customer account has been created. You can now apply for loans.', 'system');
                }
                
                // Redirect to prevent duplicate submissions
                $redirect_url = $is_admin ? 'customers.php' : '../officer/customers.php';
                $_SESSION['customer_added'] = true;
                $_SESSION['customer_added_name'] = $customer_name;
                if (isset($loan_result) && $loan_result['success']) {
                    $_SESSION['loan_added'] = true;
                }
                // Store upload warnings if any
                if (!empty($warnings)) {
                    $_SESSION['customer_warning'] = implode('; ', $warnings);
                }
                header("Location: $redirect_url");
                exit();
            } else {
                // Customer created but loan failed - still show success but with warning
                $warnings = $errors;
                $errors = [];
                // Redirect anyway since customer was created
                $redirect_url = $is_admin ? 'customers.php' : '../officer/customers.php';
                $_SESSION['customer_added'] = true;
                $_SESSION['customer_added_name'] = $customer_name;
                $_SESSION['customer_warning'] = implode(', ', $warnings);
                header("Location: $redirect_url");
                exit();
            }
        } else {
            $errors[] = $result['message'] ?? 'Failed to create customer. Please try again.';
        }
    }
}

// Note: Success messages are now shown on customers.php after redirect
// This code is kept for backward compatibility but should not execute due to redirect
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - <?php echo APP_NAME; ?></title>
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
                        <h1><i class="fas fa-user-plus"></i> Add New Customer</h1>
                        <p>Register a new customer for loan applications</p>
                    </div>
                    <a href="<?php echo $is_admin ? 'customers.php' : '../officer/customers.php'; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Customers
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
            
            <?php if (false): // Success messages now shown on customers.php after redirect ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <p><strong>Customer added successfully!</strong></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <form method="POST" action="" enctype="multipart/form-data" class="form-container">
                    <fieldset class="form-section">
                        <legend class="form-section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="customer_name">Full Name <span class="required-indicator">*</span></label>
                                    <input type="text" name="customer_name" id="customer_name" class="form-control" required placeholder="Enter full name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth <span class="required-indicator">*</span></label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" placeholder="Select date of birth">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gender">Gender <span class="required-indicator">*</span></label>
                                    <select name="gender" id="gender" class="form-control" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="national_id">National ID</label>
                                    <input type="text" name="national_id" id="national_id" class="form-control" placeholder="CM0312345ABC" maxlength="13">
                                </div>
                            </div>
                    </fieldset>
                    
                    <fieldset class="form-section">
                        <legend class="form-section-title">
                            <i class="fas fa-address-book"></i> Contact Information
                        </legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number <span class="required-indicator">*</span></label>
                                    <input type="tel" name="phone" id="phone" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter email address (optional)">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea name="address" id="address" class="form-control" rows="3"></textarea>
                            </div>
                    </fieldset>
                    
                    <fieldset class="form-section">
                        <legend class="form-section-title">
                            <i class="fas fa-briefcase"></i> Employment Information
                        </legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="occupation">Occupation</label>
                                    <input type="text" name="occupation" id="occupation" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="employer">Employer</label>
                                    <input type="text" name="employer" id="employer" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="monthly_income">Monthly Income (UGX)</label>
                                <input type="number" name="monthly_income" id="monthly_income" class="form-control" min="0" step="0.01" placeholder="Enter monthly income (optional)">
                            </div>
                    </fieldset>
                    
                    <fieldset class="form-section">
                        <legend class="form-section-title">
                            <i class="fas fa-users"></i> Next of Kin Information (Optional)
                        </legend>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="next_of_kin_name">Next of Kin Name</label>
                                <input type="text" name="next_of_kin_name" id="next_of_kin_name" class="form-control" placeholder="Enter next of kin full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="next_of_kin_phone">Next of Kin Phone</label>
                                <input type="tel" name="next_of_kin_phone" id="next_of_kin_phone" class="form-control" placeholder="Enter next of kin phone number">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="next_of_kin_relationship">Relationship</label>
                                <input type="text" name="next_of_kin_relationship" id="next_of_kin_relationship" class="form-control" placeholder="e.g., Spouse, Parent, Sibling">
                            </div>
                            
                            <div class="form-group">
                                <label for="next_of_kin_address">Next of Kin Address</label>
                                <textarea name="next_of_kin_address" id="next_of_kin_address" class="form-control" rows="2" placeholder="Enter next of kin address"></textarea>
                            </div>
                        </div>
                    </fieldset>
                    
                    <fieldset class="form-section">
                        <legend class="form-section-title">
                            <i class="fas fa-file-invoice-dollar"></i> Loan Application Details
                        </legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="loan_amount">Loan Amount (UGX) <span class="required-indicator">*</span></label>
                                    <input type="number" name="loan_amount" id="loan_amount" class="form-control" min="1000" step="0.01" required placeholder="Enter loan amount (minimum: 1,000)">
                                </div>
                                
                                <div class="form-group">
                                    <label for="interest_rate">Interest Rate (%)</label>
                                    <input type="number" name="interest_rate" id="interest_rate" class="form-control" min="0" max="100" step="0.01" value="<?php echo DEFAULT_INTEREST_RATE; ?>" placeholder="Enter interest rate">
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
                                    <label for="loan_term_months" id="term_months_label">Loan Term <span class="required-indicator">*</span></label>
                                    <input type="number" name="loan_term_months" id="loan_term_months" class="form-control" min="1" max="60" placeholder="Enter loan term (1-60)">
                                </div>
                                
                                <div class="form-group" id="term_weeks_group" style="display: none;">
                                    <label for="loan_term_weeks" id="term_weeks_label">Loan Term <span class="required-indicator">*</span></label>
                                    <input type="number" name="loan_term_weeks" id="loan_term_weeks" class="form-control" min="1" max="260" placeholder="Enter loan term (1-260)">
                                </div>
                                
                                <div class="form-group">
                                    <label for="application_date">Application Date <span class="required-indicator">*</span></label>
                                    <input type="date" name="application_date" id="application_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="loan_purpose">Loan Purpose</label>
                                <textarea name="loan_purpose" id="loan_purpose" class="form-control" rows="3" placeholder="Describe the purpose of this loan..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="security">Security/Collateral <span class="required-indicator">*</span></label>
                                <textarea name="security" id="security" class="form-control" rows="3" placeholder="Describe the security item provided by the client (e.g., land title, vehicle logbook, etc.)" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_documents">Upload Documents/Pictures</label>
                                <input type="file" name="customer_documents[]" id="customer_documents" class="form-control" multiple accept="image/*,.pdf,.doc,.docx">
                                <div class="help-text">You can upload multiple files. Allowed: Images (JPG, PNG, GIF) and Documents (PDF, DOC, DOCX). Max 10MB per file.</div>
                                <div id="file-list" style="margin-top: 1rem;"></div>
                            </div>
                            
                            <div id="document-descriptions" style="display: none;"></div>
                            
                            <div class="loan-summary" style="background: var(--light-blue); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-top: var(--spacing-md);">
                                <h4 style="margin-bottom: var(--spacing-md); color: var(--primary-blue);">
                                    <i class="fas fa-calculator"></i> Loan Summary
                                </h4>
                                <div class="summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md);">
                                    <div>
                                        <strong>Principal Amount:</strong>
                                        <div id="summary_principal" style="font-size: var(--font-size-lg); color: var(--primary-blue); font-weight: 600;">UGX 0.00</div>
                                    </div>
                                    <div>
                                        <strong>Interest Rate:</strong>
                                        <div id="summary_interest_rate" style="font-size: var(--font-size-lg); color: var(--primary-blue); font-weight: 600;">0%</div>
                                    </div>
                                    <div>
                                        <strong>Total Interest:</strong>
                                        <div id="summary_total_interest" style="font-size: var(--font-size-lg); color: var(--accent-gold); font-weight: 600;">UGX 0.00</div>
                                    </div>
                                    <div>
                                        <strong>Total Payback:</strong>
                                        <div id="summary_total_payback" style="font-size: var(--font-size-xl); color: var(--success-green); font-weight: 700;">UGX 0.00</div>
                                    </div>
                                    <div>
                                        <strong>Monthly Payment:</strong>
                                        <div id="summary_monthly_payment" style="font-size: var(--font-size-lg); color: var(--dark-gray); font-weight: 600;">UGX 0.00</div>
                                    </div>
                                </div>
                            </div>
                    </fieldset>
                    
                    <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-xl);">
                        <a href="<?php echo $is_admin ? 'customers.php' : '../officer/customers.php'; ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Customer & Create Loan
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0 && !value.startsWith('0')) {
                value = '0' + value;
            }
            e.target.value = value;
        });
        
        // Validate date of birth
        document.getElementById('date_of_birth').addEventListener('change', function(e) {
            const dob = new Date(e.target.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < <?php echo MIN_LOAN_AGE; ?>) {
                alert('Customer must be at least <?php echo MIN_LOAN_AGE; ?> years old to be eligible for loans.');
                e.target.value = '';
            }
        });
        
        // Loan calculation
        function calculateLoan() {
            const loanAmount = parseFloat(document.getElementById('loan_amount').value) || 0;
            const interestRate = parseFloat(document.getElementById('interest_rate').value) || <?php echo DEFAULT_INTEREST_RATE; ?>;
            const termType = document.getElementById('loan_term_type').value;
            const loanTermMonths = parseInt(document.getElementById('loan_term_months').value) || 0;
            const loanTermWeeks = parseInt(document.getElementById('loan_term_weeks').value) || 0;
            const loanTerm = termType === 'weeks' ? loanTermWeeks : loanTermMonths;
            const termInMonths = termType === 'weeks' ? loanTermWeeks / 4.33 : loanTermMonths;
            
            if (loanAmount > 0 && loanTerm > 0) {
                // Calculate total interest: Interest = (rate/100) * principal * loan_term
                const totalInterest = (interestRate / 100) * loanAmount * loanTerm;
                const totalPayback = loanAmount + totalInterest;
                const paymentAmount = termType === 'weeks' ? totalPayback / loanTermWeeks : totalPayback / loanTermMonths;
                
                // Update summary
                document.getElementById('summary_principal').textContent = 'UGX ' + loanAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('summary_interest_rate').textContent = interestRate.toFixed(2) + '%';
                document.getElementById('summary_total_interest').textContent = 'UGX ' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('summary_total_payback').textContent = 'UGX ' + totalPayback.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const paymentLabel = termType === 'weeks' ? 'Weekly Payment' : 'Monthly Payment';
                document.querySelector('#summary_monthly_payment').parentElement.querySelector('strong').textContent = paymentLabel + ':';
                document.getElementById('summary_monthly_payment').textContent = 'UGX ' + paymentAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('summary_principal').textContent = 'UGX 0.00';
                document.getElementById('summary_interest_rate').textContent = '0%';
                document.getElementById('summary_total_interest').textContent = 'UGX 0.00';
                document.getElementById('summary_total_payback').textContent = 'UGX 0.00';
                document.getElementById('summary_monthly_payment').textContent = 'UGX 0.00';
            }
        }
        
        // Toggle between months and weeks input
        function toggleTermInput() {
            const termType = document.getElementById('loan_term_type').value;
            const monthsGroup = document.getElementById('term_months_group');
            const weeksGroup = document.getElementById('term_weeks_group');
            const monthsInput = document.getElementById('loan_term_months');
            const weeksInput = document.getElementById('loan_term_weeks');
            const monthsLabel = document.getElementById('term_months_label');
            const weeksLabel = document.getElementById('term_weeks_label');
            
            if (termType === 'weeks') {
                monthsGroup.style.display = 'none';
                weeksGroup.style.display = 'block';
                monthsInput.removeAttribute('required');
                weeksInput.setAttribute('required', 'required');
                weeksLabel.innerHTML = 'Loan Term (Weeks) <span class="required-indicator">*</span>';
            } else {
                monthsGroup.style.display = 'block';
                weeksGroup.style.display = 'none';
                weeksInput.removeAttribute('required');
                monthsInput.setAttribute('required', 'required');
                monthsLabel.innerHTML = 'Loan Term (Months) <span class="required-indicator">*</span>';
            }
            
            // Clear the hidden input and recalculate
            if (termType === 'weeks') {
                monthsInput.value = '';
            } else {
                weeksInput.value = '';
            }
            calculateLoan();
        }
        
        // Add event listeners for loan calculation
        document.getElementById('loan_amount').addEventListener('input', calculateLoan);
        document.getElementById('interest_rate').addEventListener('input', calculateLoan);
        document.getElementById('loan_term_months').addEventListener('input', calculateLoan);
        document.getElementById('loan_term_weeks').addEventListener('input', calculateLoan);
        document.getElementById('loan_term_type').addEventListener('change', function() {
            toggleTermInput();
        });
        
        document.getElementById('national_id').addEventListener('blur', function(e) {
            const nationalId = e.target.value.toUpperCase().trim();
            if (nationalId.length > 0) {
                const gender = document.getElementById('gender').value;
                const dob = document.getElementById('date_of_birth').value;
                
                if (!gender || !dob) {
                    alert('Please select gender and date of birth first to validate National ID');
                    return;
                }
                
                if (nationalId.length !== 13) {
                    alert('National ID must be exactly 13 characters');
                    e.target.value = '';
                    return;
                }
                
                const firstChar = nationalId[0];
                const genderChar = nationalId[1];
                const yearDigits = nationalId.substring(2, 4);
                const middleSection = nationalId.substring(4, 10);
                const lastLetters = nationalId.substring(10, 13);
                
                const genderCode = gender[0].toUpperCase();
                const birthYear = dob.substring(2, 4);
                
                if (firstChar !== 'C') {
                    alert('National ID must start with "C"');
                    e.target.value = '';
                    return;
                }
                
                if (genderChar !== genderCode || (genderChar !== 'M' && genderChar !== 'F')) {
                    alert('National ID gender code (2nd character) must be M for Male or F for Female and match selected gender');
                    e.target.value = '';
                    return;
                }
                
                if (yearDigits !== birthYear) {
                    alert('National ID year (characters 3-4) does not match birth year');
                    e.target.value = '';
                    return;
                }
                
                if (!/^[A-Z0-9]{6}$/.test(middleSection)) {
                    alert('National ID middle section (characters 5-10) must be 6 alphanumeric characters (mostly numbers)');
                    e.target.value = '';
                    return;
                }
                
                if (!/^[A-Z]{3}$/.test(lastLetters)) {
                    alert('National ID ending (last 3 characters) must be exactly 3 letters');
                    e.target.value = '';
                    return;
                }
                
                e.target.value = nationalId;
            }
        });
        
        // Validate application date (no backdating)
        document.getElementById('application_date').addEventListener('change', function(e) {
            const selectedDate = new Date(e.target.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('Application date cannot be in the past. Please select today or a future date.');
                e.target.value = '<?php echo date('Y-m-d'); ?>';
            }
        });
        
        // Initial calculation
        calculateLoan();
        
        // File upload handling
        document.getElementById('customer_documents').addEventListener('change', function(e) {
            const files = e.target.files;
            const fileList = document.getElementById('file-list');
            const descriptionsDiv = document.getElementById('document-descriptions');
            
            fileList.innerHTML = '';
            descriptionsDiv.innerHTML = '';
            
            if (files.length > 0) {
                descriptionsDiv.style.display = 'block';
                
                Array.from(files).forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.style.cssText = 'padding: 0.5rem; background: #f3f4f6; border-radius: 4px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;';
                    fileItem.innerHTML = `<span><i class="fas fa-file"></i> ${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>`;
                    fileList.appendChild(fileItem);
                    
                    const descInput = document.createElement('input');
                    descInput.type = 'text';
                    descInput.name = 'document_descriptions[]';
                    descInput.className = 'form-control';
                    descInput.style.cssText = 'margin-bottom: 0.5rem;';
                    descInput.placeholder = `Description for ${file.name} (optional)`;
                    descriptionsDiv.appendChild(descInput);
                });
            } else {
                descriptionsDiv.style.display = 'none';
            }
        });
        
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


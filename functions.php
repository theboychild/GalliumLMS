<?php
// Gallium Solutions Limited - Loan Management System Functions

// ============================================
// Authentication & Authorization Functions
// ============================================

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Check if user is officer
function isOfficer() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'officer';
}

// Check if user is customer
function isCustomer() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer';
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $redirect = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php';
        $_SESSION['redirect_url'] = $redirect;
        // Determine which login page based on current path
        $current_path = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($current_path, '/admin/') !== false) {
            header("Location: login.php");
        } elseif (strpos($current_path, '/officer/') !== false) {
            header("Location: login.php");
        } else {
            header("Location: login.php");
        }
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        // Destroy session if wrong user type
        session_destroy();
        header("Location: ../admin/login.php?error=unauthorized");
        exit();
    }
}

function requireOfficer() {
    requireLogin();
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'officer') {
        // Destroy session if wrong user type
        session_destroy();
        header("Location: ../officer/login.php?error=unauthorized");
        exit();
    }
}

function requireAdminOrOfficer() {
    requireLogin();
    if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'officer')) {
        // Destroy session if wrong user type
        session_destroy();
        // Determine which login page to redirect to based on current path
        $current_path = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($current_path, '/admin/') !== false) {
            header("Location: ../admin/login.php?error=unauthorized");
        } elseif (strpos($current_path, '/officer/') !== false) {
            header("Location: ../officer/login.php?error=unauthorized");
        } else {
            header("Location: ../index.php");
        }
        exit();
    }
}

// ============================================
// Validation Functions
// ============================================

// Validate age (must be 18 or older)
function validateAge($date_of_birth) {
    $birth_date = new DateTime($date_of_birth);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    
    if ($age < MIN_LOAN_AGE) {
        return [
            'valid' => false,
            'message' => "Applicant must be at least " . MIN_LOAN_AGE . " years old. Current age: $age years."
        ];
    }
    
    return ['valid' => true, 'age' => $age];
}

// Validate date (no backdating allowed)
function validateDate($date, $field_name = 'Date') {
    $input_date = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $input_date->setTime(0, 0, 0);
    
    if ($input_date < $today) {
        return [
            'valid' => false,
            'message' => "$field_name cannot be in the past. Please select today's date or a future date."
        ];
    }
    
    return ['valid' => true];
}

// Validate application date (must be today or future)
function validateApplicationDate($date) {
    return validateDate($date, 'Application date');
}

// Validate National ID format: C + G + YY + 6 alphanumeric + 3 letters = 13 characters total
// C = First character (always 'C')
// G = Gender (M for Male, F for Female)
// YY = Last 2 digits of birth year
// Middle = 6 alphanumeric characters (positions 4-9, mostly numbers)
// Last = 3 letters (positions 10-12)
function validateNationalId($national_id, $gender, $date_of_birth) {
    if (empty($national_id)) {
        return ['valid' => true];
    }
    
    if (strlen($national_id) !== 13) {
        return [
            'valid' => false,
            'message' => 'National ID must be exactly 13 characters'
        ];
    }
    
    $national_id = strtoupper($national_id);
    $gender_code = strtoupper($gender[0]);
    
    $first_char = $national_id[0];
    $gender_char = $national_id[1];
    $year_digits = substr($national_id, 2, 2);
    $middle_section = substr($national_id, 4, 6);
    $last_letters = substr($national_id, 10, 3);
    
    if ($first_char !== 'C') {
        return [
            'valid' => false,
            'message' => 'National ID must start with "C"'
        ];
    }
    
    if ($gender_char !== $gender_code || ($gender_char !== 'M' && $gender_char !== 'F')) {
        return [
            'valid' => false,
            'message' => 'National ID gender code (2nd character) must be M for Male or F for Female and match selected gender'
        ];
    }
    
    $birth_year = date('y', strtotime($date_of_birth));
    if ($year_digits !== $birth_year) {
        return [
            'valid' => false,
            'message' => 'National ID year (characters 3-4) does not match birth year'
        ];
    }
    
    if (!preg_match('/^[A-Z0-9]{6}$/', $middle_section)) {
        return [
            'valid' => false,
            'message' => 'National ID middle section (characters 5-10) must be 6 alphanumeric characters (mostly numbers)'
        ];
    }
    
    if (!preg_match('/^[A-Z]{3}$/', $last_letters)) {
        return [
            'valid' => false,
            'message' => 'National ID ending (last 3 characters) must be 3 letters'
        ];
    }
    
    return ['valid' => true];
}

// Sanitize input data
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (basic validation)
function validatePhone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's between 9-15 digits
    return strlen($phone) >= 9 && strlen($phone) <= 15;
}

// Validate loan amount
function validateLoanAmount($amount) {
    $amount = floatval($amount);
    if ($amount <= 0) {
        return ['valid' => false, 'message' => 'Loan amount must be greater than zero.'];
    }
    if ($amount < 1000) {
        return ['valid' => false, 'message' => 'Minimum loan amount is UGX 1,000.'];
    }
    if ($amount > 100000000) {
        return ['valid' => false, 'message' => 'Maximum loan amount is UGX 100,000,000.'];
    }
    return ['valid' => true, 'amount' => $amount];
}

// ============================================
// User Management Functions
// ============================================

// Get user by ID
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get user by email
function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Register new user
function registerUser($username, $email, $phone, $password, $user_type = 'customer') {
    global $pdo;
    
    // Check if database connection exists
    if (!isset($pdo)) {
        return ['success' => false, 'message' => 'Database connection not available. Please check your configuration.'];
    }
    
    try {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            return ['success' => false, 'message' => 'Database tables not found. Please import the database schema first. See gallium_loans_localhost.sql'];
        }
        
        // Check if email or phone already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email or phone number already registered.'];
        }
        
        // Validate admin password requirements
        if ($user_type === 'admin') {
            if (strlen($password) < 10) {
                return ['success' => false, 'message' => 'Admin password must be at least 10 characters long.'];
            }
            if (!preg_match('/^A/i', $password)) {
                return ['success' => false, 'message' => 'Admin password must begin with the letter "A".'];
            }
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, phone, password, user_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $phone, $hashed_password, $user_type])) {
            $user_id = $pdo->lastInsertId();
            
            // Try to send notification (don't fail if this fails)
            try {
                sendNotification($user_id, 'Welcome to ' . APP_NAME, 'Thank you for registering with ' . APP_NAME . '.');
            } catch (Exception $e) {
                // Notification failure shouldn't stop registration
                error_log("Notification error: " . $e->getMessage());
            }
            
            return ['success' => true, 'user_id' => $user_id];
        }
        
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        
        // Provide more helpful error messages
        $error_code = $e->getCode();
        if ($error_code == '42S02') {
            return ['success' => false, 'message' => 'Database table "users" not found. Please import the database schema (gallium_loans_localhost.sql) first.'];
        } elseif ($error_code == '42000') {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage() . '. Please check your database configuration.'];
        } else {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// ============================================
// Customer Management Functions
// ============================================

// Create customer profile
function createCustomer($user_id, $customer_name, $national_id, $date_of_birth, $gender, $phone, $email, $address, $occupation, $employer, $monthly_income, $security = null, $next_of_kin_name = null, $next_of_kin_phone = null, $next_of_kin_relationship = null, $next_of_kin_address = null) {
    global $pdo;
    
    try {
        // Validate age
        $age_validation = validateAge($date_of_birth);
        if (!$age_validation['valid']) {
            return ['success' => false, 'message' => $age_validation['message']];
        }
        
        // Check if national ID already exists
        if (!empty($national_id)) {
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE national_id = ?");
            $stmt->execute([$national_id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'National ID already registered.'];
            }
        }
        
        // Check if security column exists, if not, don't include it
        $columns = "user_id, customer_name, national_id, date_of_birth, gender, phone, email, address, occupation, employer, monthly_income";
        $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
        $params = [$user_id, $customer_name, $national_id, $date_of_birth, $gender, $phone, $email, $address, $occupation, $employer, $monthly_income];
        
        // Check if security column exists
        try {
            $check_stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'security'");
            if ($check_stmt->rowCount() > 0) {
                $columns .= ", security";
                $values .= ", ?";
                $params[] = $security;
            }
        } catch (PDOException $e) {
            // Column doesn't exist, continue without it
        }
        
        // Check if next_of_kin columns exist
        try {
            $check_stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'next_of_kin_name'");
            if ($check_stmt->rowCount() > 0 && !empty($next_of_kin_name)) {
                $columns .= ", next_of_kin_name, next_of_kin_phone, next_of_kin_relationship, next_of_kin_address";
                $values .= ", ?, ?, ?, ?";
                $params[] = $next_of_kin_name;
                $params[] = $next_of_kin_phone;
                $params[] = $next_of_kin_relationship;
                $params[] = $next_of_kin_address;
            }
        } catch (PDOException $e) {
            // Columns don't exist, continue without them
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO customers ($columns)
            VALUES ($values)
        ");
        
        if ($stmt->execute($params)) {
            $customer_id = $pdo->lastInsertId();
            
            // Notify all admins when a new customer is added
            try {
                $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' AND is_active = 1");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($admins as $admin_id) {
                    sendNotification(
                        $admin_id,
                        'New Customer Added',
                        "A new customer '$customer_name' has been added to the system. Customer ID: #$customer_id",
                        'system',
                        null // sender_id - system notification
                    );
                }
            } catch (PDOException $e) {
                error_log("Failed to send customer notification: " . $e->getMessage());
            }
            
            return ['success' => true, 'customer_id' => $customer_id];
        }
        
        return ['success' => false, 'message' => 'Failed to create customer profile.'];
    } catch (PDOException $e) {
        error_log("Create customer error: " . $e->getMessage());
        
        // Provide more helpful error messages
        $error_code = $e->getCode();
        $error_message = $e->getMessage();
        
        if ($error_code == '42S02') {
            return ['success' => false, 'message' => 'Database table "customers" not found. Please import the database schema (gallium_loans_localhost.sql) first.'];
        } elseif (strpos($error_message, 'Column') !== false && strpos($error_message, "doesn't exist") !== false) {
            return ['success' => false, 'message' => 'Database table structure is incomplete. Please check your database schema.'];
        } elseif (strpos($error_message, 'Duplicate entry') !== false) {
            if (strpos($error_message, 'phone') !== false) {
                return ['success' => false, 'message' => 'A customer with this phone number already exists.'];
            } elseif (strpos($error_message, 'national_id') !== false) {
                return ['success' => false, 'message' => 'A customer with this National ID already exists.'];
            }
            return ['success' => false, 'message' => 'Duplicate entry detected. This customer may already exist.'];
        } elseif ($error_code == '42000' || $error_code == 'HY000') {
            return ['success' => false, 'message' => 'Database error: ' . $error_message . '. Please check your database configuration.'];
        } else {
            // In development, show more details; in production, show generic message
            if (getenv('APP_ENV') !== 'production') {
                return ['success' => false, 'message' => 'Database error: ' . $error_message . ' (Code: ' . $error_code . ')'];
            }
            return ['success' => false, 'message' => 'Database error occurred. Please contact the administrator.'];
        }
    }
}

// Get customer by ID
function getCustomerById($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get customer by user ID
function getCustomerByUserId($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all customers
function getAllCustomers($limit = null) {
    global $pdo;
    $sql = "SELECT c.*, u.email as user_email, u.is_active 
            FROM customers c 
            LEFT JOIN users u ON c.user_id = u.user_id 
            ORDER BY c.created_at DESC";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// Loan Management Functions
// ============================================

// Create loan application
function createLoanApplication($customer_id, $loan_amount, $interest_rate, $loan_term_months, $purpose, $application_date) {
    global $pdo;
    
    try {
        // Validate loan amount
        $amount_validation = validateLoanAmount($loan_amount);
        if (!$amount_validation['valid']) {
            return ['success' => false, 'message' => $amount_validation['message']];
        }
        
        // Validate application date (no backdating)
        $date_validation = validateApplicationDate($application_date);
        if (!$date_validation['valid']) {
            return ['success' => false, 'message' => $date_validation['message']];
        }
        
        // Validate customer age
        $customer = getCustomerById($customer_id);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }
        
        $age_validation = validateAge($customer['date_of_birth']);
        if (!$age_validation['valid']) {
            return ['success' => false, 'message' => $age_validation['message']];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO loans (customer_id, loan_amount, interest_rate, loan_term_months, purpose, application_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$customer_id, $amount_validation['amount'], $interest_rate, $loan_term_months, $purpose, $application_date])) {
            $loan_id = $pdo->lastInsertId();
            
            // Generate loan schedule
            generateLoanSchedule($loan_id, $amount_validation['amount'], $interest_rate, $loan_term_months, $application_date);
            
            // Send notification
            if ($customer['user_id']) {
                sendNotification($customer['user_id'], 'Loan Application Submitted', "Your loan application #$loan_id has been submitted and is pending review.");
            }
            
            return ['success' => true, 'loan_id' => $loan_id];
        }
        
        return ['success' => false, 'message' => 'Failed to create loan application.'];
    } catch (PDOException $e) {
        error_log("Create loan error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// Generate loan payment schedule
// Uses simple interest: Interest = Principal * (Rate/100) * (Months/12)
// Total Payback = Principal + Interest
// Monthly Payment = Total Payback / Months
function generateLoanSchedule($loan_id, $loan_amount, $interest_rate, $loan_term, $start_date, $term_type = 'months') {
    global $pdo;
    
    try {
        // Calculate total interest: Interest = (rate/100) * principal * loan_term
        if ($term_type === 'weeks') {
            // Calculate total interest: (rate/100) * principal * loan_term_weeks
            $total_interest = ($interest_rate / 100) * $loan_amount * $loan_term;
            $total_payback = $loan_amount + $total_interest;
            $payment_per_period = $total_payback / $loan_term;
            
            // Calculate principal and interest per week
            $principal_per_period = $loan_amount / $loan_term;
            $interest_per_period = $total_interest / $loan_term;
            
            $start = new DateTime($start_date);
            
            for ($i = 1; $i <= $loan_term; $i++) {
                $due_date = clone $start;
                $due_date->modify("+" . ($i * 7) . " days"); // 7 days per week
                
                // For the last payment, adjust to ensure exact total
                if ($i == $loan_term) {
                    $remaining_principal = $loan_amount - ($principal_per_period * ($loan_term - 1));
                    $remaining_interest = $total_interest - ($interest_per_period * ($loan_term - 1));
                    $principal_amount = $remaining_principal;
                    $interest_amount = $remaining_interest;
                    $payment_amount = $principal_amount + $interest_amount;
                } else {
                    $principal_amount = $principal_per_period;
                    $interest_amount = $interest_per_period;
                    $payment_amount = $payment_per_period;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO loan_schedule (loan_id, installment_number, due_date, principal_amount, interest_amount, total_amount)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $loan_id,
                    $i,
                    $due_date->format('Y-m-d'),
                    round($principal_amount, 2),
                    round($interest_amount, 2),
                    round($payment_amount, 2)
                ]);
            }
        } else {
            // Months: default behavior
            // Calculate total interest: (rate/100) * principal * loan_term_months
            $total_interest = ($interest_rate / 100) * $loan_amount * $loan_term;
            $total_payback = $loan_amount + $total_interest;
            $monthly_payment = $total_payback / $loan_term;
            
            // Calculate principal and interest per month
            $principal_per_month = $loan_amount / $loan_term;
            $interest_per_month = $total_interest / $loan_term;
            
            $start = new DateTime($start_date);
            
            for ($i = 1; $i <= $loan_term; $i++) {
                $due_date = clone $start;
                $due_date->modify("+$i months");
                
                // For the last payment, adjust to ensure exact total
                if ($i == $loan_term) {
                    $remaining_principal = $loan_amount - ($principal_per_month * ($loan_term - 1));
                    $remaining_interest = $total_interest - ($interest_per_month * ($loan_term - 1));
                    $principal_amount = $remaining_principal;
                    $interest_amount = $remaining_interest;
                    $payment_amount = $principal_amount + $interest_amount;
                } else {
                    $principal_amount = $principal_per_month;
                    $interest_amount = $interest_per_month;
                    $payment_amount = $monthly_payment;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO loan_schedule (loan_id, installment_number, due_date, principal_amount, interest_amount, total_amount)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $loan_id,
                    $i,
                    $due_date->format('Y-m-d'),
                    round($principal_amount, 2),
                    round($interest_amount, 2),
                    round($payment_amount, 2)
                ]);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Generate schedule error: " . $e->getMessage());
        return false;
    }
}

// Get loan by ID
function getLoanById($loan_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT l.*, c.customer_name, c.phone, c.email, c.national_id,
               u.username as officer_name
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        LEFT JOIN users u ON l.officer_id = u.user_id
        WHERE l.loan_id = ?
    ");
    $stmt->execute([$loan_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all loans
function getAllLoans($status = null, $officer_id = null, $limit = null) {
    global $pdo;
    
    $sql = "SELECT l.*, c.customer_name, c.phone, u.username as officer_name
            FROM loans l
            JOIN customers c ON l.customer_id = c.customer_id
            LEFT JOIN users u ON l.officer_id = u.user_id
            WHERE 1=1";
    
    $params = [];
    
    if ($status) {
        $sql .= " AND l.status = ?";
        $params[] = $status;
    }
    
    if ($officer_id) {
        $sql .= " AND l.officer_id = ?";
        $params[] = $officer_id;
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Approve loan
function approveLoan($loan_id, $officer_id, $approval_date = null) {
    global $pdo;
    
    try {
        if (!$approval_date) {
            $approval_date = date('Y-m-d');
        }
        
        // Validate approval date (no backdating)
        $date_validation = validateDate($approval_date, 'Approval date');
        if (!$date_validation['valid']) {
            return ['success' => false, 'message' => $date_validation['message']];
        }
        
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET status = 'approved', 
                officer_id = ?, 
                approval_date = ?
            WHERE loan_id = ? AND status = 'pending'
        ");
        
        if ($stmt->execute([$officer_id, $approval_date, $loan_id])) {
            $loan = getLoanById($loan_id);
            if ($loan && $loan['customer_id']) {
                $customer = getCustomerById($loan['customer_id']);
                if ($customer && $customer['user_id']) {
                    sendNotification($customer['user_id'], 'Loan Approved', "Your loan application #$loan_id has been approved.");
                }
            }
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Failed to approve loan.'];
    } catch (PDOException $e) {
        error_log("Approve loan error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// Disburse loan (activate loan - change from approved to active)
function disburseLoan($loan_id, $officer_id, $disbursement_date = null) {
    global $pdo;
    
    try {
        if (!$disbursement_date) {
            $disbursement_date = date('Y-m-d');
        }
        
        // Validate disbursement date (no backdating)
        $date_validation = validateDate($disbursement_date, 'Disbursement date');
        if (!$date_validation['valid']) {
            return ['success' => false, 'message' => $date_validation['message']];
        }
        
        // Check if loan exists and is approved
        $loan = getLoanById($loan_id);
        if (!$loan) {
            return ['success' => false, 'message' => 'Loan not found.'];
        }
        
        if ($loan['status'] !== 'approved') {
            return ['success' => false, 'message' => 'Loan must be approved before disbursement. Current status: ' . $loan['status']];
        }
        
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET status = 'active', 
                disbursement_date = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE loan_id = ? AND status = 'approved'
        ");
        
        if ($stmt->execute([$disbursement_date, $loan_id])) {
            // Send notification to customer
            if ($loan['customer_id']) {
                $customer = getCustomerById($loan['customer_id']);
                if ($customer && $customer['user_id']) {
                    sendNotification($customer['user_id'], 'Loan Disbursed', "Your loan #$loan_id has been disbursed and is now active. Amount: UGX " . number_format(round($loan['loan_amount']), 0));
                }
            }
            
            // Log audit
            logAudit($officer_id, 'Disburse Loan', 'loans', $loan_id, null, ['disbursement_date' => $disbursement_date]);
            
            return ['success' => true, 'message' => 'Loan disbursed successfully.'];
        }
        
        return ['success' => false, 'message' => 'Failed to disburse loan.'];
    } catch (PDOException $e) {
        error_log("Disburse loan error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// Reject loan
function rejectLoan($loan_id, $officer_id, $rejection_reason) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET status = 'rejected', 
                officer_id = ?, 
                rejection_reason = ?
            WHERE loan_id = ? AND status = 'pending'
        ");
        
        if ($stmt->execute([$officer_id, $rejection_reason, $loan_id])) {
            $loan = getLoanById($loan_id);
            if ($loan && $loan['customer_id']) {
                $customer = getCustomerById($loan['customer_id']);
                if ($customer && $customer['user_id']) {
                    sendNotification($customer['user_id'], 'Loan Rejected', "Your loan application #$loan_id has been rejected. Reason: $rejection_reason");
                }
            }
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Failed to reject loan.'];
    } catch (PDOException $e) {
        error_log("Reject loan error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// ============================================
// Payment Management Functions
// ============================================

function recordLoanPayment($loan_id, $payment_amount, $payment_date, $payment_method = 'cash', $transaction_reference = null, $received_by = null, $notes = null) {
    global $pdo;
    
    try {
        $date_validation = validateDate($payment_date, 'Payment date');
        if (!$date_validation['valid']) {
            return ['success' => false, 'message' => $date_validation['message']];
        }
        
        $loan = getLoanById($loan_id);
        if (!$loan || $loan['status'] !== 'active') {
            return ['success' => false, 'message' => 'Loan not found or not active.'];
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM loan_schedule 
            WHERE loan_id = ? AND status IN ('pending', 'overdue', 'partial')
            ORDER BY due_date ASC
        ");
        $stmt->execute([$loan_id]);
        $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($installments)) {
            return ['success' => false, 'message' => 'No pending installments found.'];
        }
        
        $remaining_payment = $payment_amount;
        $total_principal = 0;
        $total_interest = 0;
        $overpayment = 0;
        
        foreach ($installments as $installment) {
            if ($remaining_payment <= 0) break;
            
            $outstanding = $installment['total_amount'] - $installment['paid_amount'];
            $payment_for_installment = min($remaining_payment, $outstanding);
            
            $new_paid_amount = $installment['paid_amount'] + $payment_for_installment;
            $new_status = ($new_paid_amount >= $installment['total_amount']) ? 'paid' : 'partial';
            
            $stmt = $pdo->prepare("
                UPDATE loan_schedule 
                SET paid_amount = ?, 
                    status = ?,
                    paid_date = CASE WHEN ? = 'paid' THEN ? ELSE paid_date END
                WHERE schedule_id = ?
            ");
            $stmt->execute([$new_paid_amount, $new_status, $new_status, $payment_date, $installment['schedule_id']]);
            
            $principal_portion = ($payment_for_installment / $installment['total_amount']) * $installment['principal_amount'];
            $interest_portion = ($payment_for_installment / $installment['total_amount']) * $installment['interest_amount'];
            
            $total_principal += round($principal_portion, 2);
            $total_interest += round($interest_portion, 2);
            
            $remaining_payment -= $payment_for_installment;
        }
        
        // Track overpayment if any
        if ($remaining_payment > 0) {
            $overpayment = $remaining_payment;
            // Store overpayment in a separate field or table
            // For now, we'll add it to the notes
            if ($notes) {
                $notes .= " | Overpayment: " . formatCurrency($overpayment);
            } else {
                $notes = "Overpayment: " . formatCurrency($overpayment);
            }
        }
        
        // Check if loan_payments table has overpayment column
        $col_check = $pdo->query("SHOW COLUMNS FROM loan_payments LIKE 'overpayment'")->fetch();
        
        if ($col_check) {
            $stmt = $pdo->prepare("
                INSERT INTO loan_payments (loan_id, payment_amount, principal_amount, interest_amount, payment_date, payment_method, transaction_reference, received_by, notes, overpayment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $loan_id,
                $payment_amount,
                $total_principal,
                $total_interest,
                $payment_date,
                $payment_method,
                $transaction_reference,
                $received_by,
                $notes,
                $overpayment
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO loan_payments (loan_id, payment_amount, principal_amount, interest_amount, payment_date, payment_method, transaction_reference, received_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $loan_id,
                $payment_amount,
                $total_principal,
                $total_interest,
                $payment_date,
                $payment_method,
                $transaction_reference,
                $received_by,
                $notes
            ]);
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unpaid_count,
                   SUM(total_amount - paid_amount) as remaining_balance
            FROM loan_schedule 
            WHERE loan_id = ? AND status != 'paid'
        ");
        $stmt->execute([$loan_id]);
        $payment_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment_status['unpaid_count'] == 0 && ($payment_status['remaining_balance'] == 0 || $payment_status['remaining_balance'] === null)) {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'completed' WHERE loan_id = ? AND status = 'active'");
            $stmt->execute([$loan_id]);
        }
        
        // Notify all admins when a payment is recorded
        try {
            $loan_info = getLoanById($loan_id);
            if ($loan_info) {
                $customer = getCustomerById($loan_info['customer_id']);
                $customer_name = $customer['customer_name'] ?? 'Unknown';
                
                $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' AND is_active = 1");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($admins as $admin_id) {
                    sendNotification(
                        $admin_id,
                        'New Payment Recorded',
                        "Payment of UGX " . number_format(round($payment_amount), 0) . " received from customer '$customer_name' for Loan ID: #$loan_id",
                        'system',
                        null // sender_id - system notification
                    );
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to send payment notification: " . $e->getMessage());
        }
        
        $message = 'Payment recorded successfully.';
        if ($overpayment > 0) {
            $message .= ' Overpayment: ' . formatCurrency($overpayment);
        }
        
        return ['success' => true, 'message' => $message, 'overpayment' => $overpayment];
    } catch (PDOException $e) {
        error_log("Record payment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// Get loan payments
function getLoanPayments($loan_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT lp.*, u.username as received_by_name
        FROM loan_payments lp
        LEFT JOIN users u ON lp.received_by = u.user_id
        WHERE lp.loan_id = ?
        ORDER BY lp.payment_date DESC, lp.created_at DESC
    ");
    $stmt->execute([$loan_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get loan schedule
function getLoanSchedule($loan_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM loan_schedule 
        WHERE loan_id = ?
        ORDER BY installment_number ASC
    ");
    $stmt->execute([$loan_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// Notification Functions
// ============================================

function sendNotification($user_id, $title, $message, $type = 'system', $sender_id = null) {
    global $pdo;
    
    try {
        // Validate user_id
        if (empty($user_id) || !is_numeric($user_id)) {
            error_log("Invalid user_id for notification: " . var_export($user_id, true));
            return false;
        }
        
        // Check if notifications table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
        if (!$table_check) {
            error_log("Notifications table does not exist");
            return false;
        }
        
        // Check if user exists
        $user_check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $user_check->execute([$user_id]);
        if (!$user_check->fetch()) {
            error_log("User ID $user_id does not exist for notification");
            return false;
        }
        
        // Check if sender_id column exists
        $sender_col_check = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'sender_id'")->fetch();
        $created_by_col_check = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by'")->fetch();
        
        if ($sender_col_check && $created_by_col_check) {
            // Both columns exist, use full query
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, is_read, sender_id, created_by, created_at)
                VALUES (?, ?, ?, ?, 0, ?, ?, NOW())
            ");
            $result = $stmt->execute([$user_id, $title, $message, $type, $sender_id, $sender_id]);
        } elseif ($sender_col_check) {
            // Only sender_id exists
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, is_read, sender_id, created_at)
                VALUES (?, ?, ?, ?, 0, ?, NOW())
            ");
            $result = $stmt->execute([$user_id, $title, $message, $type, $sender_id]);
        } else {
            // Neither column exists, use basic query
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $result = $stmt->execute([$user_id, $title, $message, $type]);
        }
        
        if (!$result) {
            error_log("Failed to insert notification. User ID: $user_id, Title: $title");
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage() . " | User ID: $user_id | Title: $title | Sender ID: " . ($sender_id ?? 'null'));
        return false;
    }
}

function sendNotificationReply($notification_id, $from_user_id, $reply_message) {
    global $pdo;
    
    try {
        // Check if created_by column exists
        $col_check = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by'")->fetch();
        
        if ($col_check) {
            $notification = $pdo->prepare("SELECT user_id, sender_id, created_by FROM notifications WHERE notification_id = ?");
        } else {
            $notification = $pdo->prepare("SELECT user_id, sender_id, sender_id as created_by FROM notifications WHERE notification_id = ?");
        }
        $notification->execute([$notification_id]);
        $notif_data = $notification->fetch(PDO::FETCH_ASSOC);
        
        if (!$notif_data) {
            return ['success' => false, 'message' => 'Notification not found'];
        }
        
        // Determine who to send reply to: sender_id first, then created_by, then original user_id
        $to_user_id = $notif_data['sender_id'] ?? $notif_data['created_by'] ?? $notif_data['user_id'];
        
        // If notification was sent to current user, reply goes to sender/creator
        // If notification was sent by current user, they can't reply to themselves
        if ($notif_data['user_id'] == $from_user_id) {
            // Current user received this notification, reply to sender/creator
            $to_user_id = $notif_data['sender_id'] ?? $notif_data['created_by'] ?? null;
        } else {
            // Current user sent this notification, reply goes to recipient
            $to_user_id = $notif_data['user_id'];
        }
        
        if (!$to_user_id || $from_user_id == $to_user_id) {
            return ['success' => false, 'message' => 'Cannot reply to your own notification'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notification_replies (notification_id, from_user_id, to_user_id, reply_message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$notification_id, $from_user_id, $to_user_id, $reply_message]);
        
        $from_user = getUserById($from_user_id);
        $from_name = $from_user ? $from_user['username'] : 'Unknown';
        
        $notification_title = $pdo->prepare("SELECT title FROM notifications WHERE notification_id = ?");
        $notification_title->execute([$notification_id]);
        $notif_title = $notification_title->fetchColumn();
        
        $reply_title = "Reply to: " . ($notif_title ?: 'Your notification');
        $reply_message_text = "Reply from $from_name:\n\n$reply_message";
        
        sendNotification($to_user_id, $reply_title, $reply_message_text, 'system', $from_user_id);
        
        return ['success' => true, 'message' => 'Reply sent successfully'];
    } catch (PDOException $e) {
        error_log("Reply error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function getNotificationReplies($notification_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT nr.*, u.username as from_username
            FROM notification_replies nr
            LEFT JOIN users u ON nr.from_user_id = u.user_id
            WHERE nr.notification_id = ?
            ORDER BY nr.created_at ASC
        ");
        $stmt->execute([$notification_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get replies error: " . $e->getMessage());
        return [];
    }
}

function getUserNotifications($user_id, $limit = 10) {
    global $pdo;
    
    try {
        // Check if created_by column exists
        $columns = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by'")->fetch();
        
        if ($columns) {
            // Column exists, use full query
            $sql = "SELECT n.*, 
                    sender.username as sender_username,
                    creator.username as creator_username
                    FROM notifications n
                    LEFT JOIN users sender ON n.sender_id = sender.user_id
                    LEFT JOIN users creator ON n.created_by = creator.user_id
                    WHERE n.user_id = ?
                    ORDER BY n.created_at DESC
                    LIMIT ?";
        } else {
            // Column doesn't exist, use fallback query without created_by
            $sql = "SELECT n.*, 
                    sender.username as sender_username,
                    NULL as creator_username
                    FROM notifications n
                    LEFT JOIN users sender ON n.sender_id = sender.user_id
                    WHERE n.user_id = ?
                    ORDER BY n.created_at DESC
                    LIMIT ?";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If created_by column doesn't exist, set it to sender_id for compatibility
        if (!$columns) {
            foreach ($notifications as &$notif) {
                $notif['created_by'] = $notif['sender_id'] ?? null;
            }
        }
        
        return $notifications;
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsRead($notification_id) {
    global $pdo;
    
    if (!is_numeric($notification_id)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1
            WHERE notification_id = ?
        ");
        return $stmt->execute([$notification_id]);
    } catch (PDOException $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// Utility Functions
// ============================================

// Format currency
function formatCurrency($amount, $show_currency = false) {
    $rounded = round($amount);
    if ($show_currency) {
        return 'UGX ' . number_format($rounded, 0);
    }
    return number_format($rounded, 0);
}

function uploadCustomerDocument($customer_id, $file, $description = '', $uploaded_by = null) {
    global $pdo;
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 10 * 1024 * 1024;
    
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error (code: ' . $file['error'] . ')';
        return ['success' => false, 'message' => $error_msg];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds 10MB limit'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: Images (JPG, PNG, GIF) and Documents (PDF, DOC, DOCX)'];
    }
    
    // Ensure uploads directory exists
    $base_upload_dir = __DIR__ . '/../uploads/customers/';
    if (!is_dir($base_upload_dir)) {
        if (!mkdir($base_upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    $upload_dir = $base_upload_dir . $customer_id . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create customer upload directory'];
        }
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('doc_', true) . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $error_msg = 'Failed to save file';
        if (!is_writable($upload_dir)) {
            $error_msg .= ' - Directory is not writable';
        }
        return ['success' => false, 'message' => $error_msg];
    }
    
    // Store relative path from project root
    $relative_path = 'uploads/customers/' . $customer_id . '/' . $file_name;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO customer_documents (customer_id, document_name, file_path, file_type, file_size, uploaded_by, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customer_id,
            $file['name'],
            $relative_path,
            $mime_type,
            $file['size'],
            $uploaded_by,
            $description
        ]);
        
        return ['success' => true, 'document_id' => $pdo->lastInsertId(), 'file_path' => $relative_path];
    } catch (PDOException $e) {
        unlink($file_path);
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function getCustomerDocuments($customer_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cd.*, u.username as uploaded_by_name
            FROM customer_documents cd
            LEFT JOIN users u ON cd.uploaded_by = u.user_id
            WHERE cd.customer_id = ?
            ORDER BY cd.created_at DESC
        ");
        $stmt->execute([$customer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get documents error: " . $e->getMessage());
        return [];
    }
}

function deleteCustomerDocument($document_id, $customer_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM customer_documents WHERE document_id = ? AND customer_id = ?");
        $stmt->execute([$document_id, $customer_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $file_path = __DIR__ . '/../' . $document['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $stmt = $pdo->prepare("DELETE FROM customer_documents WHERE document_id = ? AND customer_id = ?");
            $stmt->execute([$document_id, $customer_id]);
            
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Document not found'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Check and update overdue loans with interest recalculation
function checkAndUpdateOverdueLoans() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Get all overdue installments
        $stmt = $pdo->prepare("
            SELECT ls.*, l.loan_amount, l.interest_rate, l.loan_term_type, l.loan_term_weeks, l.loan_term_months
            FROM loan_schedule ls
            JOIN loans l ON ls.loan_id = l.loan_id
            WHERE ls.due_date < ? 
            AND ls.status IN ('pending', 'overdue', 'partial')
            AND l.status = 'active'
        ");
        $stmt->execute([$today]);
        $overdue_installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($overdue_installments as $installment) {
            $due_date = new DateTime($installment['due_date']);
            $days_overdue = (new DateTime($today))->diff($due_date)->days;
            
            // Calculate how many periods (weeks or months) have passed
            $loan_term_type = $installment['loan_term_type'] ?? 'months';
            $periods_overdue = 0;
            
            if ($loan_term_type === 'weeks') {
                $weeks_per_period = $installment['loan_term_weeks'] ?? 1;
                $periods_overdue = floor($days_overdue / 7);
            } else {
                $periods_overdue = floor($days_overdue / 30); // Approximate months
            }
            
            if ($periods_overdue > 0) {
                // Recalculate interest: interest * number of periods overdue
                $original_interest = $installment['interest_amount'];
                $new_interest = $original_interest * ($periods_overdue + 1); // +1 for the current period
                $new_total = $installment['principal_amount'] + $new_interest;
                
                // Update the installment with new interest
                $stmt = $pdo->prepare("
                    UPDATE loan_schedule 
                    SET interest_amount = ?,
                        total_amount = ?,
                        status = 'overdue'
                    WHERE schedule_id = ?
                ");
                $stmt->execute([$new_interest, $new_total, $installment['schedule_id']]);
            } else {
                // Just mark as overdue
                $stmt = $pdo->prepare("
                    UPDATE loan_schedule 
                    SET status = 'overdue'
                    WHERE schedule_id = ?
                ");
                $stmt->execute([$installment['schedule_id']]);
            }
        }
        
        return ['success' => true, 'updated' => count($overdue_installments)];
    } catch (PDOException $e) {
        error_log("Check overdue loans error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Check and notify about overdue loans
function checkAndNotifyOverdueLoans() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Get all loans with overdue installments that haven't been notified today
        $stmt = $pdo->prepare("
            SELECT DISTINCT l.loan_id, l.customer_id, l.officer_id, c.customer_name,
                   MIN(ls.due_date) as earliest_overdue_date
            FROM loans l
            JOIN customers c ON l.customer_id = c.customer_id
            JOIN loan_schedule ls ON l.loan_id = ls.loan_id
            WHERE ls.due_date < ? 
            AND ls.status != 'paid'
            AND l.status = 'active'
            GROUP BY l.loan_id
            HAVING NOT EXISTS (
                SELECT 1 FROM notifications n
                WHERE n.message LIKE CONCAT('%Loan #', l.loan_id, '%Past Due%')
                AND DATE(n.created_at) = ?
            )
        ");
        $stmt->execute([$today, $today]);
        $overdue_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($overdue_loans as $loan_data) {
            $loan_id = $loan_data['loan_id'];
            $customer_name = $loan_data['customer_name'];
            $officer_id = $loan_data['officer_id'];
            $earliest_overdue = $loan_data['earliest_overdue_date'];
            
            $days_overdue = (new DateTime($today))->diff(new DateTime($earliest_overdue))->days;
            
            // Notify all admins
            $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin'");
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($admins as $admin_id) {
                sendNotification(
                    $admin_id,
                    'Past Due Loan Alert',
                    "Loan #$loan_id for customer '$customer_name' is past due. The earliest overdue date was " . formatDate($earliest_overdue) . " ($days_overdue days ago). Please take necessary action.",
                    'system'
                );
            }
            
            // Notify the officer who registered this loan (if exists)
            if ($officer_id) {
                sendNotification(
                    $officer_id,
                    'Past Due Loan Alert',
                    "Loan #$loan_id for customer '$customer_name' is past due. The earliest overdue date was " . formatDate($earliest_overdue) . " ($days_overdue days ago). Please contact the customer.",
                    'system'
                );
            }
        }
        
        return ['success' => true, 'notified' => count($overdue_loans)];
    } catch (PDOException $e) {
        error_log("Check and notify overdue loans error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get overpayment amount for a loan
function getLoanOverpayment($loan_id) {
    global $pdo;
    
    try {
        // Check if overpayment column exists
        $col_check = $pdo->query("SHOW COLUMNS FROM loan_payments LIKE 'overpayment'")->fetch();
        
        if ($col_check) {
            $stmt = $pdo->prepare("SELECT SUM(overpayment) as total_overpayment FROM loan_payments WHERE loan_id = ?");
            $stmt->execute([$loan_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_overpayment'] ?? 0;
        } else {
            // Calculate from notes or payment amounts
            $stmt = $pdo->prepare("
                SELECT SUM(payment_amount) as total_paid
                FROM loan_payments 
                WHERE loan_id = ?
            ");
            $stmt->execute([$loan_id]);
            $payments = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT SUM(total_amount) as total_due
                FROM loan_schedule 
                WHERE loan_id = ?
            ");
            $stmt->execute([$loan_id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $overpayment = ($payments['total_paid'] ?? 0) - ($schedule['total_due'] ?? 0);
            return max(0, $overpayment);
        }
    } catch (PDOException $e) {
        error_log("Get overpayment error: " . $e->getMessage());
        return 0;
    }
}

// Format date
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

// Calculate loan statistics
function getLoanStatistics($customer_id = null, $officer_id = null) {
    global $pdo;
    
    $where = "WHERE 1=1";
    $params = [];
    
    if ($customer_id) {
        $where .= " AND customer_id = ?";
        $params[] = $customer_id;
    }
    
    if ($officer_id) {
        $where .= " AND officer_id = ?";
        $params[] = $officer_id;
    }
    
    $stats = [];
    
    // Total loans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans $where");
    $stmt->execute($params);
    $stats['total_loans'] = $stmt->fetchColumn();
    
    // Pending loans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans $where AND status = 'pending'");
    $stmt->execute($params);
    $stats['pending_loans'] = $stmt->fetchColumn();
    
    // Approved loans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans $where AND status = 'approved'");
    $stmt->execute($params);
    $stats['approved_loans'] = $stmt->fetchColumn();
    
    // Active loans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans $where AND status = 'active'");
    $stmt->execute($params);
    $stats['active_loans'] = $stmt->fetchColumn();
    
    // Total loan amount
    $stmt = $pdo->prepare("SELECT SUM(loan_amount) FROM loans $where");
    $stmt->execute($params);
    $stats['total_loan_amount'] = $stmt->fetchColumn() ?: 0;
    
    // Total collections
    $where_payments = "WHERE 1=1";
    $params_payments = [];
    if ($customer_id) {
        $where_payments .= " AND loan_id IN (SELECT loan_id FROM loans WHERE customer_id = ?)";
        $params_payments[] = $customer_id;
    }
    if ($officer_id) {
        $where_payments .= " AND loan_id IN (SELECT loan_id FROM loans WHERE officer_id = ?)";
        $params_payments[] = $officer_id;
    }
    
    $stmt = $pdo->prepare("SELECT SUM(payment_amount) FROM loan_payments $where_payments");
    $stmt->execute($params_payments);
    $stats['total_collections'] = $stmt->fetchColumn() ?: 0;
    
    return $stats;
}

// Log audit trail
function logAudit($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    global $pdo;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $ip_address
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}


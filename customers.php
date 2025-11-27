<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$user_id = $_SESSION['user_id'];

// Check for success message from add customer redirect
$customer_added = false;
$customer_added_name = '';
$loan_added = false;
$customer_warning = null;
if (isset($_SESSION['customer_added'])) {
    $customer_added = true;
    $customer_added_name = $_SESSION['customer_added_name'] ?? '';
    $loan_added = isset($_SESSION['loan_added']);
    $customer_warning = $_SESSION['customer_warning'] ?? null;
    unset($_SESSION['customer_added'], $_SESSION['customer_added_name'], $_SESSION['loan_added'], $_SESSION['customer_warning']);
}

// Get all customers
$customers = getAllCustomers();

// Search functionality
if (isset($_GET['search'])) {
    $search_term = '%' . sanitize($_GET['search']) . '%';
    $stmt = $pdo->prepare("
        SELECT c.*, u.email as user_email, u.is_active 
        FROM customers c 
        LEFT JOIN users u ON c.user_id = u.user_id 
        WHERE c.customer_name LIKE ? OR c.phone LIKE ? OR c.national_id LIKE ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - <?php echo APP_NAME; ?></title>
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
        include '../includes/sidebar_template.php'; 
        ?>
        
        <div class="officer-main">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-users"></i> Manage Customers</h1>
                    <p>View and manage all registered customers</p>
                </div>
                <a href="add_customer.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add New Customer
                </a>
            </div>
            
            <?php if ($customer_added): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <p><strong>Customer "<?php echo htmlspecialchars($customer_added_name); ?>" added successfully!</strong></p>
                        <?php if ($loan_added): ?>
                            <p><strong>Loan application created successfully!</strong></p>
                        <?php elseif ($customer_warning): ?>
                            <p style="color: var(--warning-yellow);"><strong>Note:</strong> Customer created but loan application had issues: <?php echo htmlspecialchars($customer_warning); ?>. You can create a loan manually.</p>
                        <?php else: ?>
                            <p>You can now create a loan application for this customer.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Search Customers</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Search by name, phone, or National ID..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <?php if (isset($_GET['search'])): ?>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <a href="customers.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Customers (<?php echo count($customers); ?>)</h2>
                </div>
                
                <?php if (empty($customers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Customers Found</h3>
                        <p>Start by adding your first customer.</p>
                        <a href="add_customer.php" class="btn btn-primary" style="margin-top: var(--spacing-lg);">
                            <i class="fas fa-user-plus"></i> Add Customer
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-body" style="padding: var(--spacing-lg);">
                    <div class="customers-grid">
                        <?php foreach ($customers as $customer): 
                            $age = 0;
                            if ($customer['date_of_birth']) {
                                $birth_date = new DateTime($customer['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($birth_date)->y;
                            }
                            
                            $customer_loans = [];
                            try {
                                $loan_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(loan_amount) as total_amount, SUM(total_paid) as total_paid FROM loans WHERE customer_id = ?");
                                $loan_stmt->execute([$customer['customer_id']]);
                                $customer_loans = $loan_stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {}
                        ?>
                            <div class="customer-card-modern">
                                <div class="customer-card-header-modern">
                                    <div style="flex: 1; min-width: 0;">
                                        <h3 style="margin: 0 0 var(--spacing-xs) 0; font-size: var(--font-size-lg); font-weight: 700; color: var(--dark-blue); display: flex; align-items: center; gap: var(--spacing-sm); flex-wrap: wrap;">
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            <?php if ($age >= MIN_LOAN_AGE): ?>
                                                <span class="status-badge-modern" style="background: var(--light-gray); color: var(--medium-gray); padding: 2px var(--spacing-sm); border-radius: var(--radius-full); font-size: var(--font-size-xs); font-weight: 600;">
                                                    <?php echo $age; ?> years
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge-modern" style="background: var(--warning-yellow); color: var(--white); padding: 2px var(--spacing-sm); border-radius: var(--radius-full); font-size: var(--font-size-xs); font-weight: 600;">
                                                    Under <?php echo MIN_LOAN_AGE; ?>
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-sm); color: var(--medium-gray);">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?>
                                            <?php if ($customer['email']): ?>
                                                <span style="margin: 0 var(--spacing-xs); opacity: 0.5;">•</span>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="customer-card-body-modern">
                                    <?php if ($customer['national_id']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-id-card"></i>
                                        <span>ID: <?php echo htmlspecialchars($customer['national_id']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($customer['date_of_birth']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <span>DOB: <?php echo formatDate($customer['date_of_birth']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($customer['occupation'] || $customer['monthly_income']): ?>
                                    <div class="customer-employment">
                                        <?php if ($customer['occupation']): ?>
                                        <div class="employment-item">
                                            <i class="fas fa-briefcase"></i>
                                            <div>
                                                <span class="label">Occupation</span>
                                                <span class="value"><?php echo htmlspecialchars($customer['occupation']); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($customer['monthly_income']): ?>
                                        <div class="employment-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <div>
                                                <span class="label">Monthly Income</span>
                                                <span class="value"><?php echo formatCurrency($customer['monthly_income']); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($customer_loans) && $customer_loans['total'] > 0): ?>
                                    <div class="customer-loan-stats">
                                        <div class="loan-stat-item">
                                            <span class="stat-value"><?php echo $customer_loans['total']; ?></span>
                                            <span class="stat-label">Loans</span>
                                        </div>
                                        <div class="loan-stat-item">
                                            <span class="stat-value"><?php echo formatCurrency($customer_loans['total_amount'] ?? 0, false); ?></span>
                                            <span class="stat-label">Borrowed</span>
                                        </div>
                                        <div class="loan-stat-item">
                                            <span class="stat-value" style="color: var(--success-green);"><?php echo formatCurrency($customer_loans['total_paid'] ?? 0, false); ?></span>
                                            <span class="stat-label">Paid</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="customer-card-footer-modern">
                                    <a href="loans.php?action=add&customer_id=<?php echo $customer['customer_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-file-invoice-dollar"></i> Create Loan
                                    </a>
                                    <a href="customer_account.php?id=<?php echo $customer['customer_id']; ?>" 
                                       class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                <?php endif; ?>
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
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    container.classList.add('sidebar-collapsed');
                }
            }
        });
    </script>
</body>
</html>


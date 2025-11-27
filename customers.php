<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdminOrOfficer();

$is_admin = isAdmin();
$user_id = $_SESSION['user_id'];

// Get all customers
$customers = getAllCustomers();

// Search functionality - enhanced to search by name, ID, phone, or date
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $search_term = '%' . $search . '%';
    
    // Check if search is a date (YYYY-MM-DD or similar formats)
    $is_date = false;
    $date_search = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
        $is_date = true;
        $date_search = $search;
    } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)) {
        $is_date = true;
        $date_parts = explode('/', $search);
        $date_search = $date_parts[2] . '-' . $date_parts[0] . '-' . $date_parts[1];
    }
    
    // Check if search is numeric (could be customer_id)
    $is_numeric = is_numeric($search);
    
    $where_conditions = [];
    $params = [];
    
    if ($is_date) {
        $where_conditions[] = "DATE(c.created_at) = ?";
        $params[] = $date_search;
    } else {
        $where_conditions[] = "(c.customer_name LIKE ? OR c.phone LIKE ? OR c.national_id LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        
        if ($is_numeric) {
            $where_conditions[] = "c.customer_id = ?";
            $params[] = intval($search);
        }
    }
    
    $where_clause = implode(' OR ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.email as user_email, u.is_active 
        FROM customers c 
        LEFT JOIN users u ON c.user_id = u.user_id 
        WHERE $where_clause
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

// Delete customer
if (isset($_GET['delete'])) {
    $customer_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    logAudit($user_id, 'Delete Customer', 'customers', $customer_id, null, null);
    header("Location: customers.php?deleted=1");
    exit();
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
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>Customer deleted successfully.</p>
                </div>
            <?php endif; ?>
            
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Search Customers</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Search by name, ID, phone, or date (YYYY-MM-DD)..." 
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
                                            <span class="status-badge-modern" style="background: var(--light-gray); color: var(--medium-gray); padding: 2px var(--spacing-sm); border-radius: var(--radius-full); font-size: var(--font-size-xs); font-weight: 600;">
                                                <?php echo ucfirst($customer['gender'] ?? 'N/A'); ?>
                                            </span>
                                        </h3>
                                        <p style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-sm); color: var(--medium-gray);">
                                            <?php echo htmlspecialchars($customer['phone']); ?>
                                            <?php if ($customer['email']): ?>
                                                <span style="margin: 0 var(--spacing-xs); opacity: 0.5;">•</span>
                                                <?php echo htmlspecialchars($customer['email']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="customer-card-body-modern">
                                    <?php if ($customer['national_id']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-id-card"></i>
                                        <span><?php echo htmlspecialchars($customer['national_id']); ?></span>
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
                                    
                                    <div class="contact-item" style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--border-light);">
                                        <i class="fas fa-clock"></i>
                                        <span style="font-size: var(--font-size-xs); color: var(--medium-gray);">
                                            Registered: <?php echo formatDate($customer['created_at'], 'M j, Y g:i A'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="customer-card-footer-modern">
                                    <a href="customer_account.php?id=<?php echo $customer['customer_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo $is_admin ? 'loans.php?action=add&customer_id=' . $customer['customer_id'] : '../officer/loans.php?action=add&customer_id=' . $customer['customer_id']; ?>" 
                                       class="btn btn-outline btn-sm">
                                        <i class="fas fa-file-invoice-dollar"></i> Loan
                                    </a>
                                    <?php if ($is_admin): ?>
                                    <a href="customers.php?delete=<?php echo $customer['customer_id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this customer? This will also delete all associated loans.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
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


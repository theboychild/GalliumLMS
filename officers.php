<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$errors = [];
$success = false;
$user_id = $_SESSION['user_id'];

// Get all officers
$officers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT l.loan_id) as total_loans,
               COUNT(DISTINCT CASE WHEN l.status = 'approved' THEN l.loan_id END) as approved_loans,
               COUNT(DISTINCT CASE WHEN l.status = 'pending' THEN l.loan_id END) as pending_loans
        FROM users u
        LEFT JOIN loans l ON u.user_id = l.officer_id
        WHERE u.user_type = 'officer'
        GROUP BY u.user_id
        ORDER BY u.username
    ");
    $stmt->execute();
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error loading officers: " . $e->getMessage();
}

if (isset($_GET['generate_code']) && isset($_GET['id'])) {
    $officer_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE user_id = ? AND user_type = 'officer'");
        $stmt->execute([$officer_id]);
        $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($officer) {
            $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $code_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS password_reset_codes (
                        user_id INT PRIMARY KEY,
                        reset_code VARCHAR(6) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        created_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (PDOException $e) {
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO password_reset_codes (user_id, reset_code, expires_at, created_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    reset_code = VALUES(reset_code),
                    expires_at = VALUES(expires_at),
                    created_by = VALUES(created_by),
                    created_at = NOW()
            ");
            $stmt->execute([$officer_id, $reset_code, $code_expires, $user_id]);
            
            sendNotification($officer_id, 'Password Reset Code', "Your password reset code is: $reset_code. This code expires in 24 hours.", 'system');
            
            logAudit($user_id, 'Generate Password Reset Code', 'users', $officer_id, null, ['code' => $reset_code]);
            $_SESSION['reset_code_generated'] = "Password reset code for {$officer['username']}: $reset_code";
            header("Location: officers.php?code_generated=1&officer_id=$officer_id");
            exit();
        }
    } catch (PDOException $e) {
        $errors[] = "Error generating code: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_password']) && isset($_GET['id'])) {
    $officer_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT can_change_password FROM users WHERE user_id = ? AND user_type = 'officer'");
        $stmt->execute([$officer_id]);
        $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($officer) {
            $new_value = $officer['can_change_password'] == 1 ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET can_change_password = ? WHERE user_id = ? AND user_type = 'officer'");
            $stmt->execute([$new_value, $officer_id]);
            
            logAudit($user_id, 'Toggle Password Change Permission', 'users', $officer_id, null, ['can_change_password' => $new_value]);
            header("Location: officers.php?success=toggle");
            exit();
        }
    } catch (PDOException $e) {
        $errors[] = "Error updating permission: " . $e->getMessage();
    }
}

// Handle toggle active status
if (isset($_GET['toggle_active']) && isset($_GET['id'])) {
    $officer_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE user_id = ? AND user_type = 'officer'");
        $stmt->execute([$officer_id]);
        $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($officer) {
            $new_value = $officer['is_active'] == 1 ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ? AND user_type = 'officer'");
            $stmt->execute([$new_value, $officer_id]);
            
            logAudit($user_id, 'Toggle Officer Status', 'users', $officer_id, null, ['is_active' => $new_value]);
            header("Location: officers.php?success=status");
            exit();
        }
    } catch (PDOException $e) {
        $errors[] = "Error updating status: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officers - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-user-tie"></i> Manage Officers</h1>
                    <p>View and manage all loan officers</p>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>
                        <?php 
                        if ($_GET['success'] === 'toggle') {
                            echo "Password change permission updated successfully!";
                        } elseif ($_GET['success'] === 'status') {
                            echo "Officer status updated successfully!";
                        }
                        ?>
                    </p>
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
            
            <?php if (empty($officers)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h3>No Officers Found</h3>
                    <p>No loan officers have been registered yet.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">All Officers (<?php echo count($officers); ?>)</h2>
                    </div>
                    <div class="card-body" style="padding: var(--spacing-lg);">
                <div class="officers-grid-modern">
                    <?php foreach ($officers as $officer): 
                        $initials = strtoupper(substr($officer['username'], 0, 2));
                        $can_change_password = isset($officer['can_change_password']) ? $officer['can_change_password'] : 0;
                    ?>
                        <div class="officer-card-modern">
                            <div class="officer-card-header-modern">
                                <div style="flex: 1; min-width: 0;">
                                    <h3 style="margin: 0 0 var(--spacing-xs) 0; font-size: var(--font-size-lg); font-weight: 700; color: var(--dark-blue); display: flex; align-items: center; gap: var(--spacing-sm); flex-wrap: wrap; word-wrap: break-word; overflow-wrap: break-word;">
                                        <span style="flex: 1; min-width: 0; word-wrap: break-word; overflow-wrap: break-word;"><?php echo htmlspecialchars($officer['username']); ?></span>
                                        <span class="status-badge-modern <?php echo ($officer['is_active'] ?? 1) == 1 ? 'status-active' : 'status-inactive'; ?>" style="flex-shrink: 0;">
                                            <?php echo ($officer['is_active'] ?? 1) == 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </h3>
                                    <div style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-sm); color: var(--medium-gray);">
                                        <div style="display: flex; align-items: center; gap: var(--spacing-xs); flex-wrap: wrap; word-wrap: break-word; overflow-wrap: break-word;">
                                            <i class="fas fa-envelope" style="flex-shrink: 0;"></i>
                                            <span style="word-break: break-all; flex: 1; min-width: 0;"><?php echo htmlspecialchars($officer['email']); ?></span>
                                        </div>
                                        <?php if ($officer['phone']): ?>
                                        <div style="display: flex; align-items: center; gap: var(--spacing-xs); margin-top: var(--spacing-xs);">
                                            <i class="fas fa-phone" style="flex-shrink: 0;"></i>
                                            <span style="word-break: break-all;"><?php echo htmlspecialchars($officer['phone']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="officer-card-body-modern">
                                <div class="officer-loan-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--spacing-sm); margin-bottom: var(--spacing-md);">
                                    <div class="loan-stat-item" style="text-align: center; padding: var(--spacing-sm); background: var(--light-gray); border-radius: var(--radius-sm);">
                                        <div class="stat-value" style="font-size: var(--font-size-xl); font-weight: 700; color: var(--brand-blue); margin-bottom: var(--spacing-xs);"><?php echo $officer['total_loans'] ?? 0; ?></div>
                                        <div class="stat-label" style="font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px;">Total Loans</div>
                                    </div>
                                    <div class="loan-stat-item" style="text-align: center; padding: var(--spacing-sm); background: var(--success-light); border-radius: var(--radius-sm);">
                                        <div class="stat-value" style="font-size: var(--font-size-xl); font-weight: 700; color: var(--success-green); margin-bottom: var(--spacing-xs);"><?php echo $officer['approved_loans'] ?? 0; ?></div>
                                        <div class="stat-label" style="font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px;">Approved</div>
                                    </div>
                                    <div class="loan-stat-item" style="text-align: center; padding: var(--spacing-sm); background: var(--warning-light); border-radius: var(--radius-sm);">
                                        <div class="stat-value" style="font-size: var(--font-size-xl); font-weight: 700; color: var(--warning-yellow); margin-bottom: var(--spacing-xs);"><?php echo $officer['pending_loans'] ?? 0; ?></div>
                                        <div class="stat-label" style="font-size: var(--font-size-xs); color: var(--medium-gray); text-transform: uppercase; letter-spacing: 0.5px;">Pending</div>
                                    </div>
                                </div>
                                
                                <div class="officer-status-badges" style="padding-top: var(--spacing-sm); border-top: 1px solid var(--border-light);">
                                    <span class="status-badge-modern <?php echo $can_change_password == 1 ? 'status-success' : 'status-warning'; ?>" style="display: inline-flex; align-items: center; gap: var(--spacing-xs);">
                                        <i class="fas fa-<?php echo $can_change_password == 1 ? 'unlock' : 'lock'; ?>"></i>
                                        <?php echo $can_change_password == 1 ? 'Password Unlocked' : 'Password Locked'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="officer-card-footer-modern">
                                <a href="officer_account.php?id=<?php echo $officer['user_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <button onclick="generateResetCode(<?php echo $officer['user_id']; ?>, '<?php echo htmlspecialchars($officer['username']); ?>')" 
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-key"></i> Reset Code
                                </button>
                                <a href="officers.php?toggle_active=1&id=<?php echo $officer['user_id']; ?>" 
                                   class="btn btn-sm <?php echo ($officer['is_active'] ?? 1) == 1 ? 'btn-danger' : 'btn-success'; ?>"
                                   onclick="return confirm('<?php echo ($officer['is_active'] ?? 1) == 1 ? 'Deactivate' : 'Activate'; ?> this officer?');">
                                    <i class="fas fa-<?php echo ($officer['is_active'] ?? 1) == 1 ? 'ban' : 'check'; ?>"></i>
                                    <?php echo ($officer['is_active'] ?? 1) == 1 ? 'Deactivate' : 'Activate'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                    </div>
                </div>
            <?php endif; ?>
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


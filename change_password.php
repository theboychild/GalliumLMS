<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

$reset_code = $_POST['reset_code'] ?? '';
$has_valid_code = false;

if (!empty($reset_code)) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM password_reset_codes 
            WHERE user_id = ? AND reset_code = ? AND expires_at > NOW()
        ");
        $stmt->execute([$user_id, $reset_code]);
        $code_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $has_valid_code = ($code_data !== false);
    } catch (PDOException $e) {
        $has_valid_code = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!$has_valid_code && empty($reset_code)) {
        $errors[] = "Please enter a valid reset code provided by the administrator.";
    } elseif (!$has_valid_code) {
        $errors[] = "Invalid or expired reset code. Please contact the administrator for a new code.";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                
                if ($stmt->execute([$hashed_password, $user_id])) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM password_reset_codes WHERE user_id = ? AND reset_code = ?");
                        $stmt->execute([$user_id, $reset_code]);
                    } catch (PDOException $e) {
                    }
                    
                    logAudit($user_id, 'Change Password', 'users', $user_id, null, ['action' => 'password_changed_with_code']);
                    $success = true;
                } else {
                    $errors[] = "Failed to change password";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo APP_NAME; ?></title>
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
        $unread_count = $unread_count ?? 0;
        include '../includes/sidebar_template.php'; 
        ?>
        
        <div class="officer-main">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-key"></i> Change Password</h1>
                    <p>Update your account password</p>
                </div>
            </div>
            
            <?php if (empty($reset_code) && !$has_valid_code): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p><strong>Password Reset Code Required</strong></p>
                        <p>Please contact the administrator to generate a password reset code for you. The code will be sent to you via notification.</p>
                    </div>
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
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p><strong>Password changed successfully!</strong> Please login again with your new password.</p>
                    <div style="margin-top: var(--spacing-md);">
                        <a href="../logout.php" class="btn btn-primary">
                            <i class="fas fa-sign-out-alt"></i> Logout & Login Again
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-container" style="max-width: 600px; margin: 0 auto;">
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label for="reset_code">Reset Code *</label>
                        <input type="text" name="reset_code" id="reset_code" class="form-control" required maxlength="6" placeholder="Enter 6-digit code">
                        <small style="color: var(--medium-gray);">Enter the reset code provided by the administrator</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                        <small style="color: var(--medium-gray);">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-xl);">
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </div>
                </form>
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
                const isCollapsed = localStorage.getItem('officerSidebarCollapsed') === 'true';
                if (isCollapsed) {
                    container.classList.add('sidebar-collapsed');
                }
            }
        });
    </script>
</body>
</html>


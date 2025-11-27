<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['approve']) && isset($_GET['id'])) {
        $request_id = (int)$_GET['id'];
        try {
            $stmt = $pdo->prepare("SELECT prr.*, u.username, u.email FROM password_reset_requests prr JOIN users u ON prr.user_id = u.user_id WHERE prr.request_id = ? AND prr.status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $stmt = $pdo->prepare("UPDATE password_reset_requests SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE request_id = ?");
                $stmt->execute([$user_id, $request_id]);
                
                // Update user's can_change_password flag
                $stmt = $pdo->prepare("UPDATE users SET can_change_password = 1 WHERE user_id = ?");
                $stmt->execute([$request['user_id']]);
                
                // Send notification to user
                sendNotification($request['user_id'], 'Password Reset Approved', 'Your password reset request has been approved. You can now change your password from your dashboard.', 'system');
                
                logAudit($user_id, 'Approve Password Reset', 'password_reset_requests', $request_id, null, ['user_id' => $request['user_id']]);
                header("Location: password_reset_requests.php?success=approved");
                exit();
            } else {
                $errors[] = "Request not found or already processed";
            }
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    } elseif (isset($_GET['reject']) && isset($_GET['id'])) {
        $request_id = (int)$_GET['id'];
        $reason = sanitize($_GET['reason'] ?? 'Not approved');
        try {
            $stmt = $pdo->prepare("SELECT prr.*, u.username, u.email FROM password_reset_requests prr JOIN users u ON prr.user_id = u.user_id WHERE prr.request_id = ? AND prr.status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $stmt = $pdo->prepare("UPDATE password_reset_requests SET status = 'rejected', approved_at = NOW(), approved_by = ?, rejection_reason = ? WHERE request_id = ?");
                $stmt->execute([$user_id, $reason, $request_id]);
                
                // Send notification to user
                sendNotification($request['user_id'], 'Password Reset Rejected', 'Your password reset request has been rejected. Reason: ' . $reason, 'system');
                
                logAudit($user_id, 'Reject Password Reset', 'password_reset_requests', $request_id, null, ['user_id' => $request['user_id'], 'reason' => $reason]);
                header("Location: password_reset_requests.php?success=rejected");
                exit();
            } else {
                $errors[] = "Request not found or already processed";
            }
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Get all password reset requests (only if table exists)
$requests = [];
try {
    // Check if table exists first
    $table_check = $pdo->query("SHOW TABLES LIKE 'password_reset_requests'");
    if ($table_check->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT prr.*, u.username, u.email, u.user_type,
                   approver.username as approver_name
            FROM password_reset_requests prr
            JOIN users u ON prr.user_id = u.user_id
            LEFT JOIN users approver ON prr.approved_by = approver.user_id
            ORDER BY prr.requested_at DESC
        ");
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Table doesn't exist - this feature is now handled in officers page
        $errors[] = "Password reset requests are now managed through the Officers page. Use 'Generate Reset Code' for each officer.";
    }
} catch (PDOException $e) {
    // Table doesn't exist - show helpful message
    $errors[] = "Password reset requests are now managed through the Officers page. Use 'Generate Reset Code' for each officer.";
}

$pending_count = count(array_filter($requests, function($r) { return $r['status'] === 'pending'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Requests - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-key"></i> Password Reset Requests</h1>
                    <p>Review and approve password reset requests from users</p>
                </div>
                <?php if ($pending_count > 0): ?>
                    <div class="badge status-pending" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        <?php echo $pending_count; ?> Pending
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>
                        <?php if ($_GET['success'] === 'approved'): ?>
                            Password reset request approved successfully.
                        <?php elseif ($_GET['success'] === 'rejected'): ?>
                            Password reset request rejected.
                        <?php endif; ?>
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
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-key"></i> Password Reset Requests</h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: var(--spacing-2xl); color: var(--medium-gray);">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;"></i>
                                    <p>No password reset requests found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($request['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($request['email']); ?></td>
                                    <td><span class="badge"><?php echo ucfirst($request['user_type']); ?></span></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <span class="status-badge status-pending">Pending</span>
                                        <?php elseif ($request['status'] === 'approved'): ?>
                                            <span class="status-badge status-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="status-badge status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['approver_name']): ?>
                                            <?php echo htmlspecialchars($request['approver_name']); ?>
                                            <br><small style="color: var(--medium-gray);"><?php echo date('M j, Y', strtotime($request['approved_at'])); ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--medium-gray);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <a href="password_reset_requests.php?approve=1&id=<?php echo $request['request_id']; ?>" 
                                               class="btn btn-success btn-sm" 
                                               onclick="return confirm('Approve this password reset request? The user will be able to change their password.');">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="password_reset_requests.php?reject=1&id=<?php echo $request['request_id']; ?>&reason=Not%20approved" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Reject this password reset request?');">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--medium-gray);">-</span>
                                        <?php endif; ?>
                                    </td>
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
        function toggleSidebar() {
            const container = document.querySelector('.admin-container');
            container.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', container.classList.contains('sidebar-collapsed'));
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                document.querySelector('.admin-container').classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>


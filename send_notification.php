<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$admin_user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get all users for selection
$all_users = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, email, user_type FROM users WHERE is_active = 1 ORDER BY user_type, username");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error loading users: " . $e->getMessage();
}

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $selected_users = $_POST['selected_users'] ?? [];
    $title = sanitize($_POST['title']);
    $message = sanitize($_POST['message']);
    $notification_type = $_POST['notification_type'] ?? 'system';
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if ($recipient_type === 'selected' && empty($selected_users)) {
        $errors[] = "Please select at least one user";
    }
    
    if (empty($errors)) {
        $sent_count = 0;
        
        try {
            if ($recipient_type === 'all') {
                // Send to all active users
                $stmt = $pdo->query("SELECT user_id FROM users WHERE is_active = 1");
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($recipient_type === 'officers') {
                // Send to all officers
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'officer' AND is_active = 1");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($recipient_type === 'customers') {
                // Send to all customers
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'customer' AND is_active = 1");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                // Send to selected users
                $users = array_map('intval', $selected_users);
            }
            
            if (empty($users)) {
                $errors[] = "No recipients found for the selected recipient type.";
            } else {
                foreach ($users as $recipient_user_id) {
                    if (empty($recipient_user_id) || !is_numeric($recipient_user_id)) {
                        error_log("Invalid recipient_user_id: " . var_export($recipient_user_id, true));
                        continue;
                    }
                    
                    // Verify user exists
                    $user_check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
                    $user_check->execute([$recipient_user_id]);
                    if (!$user_check->fetch()) {
                        error_log("User ID $recipient_user_id does not exist, skipping notification");
                        continue;
                    }
                    
                    // Verify user exists
                    $user_check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
                    $user_check->execute([$recipient_user_id]);
                    if (!$user_check->fetch()) {
                        error_log("User ID $recipient_user_id does not exist, skipping notification");
                        continue;
                    }
                    
                    try {
                        $result = sendNotification($recipient_user_id, $title, $message, $notification_type, $admin_user_id);
                        if ($result) {
                            $sent_count++;
                        } else {
                            error_log("Failed to send notification to user_id: $recipient_user_id");
                        }
                    } catch (Exception $e) {
                        error_log("Exception sending notification to user_id $recipient_user_id: " . $e->getMessage());
                    }
                }
                
                if ($sent_count == 0 && !empty($users)) {
                    $errors[] = "Failed to send notifications. Please check error logs.";
                }
                
                if ($sent_count == 0 && !empty($users)) {
                    $errors[] = "Failed to send notifications. Please check error logs.";
                }
            }
            
            // Log audit
            logAudit($admin_user_id, 'Send Notification', 'notifications', null, null, [
                'recipient_type' => $recipient_type,
                'recipient_count' => $sent_count,
                'title' => $title
            ]);
            
            $success = true;
            $success_message = "Notification sent successfully to $sent_count user(s)";
        } catch (PDOException $e) {
            $errors[] = "Error sending notification: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-paper-plane"></i> Send Notification</h1>
                    <p>Send notifications to users in the system</p>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Notification Form</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="recipient_type">Recipient</label>
                            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-top: var(--spacing-sm);">
                                <label class="radio-label">
                                    <input type="radio" name="recipient_type" value="all" checked onchange="toggleUserList()">
                                    <span>All Users</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="recipient_type" value="officers" onchange="toggleUserList()">
                                    <span>All Officers</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="recipient_type" value="customers" onchange="toggleUserList()">
                                    <span>All Customers</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="recipient_type" value="selected" onchange="toggleUserList()">
                                    <span>Selected Users</span>
                                </label>
                            </div>
                            
                            <div id="user-list" class="user-list" style="display: none; margin-top: var(--spacing-md); max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: var(--spacing-md); background: var(--light-gray);">
                                <?php foreach ($all_users as $user): ?>
                                    <div class="user-item" style="padding: var(--spacing-sm); border-bottom: 1px solid var(--border-light);">
                                        <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['user_id']; ?>" style="margin-right: var(--spacing-sm);">
                                            <span style="flex: 1;"><?php echo htmlspecialchars($user['username']); ?></span>
                                            <span class="badge badge-<?php echo $user['user_type']; ?>" style="margin-left: var(--spacing-sm);">
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
                                            <small style="color: var(--medium-gray); margin-left: var(--spacing-sm);"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notification_type">Notification Type</label>
                            <select name="notification_type" id="notification_type" class="form-control">
                                <option value="system">System</option>
                                <option value="loan">Loan</option>
                                <option value="payment">Payment</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" name="title" id="title" class="form-control" required maxlength="100" placeholder="Enter notification title">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea name="message" id="message" class="form-control" required rows="5" placeholder="Enter notification message"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="send_notification" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Notification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        
        function toggleUserList() {
            const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
            const userList = document.getElementById('user-list');
            
            if (recipientType === 'selected') {
                userList.style.display = 'block';
            } else {
                userList.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                document.querySelector('.admin-container').classList.add('sidebar-collapsed');
            }
            toggleUserList();
        });
    </script>
</body>
</html>


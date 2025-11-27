<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireOfficer();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notification_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        $success = true;
        header("Location: notifications.php?marked=1");
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error marking notification as read: " . $e->getMessage();
    }
}

if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success = true;
        header("Location: notifications.php?marked_all=1");
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error marking all notifications as read: " . $e->getMessage();
    }
}

// Get notifications - simplified query
$notifications = [];
$unread_count = 0;

try {
    // Check if created_by column exists
    $col_check = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by'")->fetch();
    
    // Get unread count
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$user_id]);
    $unread_count = $unread_stmt->fetchColumn() ?: 0;
    
    // Check if sender_id column exists
    $sender_col_check = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'sender_id'")->fetch();
    
    // Use direct query that works regardless of column existence
    if ($sender_col_check) {
        // sender_id exists, use query with join
        $sql = "SELECT n.*, 
                sender.username as sender_username
                FROM notifications n
                LEFT JOIN users sender ON n.sender_id = sender.user_id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 100";
    } else {
        // sender_id doesn't exist, use simple query
        $sql = "SELECT n.*, 
                NULL as sender_username
                FROM notifications n
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 100";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Notification retrieval error: " . $e->getMessage());
    $errors[] = "Error loading notifications: " . $e->getMessage();
    
    // Last resort: try simplest possible query
    try {
        $simple_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($simple_sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add sender_username manually
        foreach ($notifications as &$notif) {
            if (!empty($notif['sender_id'])) {
                $sender_stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
                $sender_stmt->execute([$notif['sender_id']]);
                $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
                $notif['sender_username'] = $sender['username'] ?? 'Admin';
            } else {
                $notif['sender_username'] = 'Admin';
            }
        }
    } catch (PDOException $e2) {
        error_log("Simple query also failed: " . $e2->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-bell"></i> Notifications</h1>
                    <p>View your notifications from the administrator</p>
                </div>
                <?php if ($unread_count > 0): ?>
                    <div>
                        <a href="notifications.php?mark_all_read=1" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (isset($_GET['marked'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>Notification marked as read.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['marked_all'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <p>All notifications marked as read.</p>
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
            
            <?php if (empty($notifications)): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--medium-gray); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--medium-gray);">No Notifications</h3>
                        <p style="color: var(--medium-gray);">You don't have any notifications yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                                    <div class="notification-content">
                                        <div class="notification-header">
                                            <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                            <?php if ($notification['is_read'] == 0): ?>
                                                <span class="notification-badge">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-meta">
                                            <span class="notification-type"><?php echo ucfirst($notification['notification_type']); ?></span>
                                            <?php if (!empty($notification['sender_username'])): ?>
                                                <span class="notification-sender">
                                                    <i class="fas fa-user"></i> From: <?php echo htmlspecialchars($notification['sender_username']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="notification-date">
                                                <i class="fas fa-clock"></i> <?php echo formatDate($notification['created_at'], 'M j, Y g:i A'); ?>
                                            </span>
                                        </div>
                                        <p class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if ($notification['is_read'] == 0): ?>
                                            <a href="notifications.php?mark_read=1&id=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline" title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
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

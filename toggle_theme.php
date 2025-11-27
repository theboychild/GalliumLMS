<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Toggle theme: if current is light, switch to dark, and vice versa
$current_theme = 'light';
if (isset($_SESSION['theme'])) {
    $current_theme = $_SESSION['theme'];
} else {
    try {
        $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $theme = $stmt->fetchColumn();
        if ($theme) {
            $current_theme = $theme;
        }
    } catch (PDOException $e) {
        // Use default
    }
}

$new_theme = ($current_theme === 'light') ? 'dark' : 'light';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
    $column_exists = $stmt->rowCount() > 0;
    
    if ($column_exists) {
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE user_id = ?");
        $stmt->execute([$new_theme, $user_id]);
    }
    $_SESSION['theme'] = $new_theme;
    
    // Redirect back to the page that called this
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin/dashboard.php';
    header("Location: " . $redirect);
    exit();
} catch (PDOException $e) {
    error_log("Theme toggle error: " . $e->getMessage());
    $_SESSION['theme'] = $new_theme;
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin/dashboard.php';
    header("Location: " . $redirect);
    exit();
}


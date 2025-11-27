<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$download = isset($_GET['download']) && $_GET['download'] == '1';

if ($document_id <= 0 || $customer_id <= 0) {
    http_response_code(404);
    die('File not found');
}

try {
    $stmt = $pdo->prepare("
        SELECT cd.*, c.customer_id
        FROM customer_documents cd
        JOIN customers c ON cd.customer_id = c.customer_id
        WHERE cd.document_id = ? AND cd.customer_id = ?
    ");
    $stmt->execute([$document_id, $customer_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        die('File not found');
    }
    
    // Try multiple path resolutions
    $file_path = null;
    $possible_paths = [
        __DIR__ . '/' . $document['file_path'],
        __DIR__ . '/../' . $document['file_path'],
        realpath(__DIR__ . '/../' . $document['file_path'])
    ];
    
    foreach ($possible_paths as $path) {
        if ($path && file_exists($path)) {
            $file_path = $path;
            break;
        }
    }
    
    if (!$file_path || !file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server. Path: ' . $document['file_path'] . ' | Tried: ' . implode(', ', $possible_paths));
    }
    
    $file_name = $document['document_name'];
    $file_type = $document['file_type'] ?? mime_content_type($file_path);
    
    if ($download) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    } else {
        header('Content-Type: ' . $file_type);
        header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
    }
    
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');
    
    readfile($file_path);
    exit();
} catch (PDOException $e) {
    http_response_code(500);
    die('Error accessing file: ' . $e->getMessage());
}


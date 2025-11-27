<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    header("Location: customers.php");
    exit();
}

$customer = getCustomerById($customer_id);
if (!$customer) {
    header("Location: customers.php");
    exit();
}

$fpdf_path = __DIR__ . '/../includes/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    die('Error: FPDF library not found');
}

require_once($fpdf_path);

class PDFWithFooter extends FPDF {
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 10);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, APP_NAME . ' - Copyright ' . date('Y'), 0, 0, 'C');
    }
}

$pdf = new PDFWithFooter('P', 'mm', 'A4');
$pdf->SetTitle('Customer Report - ' . $customer['customer_name']);
$pdf->SetAuthor(APP_NAME);
$pdf->SetAutoPageBreak(true, 20); // Auto page break with 20mm margin

// Try to add logo
$logo_paths = [
    __DIR__ . '/../image/GSL.png',
    __DIR__ . '/../assets/images/logo.png',
    __DIR__ . '/../assets/images/GSL.png',
    __DIR__ . '/../image/logo.png'
];
$logo_path = null;
foreach ($logo_paths as $path) {
    if (file_exists($path)) {
        $logo_path = $path;
        break;
    }
}

// Add page
$pdf->AddPage();

// Logo and header
if ($logo_path) {
    $pdf->Image($logo_path, 10, 10, 50);
    $pdf->SetY(25);
} else {
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(30, 58, 138);
    $pdf->Cell(0, 10, APP_NAME, 0, 1, 'C');
}

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(220, 20, 60);
$pdf->Cell(0, 10, 'Customer Data Report', 0, 1, 'C');
$pdf->Ln(5);

// Customer Information
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Customer Information', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(60, 8, 'Name:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['customer_name'], 1, 1, 'L');
$pdf->Cell(60, 8, 'National ID:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['national_id'] ?: 'N/A', 1, 1, 'L');
$pdf->Cell(60, 8, 'Date of Birth:', 1, 0, 'L', true);
$pdf->Cell(0, 8, date('F j, Y', strtotime($customer['date_of_birth'])), 1, 1, 'L');
$pdf->Cell(60, 8, 'Gender:', 1, 0, 'L', true);
$pdf->Cell(0, 8, ucfirst($customer['gender']), 1, 1, 'L');
$pdf->Cell(60, 8, 'Phone:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['phone'], 1, 1, 'L');
$pdf->Cell(60, 8, 'Email:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['email'] ?: 'N/A', 1, 1, 'L');
$pdf->Cell(60, 8, 'Address:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['address'] ?: 'N/A', 1, 1, 'L');
$pdf->Cell(60, 8, 'Occupation:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['occupation'] ?: 'N/A', 1, 1, 'L');
$pdf->Cell(60, 8, 'Employer:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['employer'] ?: 'N/A', 1, 1, 'L');
$pdf->Cell(60, 8, 'Monthly Income:', 1, 0, 'L', true);
$pdf->Cell(0, 8, $customer['monthly_income'] ? 'UGX ' . number_format(round($customer['monthly_income']), 0) : 'N/A', 1, 1, 'L');
if (!empty($customer['security'])) {
    $pdf->Cell(60, 8, 'Security/Collateral:', 1, 0, 'L', true);
    $pdf->Cell(0, 8, $customer['security'], 1, 1, 'L');
}
if (!empty($customer['next_of_kin_name'])) {
    $pdf->Cell(60, 8, 'Next of Kin:', 1, 0, 'L', true);
    $pdf->Cell(0, 8, $customer['next_of_kin_name'] . ($customer['next_of_kin_phone'] ? ' (' . $customer['next_of_kin_phone'] . ')' : ''), 1, 1, 'L');
}
$pdf->Ln(5);

// Get loans with proper total_paid calculation
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_term_type'")->fetch();
    
    if ($col_check) {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username as officer_name,
                   CASE 
                       WHEN l.loan_term_type = 'weeks' THEN CONCAT(l.loan_term_weeks, 'W')
                       ELSE CONCAT(COALESCE(l.loan_term_months, 0), 'M')
                   END as term_display,
                   CASE 
                       WHEN l.loan_term_type = 'weeks' THEN DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL l.loan_term_weeks WEEK)
                       ELSE DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL COALESCE(l.loan_term_months, 0) MONTH)
                   END as payback_date,
                   COALESCE(SUM(lp.payment_amount), 0) as total_paid
            FROM loans l
            LEFT JOIN users u ON l.officer_id = u.user_id
            LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
            WHERE l.customer_id = ?
            GROUP BY l.loan_id
            ORDER BY l.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username as officer_name,
                   CONCAT(COALESCE(l.loan_term_months, 0), 'M') as term_display,
                   DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL COALESCE(l.loan_term_months, 0) MONTH) as payback_date,
                   COALESCE(SUM(lp.payment_amount), 0) as total_paid
            FROM loans l
            LEFT JOIN users u ON l.officer_id = u.user_id
            LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
            WHERE l.customer_id = ?
            GROUP BY l.loan_id
            ORDER BY l.created_at DESC
        ");
    }
    $stmt->execute([$customer_id]);
} catch (PDOException $e) {
    // Fallback query
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as officer_name,
               CONCAT(COALESCE(l.loan_term_months, 0), 'M') as term_display,
               DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL COALESCE(l.loan_term_months, 0) MONTH) as payback_date,
               COALESCE(SUM(lp.payment_amount), 0) as total_paid
        FROM loans l
        LEFT JOIN users u ON l.officer_id = u.user_id
        LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
        WHERE l.customer_id = ?
        GROUP BY l.loan_id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$customer_id]);
}
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($loans)) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Loan History', 0, 1);
    
    // Table header with professional design (like reports PDF)
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(30, 58, 138); // Dark blue background
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetDrawColor(20, 40, 100); // Darker border
    $pdf->SetLineWidth(0.3);
    
    $pdf->Cell(18, 10, 'Loan ID', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Amount', 1, 0, 'C', true);
    $pdf->Cell(18, 10, 'Rate %', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Term', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Application', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Payback Date', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Total Paid', 1, 0, 'C', true);
    $pdf->Cell(24, 10, 'Status', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(200, 200, 200); // Light gray borders
    $fill = false;
    
    foreach ($loans as $loan) {
        if ($fill) {
            $pdf->SetFillColor(245, 245, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $pdf->Cell(18, 8, '#' . $loan['loan_id'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 8, number_format(round($loan['loan_amount']), 0), 1, 0, 'R', $fill);
        $pdf->Cell(18, 8, $loan['interest_rate'] . '%', 1, 0, 'C', $fill);
        $term_display = $loan['term_display'] ?? (($loan['loan_term_months'] ?? 0) . 'M');
        $pdf->Cell(20, 8, $term_display, 1, 0, 'C', $fill);
        $pdf->Cell(30, 8, date('M j, Y', strtotime($loan['application_date'])), 1, 0, 'C', $fill);
        $payback_date = $loan['payback_date'] ?? 'N/A';
        if ($payback_date != 'N/A' && $payback_date) {
            $payback_date = date('M j, Y', strtotime($payback_date));
        }
        $pdf->Cell(30, 8, $payback_date, 1, 0, 'C', $fill);
        $total_paid = $loan['total_paid'] ?? 0;
        $pdf->Cell(30, 8, number_format(round($total_paid), 0), 1, 0, 'R', $fill);
        $pdf->Cell(24, 8, ucfirst($loan['status']), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(5);
}

// Payment History
$stmt = $pdo->prepare("
    SELECT lp.*, l.loan_id
    FROM loan_payments lp
    JOIN loans l ON lp.loan_id = l.loan_id
    WHERE l.customer_id = ?
    ORDER BY lp.payment_date DESC
    LIMIT 50
");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($payments)) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Payment History (Last 50)', 0, 1);
    
    // Table header with professional design
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(30, 58, 138);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(20, 40, 100);
    $pdf->SetLineWidth(0.3);
    
    $pdf->Cell(18, 10, 'Loan ID', 1, 0, 'C', true);
    $pdf->Cell(32, 10, 'Amount', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Date', 1, 0, 'C', true);
    $pdf->Cell(28, 10, 'Method', 1, 0, 'C', true);
    $pdf->Cell(42, 10, 'Reference', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Comments', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(200, 200, 200);
    $fill = false;
    
    foreach ($payments as $payment) {
        if ($fill) {
            $pdf->SetFillColor(245, 245, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $pdf->Cell(18, 8, '#' . $payment['loan_id'], 1, 0, 'C', $fill);
        $pdf->Cell(32, 8, 'UGX ' . number_format(round($payment['payment_amount']), 0), 1, 0, 'R', $fill);
        $pdf->Cell(30, 8, date('M j, Y', strtotime($payment['payment_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(28, 8, ucfirst(str_replace('_', ' ', $payment['payment_method'])), 1, 0, 'C', $fill);
        $pdf->Cell(42, 8, $payment['transaction_reference'] ?: 'N/A', 1, 0, 'L', $fill);
        // Comments column - wrap text if needed
        $notes = $payment['notes'] ?: 'N/A';
        if (strlen($notes) > 30) {
            $notes = substr($notes, 0, 27) . '...';
        }
        $pdf->Cell(40, 8, $notes, 1, 1, 'L', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(5);
}

// Customer Documents
$documents = getCustomerDocuments($customer_id);
if (!empty($documents)) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Uploaded Documents', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($documents as $doc) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, '- ' . $doc['document_name'], 0, 1);
        if ($doc['description']) {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, '  Description: ' . $doc['description'], 0, 1);
        }
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 5, '  Uploaded: ' . date('M j, Y g:i A', strtotime($doc['created_at'])) . ($doc['uploaded_by_name'] ? ' by ' . $doc['uploaded_by_name'] : '') . ' | Size: ' . number_format($doc['file_size'] / 1024, 2) . ' KB', 0, 1);
        $pdf->Ln(3);
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->Ln(5);
}

// Approval and Signature lines - dynamic positioning
$current_y = $pdf->GetY();
$required_space = 20; // Space needed for approval/signature lines

// Check if we need a new page
if ($current_y > (297 - $required_space - 15)) { // 297mm is A4 height, 15mm for footer
    $pdf->AddPage();
}

// Position at bottom, leaving space for footer
$pdf->SetY(-35);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);

// Left side - Approved by (shorter line)
$pdf->SetX(10);
$pdf->Cell(90, 8, 'Approved by: ________________________________________', 0, 0, 'L');

// Right side - Signature (on same line)
$pdf->SetX(100);
$pdf->Cell(0, 8, 'Signature: ________________________________________', 0, 1, 'L');

ob_clean();
$pdf->Output('customer_' . $customer_id . '_' . date('Y-m-d') . '.pdf', 'D');
exit();

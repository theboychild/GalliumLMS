<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdminOrOfficer();

$is_admin = isAdmin();
$user_id = $_SESSION['user_id'];

// Date validation constants
$min_year = 2015;
$min_month = 1;
$current_year = (int)date('Y');
$current_month = (int)date('m');

// Get selected year and month
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : 0; // 0 means whole year

// Validate year range (2015 to current year)
if ($selected_year < $min_year || $selected_year > $current_year) {
    $selected_year = $current_year;
}

// Validate month if provided (1-12, and not in future)
if ($selected_month > 0) {
    if ($selected_month < 1 || $selected_month > 12) {
        $selected_month = 0; // Invalid month, show whole year
    } elseif ($selected_year == $current_year && $selected_month > $current_month) {
        $selected_month = 0; // Future month, show whole year
    } elseif ($selected_year == $min_year && $selected_month < $min_month) {
        $selected_month = $min_month; // Before Jan 2015, use Jan 2015
    }
}

// Calculate date range
if ($selected_month > 0) {
    // Specific month selected
    $start_date = date('Y-m-01', mktime(0, 0, 0, $selected_month, 1, $selected_year));
    $days_in_month = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
    $end_date = date('Y-m-' . $days_in_month, mktime(0, 0, 0, $selected_month, $days_in_month, $selected_year));
    $period_label = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
} else {
    // Whole year selected
    $start_date = date('Y-01-01', mktime(0, 0, 0, 1, 1, $selected_year));
    $end_date = date('Y-12-31', mktime(0, 0, 0, 12, 31, $selected_year));
    $period_label = $selected_year;
}

// Get monthly/daily performance data
$monthly_data = [];
try {
    if ($selected_month > 0) {
        // For specific month, show daily data
        $sql = "
            SELECT 
                DATE_FORMAT(application_date, '%Y-%m-%d') as month,
                COUNT(*) as loan_count,
                SUM(loan_amount) as total_loans,
                SUM(CASE WHEN status = 'approved' THEN loan_amount ELSE 0 END) as approved_loans,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM loans
            WHERE application_date BETWEEN ? AND ?
        ";
        
        if (!$is_admin) {
            $sql .= " AND officer_id = ?";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(application_date, '%Y-%m-%d') ORDER BY month";
    } else {
        // For whole year, show monthly data
        $sql = "
            SELECT 
                DATE_FORMAT(application_date, '%Y-%m') as month,
                COUNT(*) as loan_count,
                SUM(loan_amount) as total_loans,
                SUM(CASE WHEN status = 'approved' THEN loan_amount ELSE 0 END) as approved_loans,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM loans
            WHERE application_date BETWEEN ? AND ?
        ";
        
        if (!$is_admin) {
            $sql .= " AND officer_id = ?";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month";
    }
    
    $stmt = $pdo->prepare($sql);
    if ($is_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $user_id]);
    }
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $monthly_data = [];
}

// Get payment collections by month/day
$collection_data = [];
try {
    if ($selected_month > 0) {
        // For specific month, show daily data
        $sql = "
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m-%d') as month,
                SUM(payment_amount) as total_collections,
                SUM(principal_amount) as principal_collected,
                SUM(interest_amount) as interest_collected,
                COUNT(*) as payment_count
            FROM loan_payments
            WHERE payment_date BETWEEN ? AND ?
        ";
        
        if (!$is_admin) {
            $sql .= " AND received_by = ?";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(payment_date, '%Y-%m-%d') ORDER BY month";
    } else {
        // For whole year, show monthly data
        $sql = "
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(payment_amount) as total_collections,
                SUM(principal_amount) as principal_collected,
                SUM(interest_amount) as interest_collected,
                COUNT(*) as payment_count
            FROM loan_payments
            WHERE payment_date BETWEEN ? AND ?
        ";
        
        if (!$is_admin) {
            $sql .= " AND received_by = ?";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month";
    }
    
    $stmt = $pdo->prepare($sql);
    if ($is_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $user_id]);
    }
    $collection_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $collection_data = [];
}

// Get loan status breakdown
$status_breakdown = [];
try {
    $sql = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(loan_amount) as total_amount
        FROM loans
        WHERE application_date BETWEEN ? AND ?
    ";
    
    if (!$is_admin) {
        $sql .= " AND officer_id = ?";
    }
    
    $sql .= " GROUP BY status";
    
    $stmt = $pdo->prepare($sql);
    if ($is_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $user_id]);
    }
    $status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $status_breakdown = [];
}

// Get outstanding loans data
$outstanding_data = [];
try {
    $sql = "
        SELECT 
            DATE_FORMAT(application_date, '%Y-%m') as month,
            SUM(loan_amount) as total_loans,
            SUM(
                CASE 
                    WHEN status IN ('approved', 'active') THEN loan_amount
                    ELSE 0
                END
            ) as active_loans
        FROM loans
        WHERE application_date BETWEEN ? AND ?
    ";
    
    if (!$is_admin) {
        $sql .= " AND officer_id = ?";
    }
    
    $sql .= " GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month";
    
    $stmt = $pdo->prepare($sql);
    if ($is_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $user_id]);
    }
    $outstanding_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $outstanding_data = [];
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $fields = isset($_GET['fields']) ? $_GET['fields'] : [];
    
    if ($export_type === 'xls' || $export_type === 'csv') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="loan_details_report_' . date('Y-m-d') . '.' . ($export_type === 'xls' ? 'xls' : 'csv') . '"');
        
        // Get all loans with customer and payment details
        $loans_query = "
            SELECT 
                l.loan_id,
                c.customer_name,
                c.phone,
                c.email,
                l.loan_amount,
                l.loan_term_months as duration,
                l.interest_rate,
                l.application_date as loan_date,
                l.disbursement_date,
                DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL l.loan_term_months MONTH) as payback_date,
                COALESCE(SUM(lp.payment_amount), 0) as total_paid,
                l.status
            FROM loans l
            LEFT JOIN customers c ON l.customer_id = c.customer_id
            LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
            WHERE l.application_date BETWEEN ? AND ?
            GROUP BY l.loan_id
            ORDER BY l.application_date DESC
        ";
        
        $stmt = $pdo->prepare($loans_query);
        $stmt->execute([$start_date, $end_date]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Define available fields
        $available_fields = [
            'loan_id' => 'Loan ID',
            'customer_name' => 'Client Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'loan_amount' => 'Loan Amount (UGX)',
            'duration' => 'Duration (Months)',
            'interest_rate' => 'Interest Rate (%)',
            'loan_date' => 'Loan Date',
            'payback_date' => 'Payback Date',
            'total_paid' => 'Total Paid (UGX)',
            'status' => 'Status'
        ];
        
        // If no fields selected, use all fields
        if (empty($fields)) {
            $fields = array_keys($available_fields);
        }
        
        // Output headers
        $delimiter = $export_type === 'csv' ? ',' : "\t";
        $header_parts = [];
        foreach ($fields as $field) {
            if (isset($available_fields[$field])) {
                $header_parts[] = $available_fields[$field];
            }
        }
        echo implode($delimiter, $header_parts) . "\n";
        
        // Output data
        foreach ($loans as $loan) {
            $row_parts = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'loan_id':
                        $row_parts[] = $loan['loan_id'];
                        break;
                    case 'customer_name':
                        $row_parts[] = '"' . str_replace('"', '""', $loan['customer_name']) . '"';
                        break;
                    case 'phone':
                        $row_parts[] = $loan['phone'];
                        break;
                    case 'email':
                        $row_parts[] = $loan['email'] ?? '';
                        break;
                    case 'loan_amount':
                        $row_parts[] = number_format(round($loan['loan_amount']), 0);
                        break;
                    case 'duration':
                        $row_parts[] = $loan['duration'];
                        break;
                    case 'interest_rate':
                        $row_parts[] = number_format($loan['interest_rate'], 2);
                        break;
                    case 'loan_date':
                        $row_parts[] = $loan['loan_date'];
                        break;
                    case 'payback_date':
                        $row_parts[] = $loan['payback_date'] ?? 'N/A';
                        break;
                    case 'total_paid':
                        $row_parts[] = number_format(round($loan['total_paid']), 0);
                        break;
                    case 'status':
                        $row_parts[] = $loan['status'];
                        break;
                }
            }
            echo implode($delimiter, $row_parts) . "\n";
        }
        exit();
    }
    
    if ($export_type === 'pdf') {
        $fpdf_path = __DIR__ . '/../includes/fpdf/fpdf.php';
        if (!file_exists($fpdf_path)) {
            die('Error: FPDF library not found. Please ensure includes/fpdf/fpdf.php exists.');
        }
        
        require_once $fpdf_path;
        
        try {
            // Create custom PDF class with footer
            class ReportsPDF extends FPDF {
                function Footer() {
                    $this->SetY(-15);
                    $this->SetFont('Arial', 'I', 8);
                    $this->SetTextColor(128, 128, 128);
                    $current_year = date('Y');
                    $this->Cell(0, 5, 'Copyright © ' . $current_year . ' ' . APP_NAME . '. All rights reserved.', 0, 1, 'C');
                    $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' - Generated on ' . date('M d, Y h:i A'), 0, 0, 'C');
                }
            }
            
            $pdf = new ReportsPDF('L', 'mm', 'A4'); // Landscape orientation for better table display
            $pdf->SetTitle('Loan Performance Report - ' . APP_NAME);
            $pdf->SetAuthor(APP_NAME);
            $pdf->SetAutoPageBreak(true, 20); // Auto page break with 20mm margin
            
            // Find logo path first (before creating page)
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
            
            // Dynamic title based on time frame
            if ($selected_month > 0) {
                $report_title = 'Monthly Loan Report of ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
            } else {
                $report_title = 'Yearly Report of ' . $selected_year;
            }
            
            // Define available fields
            $available_fields = [
                'loan_id' => 'Loan ID',
                'customer_name' => 'Client Name',
                'phone' => 'Phone',
                'email' => 'Email',
                'loan_amount' => 'Amount',
                'duration' => 'Duration',
                'interest_rate' => 'Rate %',
                'loan_date' => 'Loan Date',
                'payback_date' => 'Payback Date',
                'total_paid' => 'Total Paid',
                'status' => 'Status'
            ];
            
            // If no fields selected, use all fields
            if (empty($fields)) {
                $fields = array_keys($available_fields);
            }
            
            // Get all loans with customer and payment details
            $loans_query = "
                SELECT 
                    l.loan_id,
                    c.customer_name,
                    c.phone,
                    c.email,
                    l.loan_amount,
                    l.loan_term_months,
                    l.loan_term_weeks,
                    l.loan_term_type,
                    CASE 
                        WHEN l.loan_term_type = 'weeks' THEN CONCAT(l.loan_term_weeks, 'W')
                        ELSE CONCAT(COALESCE(l.loan_term_months, 0), 'M')
                    END as duration,
                    l.interest_rate,
                    l.application_date as loan_date,
                    l.disbursement_date,
                    CASE 
                        WHEN l.loan_term_type = 'weeks' THEN DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL l.loan_term_weeks WEEK)
                        ELSE DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL COALESCE(l.loan_term_months, 0) MONTH)
                    END as payback_date,
                    COALESCE(SUM(lp.payment_amount), 0) as total_paid,
                    l.status
                FROM loans l
                LEFT JOIN customers c ON l.customer_id = c.customer_id
                LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
                WHERE l.application_date BETWEEN ? AND ?
                GROUP BY l.loan_id
                ORDER BY l.application_date DESC
            ";
            
            try {
                $stmt = $pdo->prepare($loans_query);
                $stmt->execute([$start_date, $end_date]);
                $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // If new columns don't exist, fall back to old query
                $loans_query_fallback = "
                    SELECT 
                        l.loan_id,
                        c.customer_name,
                        c.phone,
                        c.email,
                        l.loan_amount,
                        CONCAT(COALESCE(l.loan_term_months, 0), 'M') as duration,
                        l.interest_rate,
                        l.application_date as loan_date,
                        l.disbursement_date,
                        DATE_ADD(COALESCE(l.disbursement_date, l.application_date), INTERVAL COALESCE(l.loan_term_months, 0) MONTH) as payback_date,
                        COALESCE(SUM(lp.payment_amount), 0) as total_paid,
                        l.status
                    FROM loans l
                    LEFT JOIN customers c ON l.customer_id = c.customer_id
                    LEFT JOIN loan_payments lp ON l.loan_id = lp.loan_id
                    WHERE l.application_date BETWEEN ? AND ?
                    GROUP BY l.loan_id
                    ORDER BY l.application_date DESC
                ";
                $stmt = $pdo->prepare($loans_query_fallback);
                $stmt->execute([$start_date, $end_date]);
                $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (empty($loans)) {
                // Only create page if there's no data to show message
                $pdf->AddPage();
                
                // Add company logo on top-left
                if ($logo_path) {
                    $pdf->Image($logo_path, 10, 10, 50); // 50mm width, significant size
                }
                
                // Title section (right-aligned to leave space for logo)
                $pdf->SetY(15);
                $pdf->SetX(70);
                $pdf->SetFont('Arial', 'B', 20);
                $pdf->SetTextColor(30, 58, 138);
                $pdf->Cell(0, 10, APP_NAME, 0, 1, 'L');
                $pdf->SetX(70);
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->SetTextColor(220, 20, 60);
                $pdf->Cell(0, 8, $report_title, 0, 1, 'L');
                $pdf->SetX(70);
                $pdf->SetFont('Arial', '', 10);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(0, 5, 'Generated: ' . date('M d, Y h:i A'), 0, 1, 'L');
                $pdf->Ln(8);
                
                $pdf->SetFont('Arial', 'I', 12);
                $pdf->Cell(0, 10, 'No loans found for the selected period.', 0, 1, 'C');
            } else {
                // Add page only when we have data
                $pdf->AddPage();
                
                // Add company logo on top-left
                if ($logo_path) {
                    $pdf->Image($logo_path, 10, 10, 50); // 50mm width, significant size
                }
                
                // Title section (right-aligned to leave space for logo)
                $pdf->SetY(15);
                $pdf->SetX(70);
                $pdf->SetFont('Arial', 'B', 20);
                $pdf->SetTextColor(30, 58, 138);
                $pdf->Cell(0, 10, APP_NAME, 0, 1, 'L');
                $pdf->SetX(70);
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->SetTextColor(220, 20, 60);
                $pdf->Cell(0, 8, $report_title, 0, 1, 'L');
                $pdf->SetX(70);
                $pdf->SetFont('Arial', '', 10);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(0, 5, 'Generated: ' . date('M d, Y h:i A'), 0, 1, 'L');
                $pdf->Ln(8);
                // Calculate column widths - balanced distribution (no scaling needed)
                $col_widths = [
                    'loan_id' => 18,
                    'customer_name' => 42, // Good space for names
                    'phone' => 24,
                    'email' => 30,
                    'loan_amount' => 20, // Balanced amount column
                    'duration' => 16,
                    'interest_rate' => 14,
                    'loan_date' => 26,
                    'payback_date' => 26,
                    'total_paid' => 22,
                    'status' => 18
                ];
                
                // Calculate total width for selected fields
                $total_width = 0;
                foreach ($fields as $field) {
                    if (isset($col_widths[$field])) {
                        $total_width += $col_widths[$field];
                    }
                }
                
                // Use full width available (280mm in landscape A4 minus margins)
                // If total exceeds available width, scale proportionally
                $available_width = 280;
                if ($total_width > $available_width) {
                    $scale = $available_width / $total_width;
                } else {
                    $scale = 1;
                }
                
                // Table header with distinct design
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(30, 58, 138); // Dark blue background
                $pdf->SetTextColor(255, 255, 255); // White text
                $pdf->SetDrawColor(20, 40, 100); // Darker border
                $pdf->SetLineWidth(0.3);
                
                foreach ($fields as $field) {
                    if (isset($available_fields[$field]) && isset($col_widths[$field])) {
                        $pdf->Cell($col_widths[$field] * $scale, 10, $available_fields[$field], 1, 0, 'C', true);
                    }
                }
                $pdf->Ln();
                
                // Table data with better readability
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(200, 200, 200); // Light gray borders
                $fill = false;
                
                foreach ($loans as $loan) {
                    foreach ($fields as $field) {
                        if (!isset($col_widths[$field])) continue;
                        
                        $value = '';
                        switch ($field) {
                            case 'loan_id':
                                $value = $loan['loan_id'];
                                break;
                            case 'customer_name':
                                $value = $loan['customer_name']; // Full name now
                                $align = 'L'; // Left align names
                                break;
                            case 'phone':
                                $value = $loan['phone'];
                                break;
                            case 'email':
                                $value = substr($loan['email'] ?? '', 0, 20);
                                break;
                            case 'loan_amount':
                                $value = number_format(round($loan['loan_amount']), 0);
                                break;
                            case 'duration':
                                $value = $loan['duration']; // Already formatted as 'M' or 'W'
                                break;
                            case 'interest_rate':
                                $value = $loan['interest_rate'] . '%';
                                break;
                            case 'loan_date':
                                $value = date('M j, Y', strtotime($loan['loan_date']));
                                break;
                            case 'payback_date':
                                $value = $loan['payback_date'] ? date('M j, Y', strtotime($loan['payback_date'])) : 'N/A';
                                break;
                            case 'total_paid':
                                $value = number_format(round($loan['total_paid']), 0);
                                break;
                            case 'status':
                                $value = ucfirst($loan['status']);
                                break;
                        }
                        // Alternate row colors for better readability
                        if ($fill) {
                            $pdf->SetFillColor(245, 245, 250);
                        } else {
                            $pdf->SetFillColor(255, 255, 255);
                        }
                        // Use left align for names, center for others
                        $cell_align = isset($align) ? $align : 'C';
                        unset($align); // Reset for next iteration
                        $pdf->Cell($col_widths[$field] * $scale, 8, $value, 1, 0, $cell_align, $fill);
                    }
                    $pdf->Ln();
                    $fill = !$fill;
                }
                
                // Approval and Signature section - dynamic positioning
                $current_y = $pdf->GetY();
                $required_space = 20; // Space needed for approval/signature lines
                
                // Check if we need a new page (for landscape: 210mm height)
                if ($current_y > (210 - $required_space - 15)) { // 210mm is landscape A4 height, 15mm for footer
                    $pdf->AddPage();
                    // Re-add logo and title if new page
                    if ($logo_path) {
                        $pdf->Image($logo_path, 10, 10, 50);
                    }
                    $pdf->SetY(25);
                } else {
                    // Skip 4 lines before approval section
                    $pdf->Ln(20);
                }
                
                // Approval and Signature section at bottom
                $pdf->SetFont('Arial', '', 11);
                $pdf->SetTextColor(0, 0, 0);
                
                // Left side - Approved by (shorter line)
                $pdf->SetX(10);
                $pdf->Cell(90, 8, 'Approved by: ________________________________________', 0, 0, 'L');
                
                // Right side - Signature (on same line)
                $pdf->SetX(100);
                $pdf->Cell(0, 8, 'Signature: ________________________________________', 0, 1, 'L');
            }
            
            // Footer is handled by the Footer() method in ReportsPDF class
            
            $pdf->Output('D', 'loan_report_' . date('Y-m-d') . '.pdf');
    exit();
        } catch (Exception $e) {
            die('PDF Generation Error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
            <div class="reports-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-md);">
                    <div>
                        <h1>LOAN MANAGEMENT SYSTEM</h1>
                        <p class="reports-subtitle">Overview - Key performance indicators available to derive insights.</p>
                    </div>
                    <div style="display: flex; gap: var(--spacing-md); align-items: center; flex-wrap: wrap;">
                        <form method="GET" style="display: flex; align-items: center; gap: var(--spacing-md); flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: var(--spacing-sm);">
                                <label style="font-weight: 600; color: var(--dark-gray); white-space: nowrap;">Select Period:</label>
                                <select name="year" id="yearSelect" class="form-control" style="min-width: 100px;" onchange="updateMonthOptions()">
                                    <?php 
                                    for ($y = $current_year; $y >= $min_year; $y--): 
                                        $selected = ($selected_year == $y) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $y; ?>" <?php echo $selected; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="month" id="monthSelect" class="form-control" style="min-width: 150px;">
                                    <option value="0" <?php echo $selected_month == 0 ? 'selected' : ''; ?>>Whole Year</option>
                                    <?php 
                                    $months = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];
                                    foreach ($months as $m => $month_name): 
                                        // Disable future months
                                        $disabled = ($selected_year == $current_year && $m > $current_month) ? 'disabled' : '';
                                        // Disable months before Jan 2015
                                        if ($selected_year == $min_year && $m < $min_month) {
                                            $disabled = 'disabled';
                                        }
                                        $selected = ($selected_month == $m) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $m; ?>" <?php echo $selected . ' ' . $disabled; ?>><?php echo $month_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                
                                <?php if ($selected_year != $current_year || $selected_month != 0): ?>
                                <a href="reports.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="kpi-cards-grid-reports">
                <div class="kpi-card-reports" style="border-left: 4px solid var(--brand-blue); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="kpi-value-reports" style="color: var(--brand-blue); font-size: var(--font-size-3xl);"><?php echo number_format(!empty($monthly_data) ? array_sum(array_column($monthly_data, 'loan_count')) : 0); ?></div>
                    <div class="kpi-label-reports" style="display: flex; align-items: center; gap: var(--spacing-xs); justify-content: center;"><i class="fas fa-file-invoice-dollar"></i> Total Loans</div>
                </div>
                
                <div class="kpi-card-reports" style="border-left: 4px solid var(--success-green); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <?php 
                    $total_customers = 0;
                    try {
                        // Count customers who registered in the selected year
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE YEAR(created_at) = ?");
                        $stmt->execute([$selected_year]);
                        $total_customers = $stmt->fetchColumn() ?: 0;
                    } catch (PDOException $e) {}
                    ?>
                    <div class="kpi-value-reports" style="color: var(--success-green); font-size: var(--font-size-3xl);"><?php echo number_format($total_customers); ?></div>
                    <div class="kpi-label-reports" style="display: flex; align-items: center; gap: var(--spacing-xs); justify-content: center;"><i class="fas fa-users"></i> Total Customers (<?php echo $period_label; ?>)</div>
                </div>
                
                <div class="kpi-card-reports" style="border-left: 4px solid var(--brand-red); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="kpi-value-reports" style="color: var(--brand-red); font-size: var(--font-size-3xl);"><?php echo formatCurrency(!empty($collection_data) ? array_sum(array_column($collection_data, 'total_collections')) : 0); ?></div>
                    <div class="kpi-label-reports" style="display: flex; align-items: center; gap: var(--spacing-xs); justify-content: center;"><i class="fas fa-money-bill-wave"></i> Total Revenue</div>
                </div>
                
                <div class="kpi-card-reports" style="border-left: 4px solid var(--accent-gold); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="kpi-value-reports" style="color: var(--accent-gold); font-size: var(--font-size-3xl);"><?php echo number_format(!empty($monthly_data) ? array_sum(array_column($monthly_data, 'loan_count')) : 0); ?></div>
                    <div class="kpi-label-reports" style="display: flex; align-items: center; gap: var(--spacing-xs); justify-content: center;"><i class="fas fa-file-alt"></i> Total Applications</div>
                </div>
            </div>
            
            <div class="reports-charts-grid">
                <div class="chart-panel-reports" style="border-left: 4px solid #667eea; box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-chart-pie"></i> Loan Status Distribution
                    </div>
                    <div class="chart-wrapper-reports">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-panel-reports" style="border-left: 4px solid var(--info-blue); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, var(--info-blue) 0%, #117a8b 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-calculator"></i> Average Loan Metrics
                    </div>
                    <div class="metrics-display-reports">
                        <?php 
                        $avg_loan = 0;
                        $avg_term = 0;
                        if (!empty($monthly_data) && array_sum(array_column($monthly_data, 'loan_count')) > 0) {
                            $avg_loan = array_sum(array_column($monthly_data, 'total_loans')) / array_sum(array_column($monthly_data, 'loan_count'));
                        }
                        try {
                            $result = $pdo->query("SELECT AVG(loan_term_months) as avg FROM loans")->fetch(PDO::FETCH_ASSOC);
                            $avg_term = $result['avg'] ?? 0;
                        } catch (PDOException $e) {}
                        ?>
                        <div class="metric-display-reports">
                            <div class="metric-label-reports"><?php echo formatCurrency($avg_loan); ?> Average Loan</div>
                            <div class="metric-label-reports"><?php echo number_format($avg_term, 1); ?> Average Duration(Months)</div>
                        </div>
                    </div>
                </div>
                
                <div class="chart-panel-reports" style="border-left: 4px solid var(--brand-red); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, var(--brand-red) 0%, #8E0018 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-chart-line"></i> Revenue (<?php echo $period_label; ?>)
                    </div>
                    <div class="chart-wrapper-reports">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-panel-reports" style="border-left: 4px solid var(--success-green); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, var(--success-green) 0%, #1e7e34 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-chart-bar"></i> Loans by Status (<?php echo $period_label; ?>)
                    </div>
                    <div class="chart-wrapper-reports">
                        <canvas id="loansStatusChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-panel-reports" style="border-left: 4px solid var(--accent-gold); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, var(--accent-gold) 0%, var(--accent-gold-dark) 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-chart-area"></i> Loan Amount Ranges
                    </div>
                    <div class="chart-wrapper-reports">
                        <canvas id="amountRangeChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-panel-reports" style="border-left: 4px solid var(--brand-blue); box-shadow: var(--shadow-md); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)';">
                    <div class="chart-title-reports" style="background: linear-gradient(135deg, var(--brand-blue) 0%, var(--dark-blue) 100%); color: var(--white); padding: var(--spacing-md) var(--spacing-lg); margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-md) calc(-1 * var(--spacing-lg)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; font-weight: 600; display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-users"></i> Customer Demographics
                    </div>
                    <div class="chart-wrapper-reports">
                        <canvas id="demographicsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-top: var(--spacing-xl); border-left: 4px solid var(--brand-red); box-shadow: var(--shadow-md);">
                <div class="card-header" style="background: linear-gradient(135deg, var(--brand-blue) 0%, var(--dark-blue) 100%); color: var(--white); border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                    <h2 class="card-title" style="color: var(--white); margin: 0;">
                        <i class="fas fa-download"></i> Export Data
                    </h2>
                </div>
                <div class="card-body" style="padding: var(--spacing-xl);">
                    <p style="margin-bottom: var(--spacing-lg); color: var(--medium-gray); font-size: var(--font-size-base); text-align: center;">Select fields to export and choose your preferred format:</p>
                    <div style="display: flex; gap: var(--spacing-md); justify-content: center; flex-wrap: wrap;">
                        <button type="button" onclick="openExportModal('pdf')" class="btn" style="background: var(--brand-red); color: var(--white); padding: var(--spacing-md) var(--spacing-xl); font-weight: 600; box-shadow: var(--shadow-sm); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-sm)';">
                            <i class="fas fa-file-pdf"></i> Export as PDF
                        </button>
                        <button type="button" onclick="openExportModal('csv')" class="btn" style="background: var(--success-green); color: var(--white); padding: var(--spacing-md) var(--spacing-xl); font-weight: 600; box-shadow: var(--shadow-sm); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-sm)';">
                            <i class="fas fa-file-csv"></i> Export as CSV
                        </button>
                        <button type="button" onclick="openExportModal('xls')" class="btn" style="background: var(--success-green); color: var(--white); padding: var(--spacing-md) var(--spacing-xl); font-weight: 600; box-shadow: var(--shadow-sm); transition: all var(--transition-base);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-sm)';">
                            <i class="fas fa-file-excel"></i> Export as XLS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .reports-header {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .reports-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
            letter-spacing: 0.5px;
        }
        
        .reports-subtitle {
            margin: var(--spacing-xs) 0 0 0;
            color: var(--medium-gray);
            font-size: 0.95rem;
        }
        
        .year-selector-sidebar {
            margin-top: auto;
        }
        
        .year-btn-sidebar {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: block;
            text-align: center;
        }
        
        .year-btn-sidebar:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
        }
        
        .year-btn-sidebar.active {
            background: var(--white);
            color: var(--primary-blue);
            border-color: var(--white);
        }
        
        .kpi-cards-grid-reports {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .kpi-card-reports {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .kpi-value-reports {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: var(--spacing-sm);
        }
        
        .kpi-label-reports {
            font-size: 1rem;
            color: var(--medium-gray);
            font-weight: 500;
        }
        
        .reports-charts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .chart-panel-reports {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }
        
        .chart-title-reports {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: var(--spacing-md);
        }
        
        .chart-wrapper-reports {
            position: relative;
            height: 250px;
        }
        
        .metrics-display-reports {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            padding: var(--spacing-md) 0;
        }
        
        .metric-display-reports {
            text-align: center;
        }
        
        .metric-label-reports {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: var(--spacing-sm);
        }
        
        @media (max-width: 1200px) {
            .reports-charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .kpi-cards-grid-reports,
            .reports-charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        // Format month labels
        const formatMonth = (monthStr) => {
            const [year, month] = monthStr.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        };
        
        const monthLabels = <?php 
        if ($selected_month > 0) {
            // For specific month, format as day
            echo json_encode(array_map(function($m) { 
                $parts = explode('-', $m); 
                return date('M d', mktime(0,0,0,$parts[1],$parts[2],$parts[0])); 
            }, array_column($monthly_data, 'month')));
        } else {
            // For whole year, format as month
            echo json_encode(array_map(function($m) { 
                $parts = explode('-', $m); 
                return date('M Y', mktime(0,0,0,$parts[1],1,$parts[0])); 
            }, array_column($monthly_data, 'month')));
        }
        ?>;
        
        const loanAmounts = <?php echo json_encode(array_column($monthly_data, 'total_loans')); ?>;
        const approvedAmounts = <?php echo json_encode(array_column($monthly_data, 'approved_loans')); ?>;
        const rejectedCounts = <?php echo json_encode(array_column($monthly_data, 'rejected_count')); ?>;
        const loanCounts = <?php echo json_encode(array_column($monthly_data, 'loan_count')); ?>;
        
        const collectionLabels = <?php 
        if ($selected_month > 0) {
            // For specific month, format as day
            echo json_encode(array_map(function($m) { 
                $parts = explode('-', $m); 
                return date('M d', mktime(0,0,0,$parts[1],$parts[2],$parts[0])); 
            }, array_column($collection_data, 'month')));
        } else {
            // For whole year, format as month
            echo json_encode(array_map(function($m) { 
                $parts = explode('-', $m); 
                return date('M Y', mktime(0,0,0,$parts[1],1,$parts[0])); 
            }, array_column($collection_data, 'month')));
        }
        ?>;
        const totalCollections = <?php echo json_encode(array_column($collection_data, 'total_collections')); ?>;
        const principalCollected = <?php echo json_encode(array_column($collection_data, 'principal_collected')); ?>;
        const interestCollected = <?php echo json_encode(array_column($collection_data, 'interest_collected')); ?>;
        
        // Calculate approval rates
        const approvalRates = loanCounts.map((count, index) => {
            return count > 0 ? ((count - rejectedCounts[index]) / count * 100).toFixed(1) : 0;
        });
        
        // Status Distribution Chart (Doughnut)
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const statusData = <?php echo json_encode($status_breakdown ?? []); ?>;
            const statusLabels = (statusData && Array.isArray(statusData) && statusData.length > 0)
                ? statusData.map(item => item && item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Unknown')
                : [];
            const statusCounts = (statusData && Array.isArray(statusData) && statusData.length > 0)
                ? statusData.map(item => parseInt(item.count) || 0)
                : [];
            
            new Chart(statusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(245, 87, 108, 0.8)',
                            'rgba(67, 233, 123, 0.8)',
                            'rgba(79, 172, 254, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Yearly Revenue Chart (Quarterly)
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const quarters = ['Qtr 1', 'Qtr 2', 'Qtr 3', 'Qtr 4'];
            const revenueByQuarter = [0, 0, 0, 0];
            const collectionData = <?php echo json_encode($collection_data ?? []); ?>;
            if (collectionData && Array.isArray(collectionData)) {
                collectionData.forEach(item => {
                    if (item && item.month) {
                        const month = parseInt(item.month.split('-')[1]);
                        const quarter = Math.floor((month - 1) / 3);
                        if (quarter >= 0 && quarter < 4) {
                            revenueByQuarter[quarter] += parseFloat(item.total_collections) || 0;
                        }
                    }
                });
            }
            
            new Chart(revenueCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: quarters,
                    datasets: [{
                        label: 'Revenue (UGX)',
                        data: revenueByQuarter,
                        backgroundColor: 'rgba(176, 0, 32, 0.8)',
                        borderColor: 'rgba(176, 0, 32, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return (value / 1000000).toFixed(1) + 'M';
                                    } else if (value >= 1000) {
                                        return (value / 1000).toFixed(0) + 'K';
                                    }
                                    return value;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Loans by Status Chart
        const loansStatusCtx = document.getElementById('loansStatusChart');
        if (loansStatusCtx) {
            const loansStatusData = <?php echo json_encode($status_breakdown ?? []); ?>;
            const loansLabels = (loansStatusData && Array.isArray(loansStatusData) && loansStatusData.length > 0)
                ? loansStatusData.map(item => item && item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Unknown')
                : [];
            const loansCounts = (loansStatusData && Array.isArray(loansStatusData) && loansStatusData.length > 0)
                ? loansStatusData.map(item => parseInt(item.count) || 0)
                : [];
            
            new Chart(loansStatusCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: loansLabels,
                    datasets: [{
                        label: 'Number of Loans',
                        data: loansCounts,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.7)',
                            'rgba(245, 87, 108, 0.7)',
                            'rgba(67, 233, 123, 0.7)',
                            'rgba(79, 172, 254, 0.7)',
                            'rgba(118, 75, 162, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(239, 68, 68, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Loan Amount Ranges Chart (Horizontal Bar)
        const amountRangeCtx = document.getElementById('amountRangeChart');
        if (amountRangeCtx) {
            <?php
            $rangeCounts = [0, 0, 0, 0];
            try {
                $rangeSql = "
                    SELECT 
                        CASE 
                            WHEN loan_amount < 1500000 THEN 0
                            WHEN loan_amount < 2500000 THEN 1
                            WHEN loan_amount < 3500000 THEN 2
                            ELSE 3
                        END as range_idx,
                        COUNT(*) as count
                    FROM loans
                    WHERE application_date BETWEEN ? AND ?
                ";
                
                if (!$is_admin) {
                    $rangeSql .= " AND officer_id = ?";
                }
                
                $rangeSql .= " GROUP BY range_idx";
                
                $rangeStmt = $pdo->prepare($rangeSql);
                if ($is_admin) {
                    $rangeStmt->execute([$start_date, $end_date]);
                } else {
                    $rangeStmt->execute([$start_date, $end_date, $user_id]);
                }
                $rangeData = $rangeStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rangeData as $r) {
                    $rangeCounts[$r['range_idx']] = $r['count'];
                }
            } catch (PDOException $e) {
                // Table doesn't exist yet
            }
            ?>
            const ranges = ['500K-1.5M', '1.5M-2.5M', '2.5M-3.5M', '3.5M and Above'];
            const rangeCounts = <?php echo json_encode($rangeCounts); ?>;
            
            new Chart(amountRangeCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ranges,
                    datasets: [{
                        label: 'Number of Loans',
                        data: rangeCounts,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Customer Demographics Chart
        const demographicsCtx = document.getElementById('demographicsChart');
        if (demographicsCtx) {
            <?php
            $demographics = [];
            try {
                // Get demographics for customers who have loans in the selected period
                $demoSql = "
                    SELECT c.gender, COUNT(DISTINCT c.customer_id) as count
                    FROM customers c
                    INNER JOIN loans l ON c.customer_id = l.customer_id
                    WHERE l.application_date BETWEEN ? AND ?
                ";
                
                if (!$is_admin) {
                    $demoSql .= " AND l.officer_id = ?";
                }
                
                $demoSql .= " GROUP BY c.gender";
                
                $demoStmt = $pdo->prepare($demoSql);
                if ($is_admin) {
                    $demoStmt->execute([$start_date, $end_date]);
                } else {
                    $demoStmt->execute([$start_date, $end_date, $user_id]);
                }
                $demographics = $demoStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table doesn't exist yet
            }
            ?>
            const demoData = <?php echo json_encode($demographics ?? []); ?>;
            const demoLabels = (demoData && Array.isArray(demoData) && demoData.length > 0) 
                ? demoData.map(item => item && item.gender ? item.gender.charAt(0).toUpperCase() + item.gender.slice(1) : 'Unknown')
                : ['Male', 'Female'];
            const demoCounts = (demoData && Array.isArray(demoData) && demoData.length > 0)
                ? demoData.map(item => parseInt(item.count) || 0)
                : [0, 0];
            
            <?php
            $age_demographics = [];
            try {
                // Get age demographics for customers who have loans in the selected period
                $ageSql = "
                    SELECT 
                        CASE 
                            WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                            WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
                            WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36-45'
                            WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 46 AND 55 THEN '46-55'
                            WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) >= 56 THEN '56+'
                            ELSE 'Unknown'
                        END as age_group,
                        COUNT(DISTINCT c.customer_id) as count
                    FROM customers c
                    INNER JOIN loans l ON c.customer_id = l.customer_id
                    WHERE l.application_date BETWEEN ? AND ?
                    AND c.date_of_birth IS NOT NULL
                ";
                
                if (!$is_admin) {
                    $ageSql .= " AND l.officer_id = ?";
                }
                
                $ageSql .= " GROUP BY age_group
                    ORDER BY 
                        CASE age_group
                            WHEN '18-25' THEN 1
                            WHEN '26-35' THEN 2
                            WHEN '36-45' THEN 3
                            WHEN '46-55' THEN 4
                            WHEN '56+' THEN 5
                            ELSE 6
                        END";
                
                $age_stmt = $pdo->prepare($ageSql);
                if ($is_admin) {
                    $age_stmt->execute([$start_date, $end_date]);
                } else {
                    $age_stmt->execute([$start_date, $end_date, $user_id]);
                }
                $age_demographics = $age_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
            ?>
            
            const ageData = <?php echo json_encode($age_demographics ?? []); ?>;
            const ageLabels = (ageData && Array.isArray(ageData) && ageData.length > 0) 
                ? ageData.map(item => item.age_group || 'Unknown')
                : ['18-25', '26-35', '36-45', '46-55', '56+'];
            const ageCounts = (ageData && Array.isArray(ageData) && ageData.length > 0)
                ? ageData.map(item => parseInt(item.count) || 0)
                : [0, 0, 0, 0, 0];
            
            new Chart(demographicsCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: demoLabels,
                    datasets: [{
                        label: 'Gender Distribution',
                        data: demoCounts,
                        backgroundColor: [
                            'rgba(58, 115, 184, 0.8)',
                            'rgba(176, 0, 32, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // OLD CHARTS - REMOVED
        /*
        const loanCtx = document.getElementById('loanChart').getContext('2d');
        const loanChart = new Chart(loanCtx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Total Applications',
                    data: loanCounts,
                    borderColor: '#1e3a8a',
                    backgroundColor: 'rgba(30, 58, 138, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#1e3a8a',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Approved Loans',
                    data: approvedAmounts.map((amt, idx) => loanCounts[idx] - rejectedCounts[idx]),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Rejected',
                    data: rejectedCounts,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: (() => {
                            const allValues = [...loanCounts, ...approvedAmounts.map((amt, idx) => loanCounts[idx] - rejectedCounts[idx]), ...rejectedCounts].filter(v => v > 0);
                            if (allValues.length === 0) return 100;
                            const maxValue = Math.max(...allValues);
                            return Math.ceil(maxValue * 1.15);
                        })(),
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280',
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Collection Chart - Enhanced
        const collectionCtx = document.getElementById('collectionChart').getContext('2d');
        const collectionChart = new Chart(collectionCtx, {
            type: 'bar',
            data: {
                labels: collectionLabels,
                datasets: [{
                    label: 'Total Collections',
                    data: totalCollections,
                    backgroundColor: 'rgba(245, 158, 11, 0.9)',
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    borderRadius: 6
                }, {
                    label: 'Principal',
                    data: principalCollected,
                    backgroundColor: 'rgba(16, 185, 129, 0.9)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 6
                }, {
                    label: 'Interest',
                    data: interestCollected,
                    backgroundColor: 'rgba(59, 130, 246, 0.9)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': UGX ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: false,
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: (() => {
                            const allValues = [...totalCollections, ...principalCollected, ...interestCollected].filter(v => v > 0);
                            if (allValues.length === 0) return 100000;
                            const maxValue = Math.max(...allValues);
                            return Math.ceil(maxValue * 1.15);
                        })(),
                        stacked: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280',
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'UGX ' + (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return 'UGX ' + (value / 1000).toFixed(0) + 'K';
                                }
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Approval Rate Chart
        const approvalCtx = document.getElementById('approvalRateChart').getContext('2d');
        const approvalChart = new Chart(approvalCtx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Approval Rate (%)',
                    data: approvalRates,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Approval Rate: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280',
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        // OLD CHARTS COMMENTED OUT - Using new charts above
        /*
        const statusChartOld = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ],
                    borderColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6',
                        '#6b7280'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: { size: 12 },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const amount = statusAmounts[i];
                                        return {
                                            text: label + ': ' + value + ' (UGX ' + amount.toLocaleString() + ')',
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor[i],
                                            lineWidth: 2,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const amount = statusAmounts[context.dataIndex] || 0;
                                return [
                                    label + ': ' + value + ' loans',
                                    'Total Amount: UGX ' + amount.toLocaleString()
                                ];
                            }
                        }
                    }
                }
            }
        });
        
        // Outstanding vs Active Loans Chart
        const outstandingLabels = <?php echo json_encode(array_map(function($m) { 
            $parts = explode('-', $m); 
            return date('M Y', mktime(0,0,0,$parts[1],1,$parts[0])); 
        }, array_column($outstanding_data, 'month'))); ?>;
        const totalLoansData = <?php echo json_encode(array_column($outstanding_data, 'total_loans')); ?>;
        const activeLoansData = <?php echo json_encode(array_column($outstanding_data, 'active_loans')); ?>;
        
        const outstandingCtx = document.getElementById('outstandingChart').getContext('2d');
        const outstandingChart = new Chart(outstandingCtx, {
            type: 'bar',
            data: {
                labels: outstandingLabels,
                datasets: [{
                    label: 'Total Loans',
                    data: totalLoansData,
                    backgroundColor: 'rgba(30, 58, 138, 0.8)',
                    borderColor: '#1e3a8a',
                    borderWidth: 2,
                    borderRadius: 6
                }, {
                    label: 'Active Loans',
                    data: activeLoansData,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': UGX ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: false,
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: (() => {
                            const allValues = [...totalLoansData, ...activeLoansData].filter(v => v > 0);
                            if (allValues.length === 0) return 100000;
                            const maxValue = Math.max(...allValues);
                            return Math.ceil(maxValue * 1.15);
                        })(),
                        stacked: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#6b7280',
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'UGX ' + (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return 'UGX ' + (value / 1000).toFixed(0) + 'K';
                                }
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        */
    </script>
    
    <script>
        function updateMonthOptions() {
            const yearSelect = document.getElementById('yearSelect');
            const monthSelect = document.getElementById('monthSelect');
            const selectedYear = parseInt(yearSelect.value);
            const currentYear = <?php echo $current_year; ?>;
            const currentMonth = <?php echo $current_month; ?>;
            const minYear = <?php echo $min_year; ?>;
            const minMonth = <?php echo $min_month; ?>;
            
            // Get all month options
            const monthOptions = monthSelect.querySelectorAll('option');
            
            monthOptions.forEach((option, index) => {
                if (index === 0) return; // Skip "Whole Year" option
                
                const monthValue = parseInt(option.value);
                
                // Disable future months
                if (selectedYear === currentYear && monthValue > currentMonth) {
                    option.disabled = true;
                    if (option.selected) {
                        monthSelect.value = '0'; // Reset to "Whole Year" if selected month becomes invalid
                    }
                } else if (selectedYear === minYear && monthValue < minMonth) {
                    option.disabled = true;
                    if (option.selected) {
                        monthSelect.value = minMonth.toString();
                    }
                } else {
                    option.disabled = false;
                }
            });
        }
        
        // Initialize month options on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateMonthOptions();
        });
        
        function openExportModal(type) {
            document.getElementById('exportModal').style.display = 'flex';
            document.getElementById('exportType').value = type;
        }
        
        function closeExportModal() {
            document.getElementById('exportModal').style.display = 'none';
        }
        
        function handleExport(type) {
            if (!type) {
                type = document.getElementById('exportType').value;
            }
            
            const form = document.getElementById('exportForm');
            const fields = Array.from(form.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
            
            if (fields.length === 0) {
                alert('Please select at least one field to export');
                return;
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('export', type);
            fields.forEach(field => {
                url.searchParams.append('fields[]', field);
            });
            
            // Get year and month from the form selects
            const yearSelect = document.getElementById('yearSelect');
            const monthSelect = document.getElementById('monthSelect');
            if (yearSelect) {
                url.searchParams.set('year', yearSelect.value);
            }
            if (monthSelect) {
                url.searchParams.set('month', monthSelect.value);
            }
            
            window.location.href = url.toString();
        }
        
        
        document.getElementById('exportModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
    </script>
    
    <div id="exportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: var(--spacing-2xl); border-radius: var(--radius-lg); max-width: 600px; width: 90%; box-shadow: var(--shadow-xl); max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <h2 style="color: var(--primary-blue); margin: 0;">
                    <i class="fas fa-download"></i> Export Data
                </h2>
                <button onclick="closeExportModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--medium-gray); cursor: pointer;">&times;</button>
            </div>
            
            <form id="exportForm" method="GET" action="">
                <input type="hidden" id="exportType" name="export" value="">
                <div style="margin-bottom: var(--spacing-lg);">
                    <label style="display: block; font-weight: 600; margin-bottom: var(--spacing-md); color: var(--dark-gray);">Select Fields to Export:</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-sm);">
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm); hover:background: var(--light-gray);">
                            <input type="checkbox" name="fields[]" value="loan_id" checked>
                            <span>Loan ID</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="customer_name" checked>
                            <span>Client Name</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="phone" checked>
                            <span>Phone</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="email" checked>
                            <span>Email</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="loan_amount" checked>
                            <span>Loan Amount</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="duration" checked>
                            <span>Duration</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="interest_rate" checked>
                            <span>Interest Rate</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="loan_date" checked>
                            <span>Loan Date</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="payback_date" checked>
                            <span>Payback Date</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="total_paid" checked>
                            <span>Total Paid</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer; padding: var(--spacing-sm); border-radius: var(--radius-sm);">
                            <input type="checkbox" name="fields[]" value="status" checked>
                            <span>Status</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-xl);">
                    <button type="button" onclick="closeExportModal()" class="btn btn-outline">Cancel</button>
                    <button type="button" onclick="handleExport(document.getElementById('exportType').value)" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

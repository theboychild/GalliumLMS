<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}

// If logged in as different user type, show error and logout
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin') {
    session_destroy();
    $errors[] = "Unauthorized access. Admin login only.";
}

// Check for error parameter
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $errors[] = "Unauthorized access. You must be an administrator to access this area.";
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 10) {
        $errors[] = "Admin password must be at least 10 characters long";
    } elseif (!preg_match('/^A/i', $password)) {
        $errors[] = "Admin password must begin with the letter 'A'";
    }

    if (empty($errors)) {
        try {
            // Check users table - ONLY admin users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Additional validation: Check if password meets admin requirements
                if (strlen($password) < 10) {
                    $errors[] = "Admin password must be at least 10 characters long";
                } elseif (!preg_match('/^A/i', $password)) {
                    $errors[] = "Admin password must begin with the letter 'A'";
                } elseif (isset($user['is_active']) && $user['is_active'] == 0) {
                    $errors[] = "Your account has been deactivated. Please contact administrator.";
                } else {
                    // Verify user is actually admin (double check)
                    if ($user['user_type'] !== 'admin') {
                        $errors[] = "Unauthorized access. This login is for administrators only.";
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = 'admin';

                        // Redirect to admin dashboard
                        header("Location: dashboard.php");
                        exit();
                    }
                }
            } else {
                $errors[] = "Invalid email or password. Admin access only.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }

        .login-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .login-header .admin-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 20px;
            margin-top: 15px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e3a8a;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer a {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: #1e40af;
        }

        .back-to-home {
            margin-top: 15px;
        }

        .back-to-home a {
            color: #6b7280;
            font-size: 13px;
        }

        .security-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin-bottom: 24px;
            border-radius: 4px;
            font-size: 13px;
            color: #92400e;
        }

        .security-note i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shield-alt"></i> Administrator Login</h1>
            <p>Gallium Solutions Limited</p>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i> Admin Access Only
            </div>
        </div>

        <div class="login-body">
            <div class="security-note">
                <i class="fas fa-lock"></i>
                <strong>Secure Access:</strong> This login is restricted to administrators only. Unauthorized access attempts are logged.
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p style="margin: 0;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email" class="form-control" required autocomplete="email" placeholder="admin@example.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password" placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login as Administrator
                </button>
            </form>
        </div>

        <div class="login-footer">
            <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            <div class="back-to-home">
                <a href="../officer/login.php">Officer Login</a> | 
                <a href="../login.php">Customer Login</a>
            </div>
        </div>
    </div>
</body>
</html>


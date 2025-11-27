<?php
session_start();
require_once 'config.php'; // Ensure this file sets up $pdo correctly

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
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
    }

    if (empty($errors)) {
        try {
            // Check users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Check if user is active
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    $errors[] = "Your account has been deactivated. Please contact administrator.";
                } else {
                    // Redirect admin and officer to their specific login pages
                    if ($user['user_type'] === 'admin') {
                        $errors[] = "Administrators must use the admin login page. <a href='admin/login.php'>Click here to login as admin</a>";
                    } elseif ($user['user_type'] === 'officer') {
                        $errors[] = "Officers must use the officer login page. <a href='officer/login.php'>Click here to login as officer</a>";
                    } else {
                        // Only customers can login here
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = 'customer';
                        header("Location: dashboard.php");
                        exit();
                    }
                }
            } else {
                $errors[] = "Invalid email or password";
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        h1 {
            text-align: center;
            color: #1e3a8a;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .forgot-password {
            text-align: right;
            margin-top: -15px;
            margin-bottom: 15px;
        }

        .register-link, .back-to-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn-register-link {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border: 2px solid #1e3a8a;
            border-radius: 5px;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-register-link:hover {
            background: #1e3a8a;
            color: white;
        }
        
        .back-to-home a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-to-home a:hover {
            color: #1e3a8a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-university"></i> Customer Login</h1>
        <p style="text-align: center; color: #666; margin-top: 10px; margin-bottom: 20px;">
            <strong>Note:</strong> Administrators and Officers have separate login pages
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php" class="btn-register-link"><i class="fas fa-user-plus"></i> Sign Up</a></p>
        </div>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <p style="margin: 10px 0; color: #666; font-size: 14px;">
                <strong>Staff Login:</strong><br>
                <a href="admin/login.php" style="color: #1e3a8a; text-decoration: none; margin: 0 10px;">
                    <i class="fas fa-shield-alt"></i> Admin Login
                </a> | 
                <a href="officer/login.php" style="color: #059669; text-decoration: none; margin: 0 10px;">
                    <i class="fas fa-user-tie"></i> Officer Login
                </a>
            </p>
        </div>
        
        <div class="back-to-home">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
        
        <?php if (isset($_SESSION['reset_success'])): ?>
            <div class="alert alert-success" style="margin-top: 1rem;">
                <p><?php echo htmlspecialchars($_SESSION['reset_success']); unset($_SESSION['reset_success']); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
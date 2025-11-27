<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type'] ?? 'customer'); 
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!validatePhone($phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Validate user type
    if (!in_array($user_type, ['admin', 'officer', 'customer'])) {
        $user_type = 'customer';
    }
    
    if (empty($errors)) {
        // Use the registerUser function from functions.php
        $result = registerUser($username, $email, $phone, $password, $user_type);
        
        if ($result['success']) {
            $success = true;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            text-align: center;
            color: #0056b3;
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
            padding: 10px 20px;
            background: #0056b3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        .btn:hover {
            background: #004494;
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
		.back-to-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-home a {
            color: #0056b3;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-to-home a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-plus"></i> Create Your Account</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
                <p style="margin-top: 10px; font-size: 0.9em;">
                    <strong>Need help?</strong> <a href="check_database.php" style="color: #721c24; text-decoration: underline;">Check database connection</a>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Registration successful! You can now <a href="login.php">login</a>.</p>
            </div>
        <?php else: ?>
            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password (min 6 characters)</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="user_type">Account Type</label>
                    <select name="user_type" id="user_type" class="form-control" required>
                        <option value="customer">Customer</option>
                        <option value="officer">Loan Officer</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <small style="color: #666;">Select your role. You can change this later if needed.</small>
                </div>
                
                <button type="submit" class="btn">Register</button>
            </form>
            <div class="back-to-home">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home Page</a>
        </div>
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
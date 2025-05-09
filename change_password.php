<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Check if password change is required
if (!isset($_SESSION['require_password_change']) || !isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit();
}

// Include config file
require_once 'config.php';

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in database
            $update_query = "UPDATE employee SET password = ?, first_login = 0 WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            
            if (!$update_stmt) {
                throw new Exception("Database error. Please try again later.");
            }
            
            $update_stmt->bind_param("ss", $hashed_password, $email);
            $update_stmt->execute();
            
            // Clear session variables
            unset($_SESSION['require_password_change']);
            unset($_SESSION['reset_email']);
            
            // Set success message
            $success = "Password changed successfully! Redirecting to dashboard...";
            
            // Redirect after 3 seconds
            header("refresh:3;url=dashboard.php");
        } catch (Exception $e) {
            $error = "Failed to update password. Please try again.";
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - ICT IT Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .change-container {
            background-color: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
        }
        
        .change-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .change-header i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .change-header h2 {
            color: var(--dark-color);
            margin: 0;
            font-weight: 600;
        }
        
        .change-header p {
            color: #7f8c8d;
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 38px;
            color: #95a5a6;
        }
        
        .btn-change {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-change:hover {
            background-color: var(--secondary-color);
        }
        
        .error-message {
            color: #e74c3c;
            background-color: #fde8e8;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
        }
        
        .success-message {
            color: #27ae60;
            background-color: #e8f8f0;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #27ae60;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        .strength-weak {
            color: #e74c3c;
        }
        
        .strength-medium {
            color: #f39c12;
        }
        
        .strength-strong {
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="change-container">
        <div class="change-header">
            <i class="fas fa-key"></i>
            <h2>Change Password</h2>
            <p>You must change your default password</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required 
                       placeholder="Enter new password (min 8 characters)">
                <i class="fas fa-lock input-icon"></i>
                <div class="password-strength" id="password-strength">
                    Password strength: <span id="strength-text">None</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                       placeholder="Confirm new password">
                <i class="fas fa-lock input-icon"></i>
            </div>
            
            <button type="submit" class="btn-change">
                <i class="fas fa-save me-2"></i> Change Password
            </button>
        </form>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('strength-text');
            const strength = checkPasswordStrength(password);
            
            strengthText.textContent = strength.text;
            strengthText.className = strength.class;
        });
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length >= 8
            if (password.length >= 8) strength++;
            
            // Contains lowercase
            if (password.match(/[a-z]/)) strength++;
            
            // Contains uppercase
            if (password.match(/[A-Z]/)) strength++;
            
            // Contains number
            if (password.match(/[0-9]/)) strength++;
            
            // Contains special char
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (strength <= 2) {
                return { text: "Weak", class: "strength-weak" };
            } else if (strength <= 4) {
                return { text: "Medium", class: "strength-medium" };
            } else {
                return { text: "Strong", class: "strength-strong" };
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
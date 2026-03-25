<?php
session_start();
include "app_init.php";

// Redirect if already logged in
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    header("Location: super_admin_dashboard.php");
    exit();
}

$error = "";
$email_value = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_value = $email;

    // Validation
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $user = rc_mig_get_user_auth_by_email($conn, $email);

        if ($user && (string) ($user['role'] ?? '') === 'super_admin') {
            $storedPassword = (string) ($user['password'] ?? '');
            $passwordOk = false;

            // Support both modern hashed passwords and any legacy plain-text values.
            if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
                $passwordOk = true;
            } elseif ($storedPassword !== '' && hash_equals($storedPassword, $password)) {
                $passwordOk = true;
            }

            if ($passwordOk) {
                $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
                $_SESSION['role'] = (string) ($user['role'] ?? 'super_admin');
                $_SESSION['user_name'] = (string) ($user['name'] ?? 'Super Admin');

                header("Location: super_admin_dashboard.php");
                exit();
            }

            $error = "Invalid email or password.";
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Super Admin Login - RentConnect</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #2E7D32;
    --primary-light: #4CAF50;
    --primary-dark: #1b5e20;
    --secondary: #FF9800;
    --danger: #d32f2f;
    --light-bg: #f5f7fa;
    --border-color: #e0e0e0;
    --text-dark: #2c3e50;
    --text-light: #666;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.15);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.login-wrapper {
    width: 100%;
    max-width: 450px;
}

.login-container {
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.login-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 3rem 2rem;
    text-align: center;
}

.login-header .logo {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.login-header h1 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.login-header p {
    font-size: 0.95rem;
    opacity: 0.9;
}

.login-body {
    padding: 2.5rem 2rem;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-error {
    background: #ffebee;
    color: var(--danger);
    border: 1px solid #ffcdd2;
}

.alert-error i {
    flex-shrink: 0;
    margin-top: 2px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.95rem;
}

.form-group .input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-group .input-wrapper i {
    position: absolute;
    left: 1rem;
    color: var(--primary);
    font-size: 1.1rem;
}

input[type="email"],
input[type="password"],
input[type="text"] {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 2.75rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s;
    background: #fafbfc;
}

input[type="email"]:focus,
input[type="password"]:focus,
input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.password-toggle {
    position: absolute;
    right: 1rem;
    cursor: pointer;
    color: var(--primary);
    font-size: 1rem;
    user-select: none;
    transition: opacity 0.2s;
}

.password-toggle:hover {
    opacity: 0.7;
}

.submit-btn {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.submit-btn:active {
    transform: translateY(0);
}

.footer-info {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
    text-align: center;
    color: var(--text-light);
    font-size: 0.85rem;
}

.footer-info i {
    color: var(--primary);
    margin-right: 0.5rem;
}

.security-notice {
    background: #f0f4ff;
    border-left: 4px solid var(--primary);
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    color: var(--text-dark);
}

.security-notice i {
    color: var(--primary);
    margin-right: 0.5rem;
}

@media (max-width: 600px) {
    .login-wrapper {
        max-width: 100%;
    }

    .login-header {
        padding: 2rem 1.5rem;
    }

    .login-header h1 {
        font-size: 1.5rem;
    }

    .login-header .logo {
        font-size: 2rem;
    }

    .login-body {
        padding: 2rem 1.5rem;
    }

    .form-group .input-wrapper i {
        left: 0.75rem;
    }

    input[type="email"],
    input[type="password"],
    input[type="text"] {
        padding: 0.875rem 0.75rem 0.875rem 2.5rem;
    }

    .password-toggle {
        right: 0.75rem;
    }
}
</style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-container">
    <!-- Header -->
    <div class="login-header">
      <div class="logo">
        <i class="fas fa-crown"></i>
      </div>
      <h1>Admin Access</h1>
      <p>Super Admin Portal</p>
    </div>

    <!-- Body -->
    <div class="login-body">
      <!-- Error Alert -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <!-- Security Notice -->
      <div class="security-notice">
        <i class="fas fa-lock"></i>
        <strong>Restricted Access:</strong> This area is for authorized administrators only. Unauthorized access attempts are logged.
      </div>

      <!-- Login Form -->
      <form method="POST" action="">
        <!-- Email Field -->
        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i> Email Address
          </label>
          <div class="input-wrapper">
            <i class="fas fa-envelope"></i>
            <input 
              type="email" 
              id="email" 
              name="email" 
              placeholder="admin@rentconnect.com" 
              value="<?= htmlspecialchars($email_value) ?>"
              required 
              autocomplete="email">
          </div>
        </div>

        <!-- Password Field -->
        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i> Password
          </label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input 
              type="password" 
              id="password" 
              name="password" 
              placeholder="Enter your password" 
              required 
              autocomplete="current-password">
            <span class="password-toggle" onclick="togglePassword()" title="Toggle password visibility">
              <i class="fas fa-eye" id="toggle-icon"></i>
            </span>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="submit-btn">
          <i class="fas fa-sign-in-alt"></i> Login to Dashboard
        </button>
      </form>

      <!-- Footer -->
      <div class="footer-info">
        <i class="fas fa-shield-alt"></i>
        &copy; <?= date("Y") ?> RentConnect Liberia. All rights reserved.
      </div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const passField = document.getElementById('password');
  const toggleIcon = document.getElementById('toggle-icon');
  
  if (passField.type === 'password') {
    passField.type = 'text';
    toggleIcon.classList.remove('fa-eye');
    toggleIcon.classList.add('fa-eye-slash');
  } else {
    passField.type = 'password';
    toggleIcon.classList.remove('fa-eye-slash');
    toggleIcon.classList.add('fa-eye');
  }
}

// Focus handling
document.getElementById('email').addEventListener('focus', function() {
  this.parentElement.style.borderColor = 'var(--primary)';
});

document.getElementById('password').addEventListener('focus', function() {
  this.parentElement.style.borderColor = 'var(--primary)';
});
</script>

</body>
</html>

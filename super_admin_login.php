<?php
session_start();
include "db.php";

// Redirect if already logged in
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    header("Location: super_admin_dashboard.php");
    exit();
}

$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email=? AND role='super_admin' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Plain text password check
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            header("Location: super_admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Super admin not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Login - RentConnect</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; font-family:Arial, sans-serif; }
body {
  background: linear-gradient(135deg,#2E7D32,#1b5e20);
  display:flex; justify-content:center; align-items:center;
  min-height:100vh; padding:15px;
}
.login-container {
  background:#fff; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.15);
  width:100%; max-width:400px; padding:40px; text-align:center;
}
.login-container h2 { color:#2E7D32; margin-bottom:25px; font-weight:700; }
.form-group { margin-bottom:20px; text-align:left; position:relative; }
input[type=email], input[type=password] {
  width:100%; padding:14px; border:1px solid #ccc; border-radius:8px;
  font-size:1em; transition:0.3s;
}
input[type=email]:focus, input[type=password]:focus {
  border-color:#2E7D32; outline:none; box-shadow:0 0 5px rgba(46,125,50,0.3);
}
.show-password {
  position:absolute; right:12px; top:50%; transform:translateY(-50%);
  cursor:pointer; font-size:0.9em; color:#2E7D32;
}
button {
  width:100%; padding:14px; background:#2E7D32; color:#fff;
  border:none; border-radius:8px; font-size:1em; cursor:pointer;
  transition:0.3s; font-weight:500;
}
button:hover { background:#1b5e20; }
.error { color:#d32f2f; margin-bottom:15px; font-weight:500; }
.footer-text { margin-top:20px; font-size:0.85em; color:#555; }
</style>
</head>
<body>
<div class="login-container">
  <h2>Super Admin Login</h2>
  <?php if(!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <input type="email" name="email" placeholder="Enter your email" required>
    </div>
    <div class="form-group">
      <input type="password" name="password" placeholder="Enter your password" id="password" required>
      <span class="show-password" onclick="togglePassword()">Show</span>
    </div>
    <button type="submit">Login</button>
  </form>
  <div class="footer-text">&copy; <?= date("Y") ?> RentConnect Liberia</div>
</div>

<script>
function togglePassword() {
  const passField = document.getElementById('password');
  const toggle = document.querySelector('.show-password');
  if(passField.type === 'password') {
    passField.type = 'text';
    toggle.textContent = 'Hide';
  } else {
    passField.type = 'password';
    toggle.textContent = 'Show';
  }
}
</script>
</body>
</html>

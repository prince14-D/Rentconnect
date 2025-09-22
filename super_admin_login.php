<?php
session_start();
include "db.php";

// If already logged in and super admin, redirect
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    header("Location: super_admin_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email=? AND role='super_admin' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // If you stored with password_hash()
        if (password_verify($password, $user['password'])) {
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
  <title>Super Admin Login - RentConnect</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; display:flex; justify-content:center; align-items:center; height:100vh; }
    .login-box {
      background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
      width:100%; max-width:400px;
    }
    h2 { margin-top:0; text-align:center; color:#2E7D32; }
    .form-group { margin-bottom:15px; }
    input[type=email], input[type=password] {
      width:100%; padding:12px; border:1px solid #ccc; border-radius:6px; font-size:1em;
    }
    button {
      width:100%; padding:12px; background:#2E7D32; color:#fff; border:none; border-radius:6px;
      font-size:1em; cursor:pointer; transition:0.3s;
    }
    button:hover { background:#1b5e20; }
    .error { color:#d32f2f; margin-bottom:10px; text-align:center; }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Super Admin Login</h2>
    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <input type="email" name="email" placeholder="Super Admin Email" required>
      </div>
      <div class="form-group">
        <input type="password" name="password" placeholder="Password" required>
      </div>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>

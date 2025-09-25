<?php
session_start();
include "db.php";

$message = "";

$token = $_GET['token'] ?? '';

if (!$token) die("Invalid or expired reset link.");

$stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->bind_result($user_id);

if (!$stmt->fetch()) die("Invalid or expired reset link.");
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token=?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "Password updated successfully. Please login.";
        header("Location: login.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - RentConnect</title>
  <style>
      body {
          font-family: Arial, sans-serif;
          background: #f4f6f9;
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100vh;
          margin: 0;
          padding: 15px;
      }
      .reset-box {
          background: white;
          padding: 30px;
          width: 100%;
          max-width: 400px;
          border-radius: 10px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }
      .reset-box h2 {
          text-align: center;
          color: #4CAF50;
          margin-bottom: 10px;
      }
      .reset-box p {
          text-align: center;
          font-size: 0.95em;
          margin-bottom: 20px;
          color: #555;
      }
      .reset-box form {
          display: flex;
          flex-direction: column;
      }
      .reset-box label {
          margin: 10px 0 5px;
          font-weight: bold;
          font-size: 0.95em;
      }
      .reset-box input {
          padding: 12px;
          border-radius: 6px;
          border: 1px solid #ccc;
          font-size: 1em;
      }
      .reset-box button {
          margin-top: 20px;
          padding: 12px;
          background: #4CAF50;
          color: white;
          border: none;
          border-radius: 6px;
          font-size: 1.1em;
          cursor: pointer;
          transition: background 0.3s ease;
      }
      .reset-box button:hover {
          background: #43a047;
      }
      .message {
          margin: 15px 0;
          text-align: center;
          color: red;
      }
      .success {
          margin: 15px 0;
          text-align: center;
          color: green;
          font-weight: bold;
      }
      .back-login {
          margin-top: 20px;
          text-align: center;
      }
      .back-login a {
          display: inline-block;
          padding: 10px 20px;
          background: #2196F3;
          color: white;
          border-radius: 6px;
          text-decoration: none;
          transition: background 0.3s ease;
      }
      .back-login a:hover {
          background: #1976D2;
      }

      @media (max-width: 600px) {
          .reset-box {
              padding: 20px;
              width: 100%;
              max-width: 95%;
          }
          .reset-box h2 {
              font-size: 1.5em;
          }
          .reset-box input, .reset-box button {
              font-size: 1em;
              padding: 10px;
          }
          .back-login a {
              font-size: 0.95em;
              padding: 8px 16px;
          }
      }
  </style>
</head>
<body>
  <div class="reset-box">
    <h2>Reset Password</h2>
    <p>Create a new password for your account.</p>

    <?php if ($message): ?>
      <p class="message"><?= $message; ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Update Password</button>
    </form>

    <div class="back-login">
        <a href="login.php">⬅ Back to Login</a>
    </div>
  </div>
</body>
</html>

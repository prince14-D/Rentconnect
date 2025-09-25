<?php
session_start();
include "db.php";

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_id);

    if ($stmt->fetch()) {
        $token = bin2hex(random_bytes(16));

        $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token) VALUES (?, ?)");
        $stmt2->bind_param("is", $user_id, $token);
        $stmt2->execute();
        $stmt2->close();

        $reset_link = "http://localhost/reset-password.php?token=" . $token;
        $subject = "RentConnect Password Reset";
        $message_body = "Click this link to reset your password: " . $reset_link;
        $headers = "From: no-reply@rentconnect.com\r\n";
        mail($email, $subject, $message_body, $headers);

        $_SESSION['success_message'] = "A password reset link has been sent to your email.";
        header("Location: forgot-password.php");
        exit();
    } else {
        $message = "No account found with this email.";
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RentConnect</title>
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
        .forgot-box {
            background: white;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .forgot-box h2 {
            text-align: center;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        .forgot-box p {
            text-align: center;
            font-size: 0.95em;
            margin-bottom: 20px;
            color: #555;
        }
        .forgot-box form {
            display: flex;
            flex-direction: column;
        }
        .forgot-box label {
            margin: 10px 0 5px;
            font-weight: bold;
            font-size: 0.95em;
        }
        .forgot-box input {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        .forgot-box button {
            margin-top: 20px;
            padding: 12px;
            background: #FF9800;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .forgot-box button:hover {
            background: #e68900;
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
            .forgot-box {
                padding: 20px;
                width: 100%;
                max-width: 95%;
            }
            .forgot-box h2 {
                font-size: 1.5em;
            }
            .forgot-box input, .forgot-box button {
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
    <div class="forgot-box">
        <h2>Forgot Password</h2>
        <p>Enter your email and we’ll send you a reset link.</p>

        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success"><?= $_SESSION['success_message']; ?></p>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="post" action="">
            <label>Email Address</label>
            <input type="email" name="email" required>

            <button type="submit">Send Reset Link</button>
        </form>

        <div class="back-login">
            <a href="login.php">⬅ Back to Login</a>
        </div>
    </div>
</body>
</html>

<?php
session_start();
include "db.php";

$message = "";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Fetch user by email
    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $hashedPassword, $role);

    if ($stmt->fetch()) {
        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

            // Redirect based on role
            if ($role === 'super_admin') {
                header("Location: super_admin_dashboard.php");
            } elseif ($role === 'landlord') {
                header("Location: landlord_dashboard.php");
            } elseif ($role === 'renter') {
                header("Location: renter_dashboard.php");
            }
            exit();
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "No account found with this email.";
    }

    $stmt->close();
}

// ✅ Get prefilled email from signup
$prefill_email = $_SESSION['prefill_email'] ?? '';
unset($_SESSION['prefill_email']); // clear after showing once
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RentConnect</title>
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
        .login-box {
            background: white;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .login-box h2 {
            text-align: center;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .login-box form {
            display: flex;
            flex-direction: column;
        }
        .login-box label {
            margin: 10px 0 5px;
            font-weight: bold;
            font-size: 0.95em;
        }
        .login-box input {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        .login-box button {
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
        .login-box button:hover {
            background: #43a047;
        }
        .forgot-password {
            margin-top: 15px;
            text-align: center;
        }
        .forgot-password a {
            display: inline-block;
            padding: 10px 18px;
            background: #FF9800;
            color: white;
            border-radius: 6px;
            font-size: 0.95em;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .forgot-password a:hover {
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
        .alt {
            margin-top: 15px;
            text-align: center;
            font-size: 0.95em;
        }
        .alt a {
            color: #2196F3;
            text-decoration: none;
            font-weight: bold;
        }
        .alt a:hover {
            text-decoration: underline;
        }
        .back-home {
            margin-top: 20px;
            text-align: center;
        }
        .back-home a {
            display: inline-block;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .back-home a:hover {
            background: #1976D2;
        }

        @media (max-width: 600px) {
            .login-box {
                padding: 20px;
                width: 100%;
                max-width: 95%;
            }
            .login-box h2 {
                font-size: 1.5em;
            }
            .login-box input, .login-box button {
                font-size: 1em;
                padding: 10px;
            }
            .forgot-password a {
                font-size: 0.9em;
                padding: 8px 14px;
            }
            .back-home a {
                font-size: 0.95em;
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>

        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success"><?= $_SESSION['success_message']; ?></p>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="post" action="">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($prefill_email) ?>">

            <label>Password</label>
            <input type="password" name="password" required id="password">

            <button type="submit">Login</button>
        </form>

        <!-- ✅ Upgraded Forget Password UI -->
        <div class="forgot-password">
            <a href="forgot-password.php">Forgot Password?</a>
        </div>

        <div class="alt">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>

        <div class="back-home">
            <a href="index.php">⬅ Back to Home</a>
        </div>
    </div>

    <script>
        // ✅ Autofocus password if email is prefilled
        window.onload = function() {
            const emailField = document.querySelector("input[name='email']");
            const passwordField = document.getElementById("password");
            if (emailField.value.trim() !== "") {
                passwordField.focus();
            } else {
                emailField.focus();
            }
        }
    </script>
</body>
</html>

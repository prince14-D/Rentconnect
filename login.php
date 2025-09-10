<?php
session_start();
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

     if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on role
            if ($row['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($row['role'] === 'landlord') {
                header("Location: landlord_dashboard.php");
            } else {
                header("Location: renter_dashboard.php");
            }
            exit;

        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "No account found with that email.";
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - RentConnect</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-box {
            background: white;
            padding: 30px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .login-box h2 {
            text-align: center;
            color: #4CAF50;
        }
        .login-box form {
            display: flex;
            flex-direction: column;
        }
        .login-box label {
            margin: 10px 0 5px;
            font-weight: bold;
        }
        .login-box input {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .login-box button {
            margin-top: 20px;
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
        }
        .login-box button:hover {
            background: #43a047;
        }
        .message {
            margin: 15px 0;
            text-align: center;
            color: red;
        }
        .alt {
            margin-top: 15px;
            text-align: center;
        }
        .alt a {
            color: #2196F3;
            text-decoration: none;
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
            border-radius: 5px;
            text-decoration: none;
        }
        .back-home a:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label>Email Address</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <div class="alt">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>

        <!-- Back to Home Button -->
        <div class="back-home">
            <a href="index.php">⬅ Back to Home</a>
        </div>
    </div>
</body>
</html>


<?php
session_start();
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows > 0) {
        $message = "⚠ Email already registered. Please login.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        if ($stmt->execute()) {
            // ✅ Instead of logging them in, show success + redirect to login
            $_SESSION['success_message'] = "🎉 Registration successful! Please login with your $role account.";
            header("Location: login.php");
            exit;
        } else {
            $message = "❌ Error signing up. Try again.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - RentConnect</title>
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
        .signup-box {
            background: white;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .signup-box h2 {
            text-align: center;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .signup-box form {
            display: flex;
            flex-direction: column;
        }
        .signup-box label {
            margin: 10px 0 5px;
            font-weight: bold;
            font-size: 0.95em;
        }
        .signup-box input, .signup-box select {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        .signup-box button {
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
        .signup-box button:hover {
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

        /* ✅ Responsive Styles */
        @media (max-width: 600px) {
            .signup-box {
                padding: 20px;
                width: 100%;
                max-width: 95%;
            }
            .signup-box h2 {
                font-size: 1.5em;
            }
            .signup-box input,
            .signup-box select,
            .signup-box button {
                font-size: 1em;
                padding: 10px;
            }
            .back-home a {
                font-size: 0.95em;
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="signup-box">
        <h2>Create Account</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label>Full Name</label>
            <input type="text" name="name" required>

            <label>Email Address</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Account Type</label>
            <select name="role" required>
                <option value="renter">Renter</option>
                <option value="landlord">Landlord</option>
            </select>

            <button type="submit">Sign Up</button>
        </form>

        <div class="alt">
            Already have an account? <a href="login.php">Login</a>
        </div>

        <!-- Back to Home Button -->
        <div class="back-home">
            <a href="index.php">⬅ Back to Home</a>
        </div>
    </div>
</body>
</html>

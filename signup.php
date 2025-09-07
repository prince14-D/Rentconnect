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
        $message = "Email already registered. Please login.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['role'] = $role;
            header("Location: " . ($role == 'landlord' ? "landlord_dashboard.php" : "renter_dashboard.php"));
            exit;
        } else {
            $message = "Error signing up. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - RentConnect</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .signup-box {
            background: white;
            padding: 30px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .signup-box h2 {
            text-align: center;
            color: #4CAF50;
        }
        .signup-box form {
            display: flex;
            flex-direction: column;
        }
        .signup-box label {
            margin: 10px 0 5px;
            font-weight: bold;
        }
        .signup-box input, .signup-box select {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .signup-box button {
            margin-top: 20px;
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
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

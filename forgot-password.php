<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    include 'db.php'; // database connection

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate reset token
        $token = bin2hex(random_bytes(50));

        // Store token in DB
        $stmt = $conn->prepare("UPDATE users SET reset_token=?, token_expiry=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email=?");
        $stmt->bind_param("ss", $token, $email);
        $stmt->execute();

        // Send email with reset link
        $reset_link = "http://localhost/rentconnect/reset-password.php?token=$token";
        mail($email, "Password Reset", "Click here to reset your password: $reset_link");

        echo "Check your email for the password reset link.";
    } else {
        echo "Email not found.";
    }
}
?>

<form method="POST" action="">
    <input type="email" name="email" placeholder="Enter your email" required>
    <button type="submit">Send Reset Link</button>
</form>

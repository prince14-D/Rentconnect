<?php
include 'db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token=? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, token_expiry=NULL WHERE reset_token=?");
            $stmt->bind_param("ss", $new_password, $token);
            $stmt->execute();

            echo "Password reset successfully. <a href='login.php'>Login</a>";
            exit();
        }
    } else {
        echo "Invalid or expired token.";
        exit();
    }
} else {
    echo "No token provided.";
    exit();
}
?>

<form method="POST">
    <input type="password" name="password" placeholder="Enter new password" required>
    <button type="submit">Reset Password</button>
</form>

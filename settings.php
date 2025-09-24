<?php
session_start();
include "db.php";

// Debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

/* -------------------------
   UPDATE THEME
------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme_color = $_POST['theme_color'] ?? '#2e7d32';

    $stmt = $conn->prepare("UPDATE users SET theme_color=? WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("si", $theme_color, $landlord_id);
        if ($stmt->execute()) {
            $message = "✅ Theme updated successfully!";
        } else {
            $message = "❌ Database error: " . $stmt->error;
        }
    } else {
        $message = "❌ Prepare failed: " . $conn->error;
    }
}

/* -------------------------
   FETCH CURRENT THEME
------------------------- */
$stmt = $conn->prepare("SELECT theme_color FROM users WHERE id=?");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$res = $stmt->get_result();
$current_theme = $res->fetch_assoc()['theme_color'] ?? '#2e7d32';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings - RentConnect</title>
<style>
    body { font-family:Arial, sans-serif; background:#f7f9f7; margin:0; }
    header { background:<?php echo $current_theme; ?>; color:white; padding:20px; text-align:center; font-size:1.5em; }
    .container { max-width:500px; margin:20px auto; background:white; padding:20px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
    .message { color:green; font-weight:bold; text-align:center; margin-bottom:10px; }
    label { display:block; margin-top:15px; font-weight:bold; }
    input[type=color] { width:100%; height:50px; border:none; border-radius:6px; cursor:pointer; }
    button { margin-top:20px; width:100%; padding:12px; background:<?php echo $current_theme; ?>; color:white; border:none; border-radius:6px; cursor:pointer; font-size:1em; }
    button:hover { opacity:0.9; }
</style>
</head>
<body>
<header>⚙️ Settings</header>
<div class="container">
    <?php if($message) echo "<p class='message'>$message</p>"; ?>

    <form method="post">
        <label for="theme_color">Dashboard Theme Color</label>
        <input type="color" name="theme_color" id="theme_color" value="<?php echo htmlspecialchars($current_theme); ?>" required>
        <button type="submit">Update Theme</button>
    </form>
</div>
</body>
</html>

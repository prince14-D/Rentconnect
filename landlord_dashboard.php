<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:900px; margin:30px auto; padding:0 15px; display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; }

.card-btn {
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    background:white;
    padding:30px 15px;
    border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);
    text-align:center;
    font-size:1.1em;
    color:#2e7d32;
    text-decoration:none;
    transition:0.3s;
}
.card-btn:hover { transform:translateY(-5px); box-shadow:0 6px 14px rgba(0,0,0,0.15); }
.card-btn i { font-size:2em; margin-bottom:10px; }

/* Message */
.message { text-align:center; color:green; font-weight:bold; margin-bottom:15px; }

/* Mobile */
@media(max-width:500px){
    .container { grid-template-columns:1fr; }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header>Welcome, <?php echo $_SESSION['name']; ?>!</header>
<?php if($message) echo "<p class='message'>$message</p>"; ?>

<div class="container">
    <a href="my_properties.php" class="card-btn">
        <i class="fas fa-home"></i>
        My Properties
    </a>
    <a href="rental_requests.php" class="card-btn">
        <i class="fas fa-envelope"></i>
        Rental Requests
    </a>
    <a href="add_property.php" class="card-btn">
        <i class="fas fa-plus-circle"></i>
        Upload Property
    </a>
    <a href="chat_list.php" class="card-btn">
        <i class="fas fa-comments"></i>
        Chat
    </a>
    <a href="settings.php" class="card-btn">
        <i class="fas fa-cog"></i>
        Settings
    </a>
    <a href="logout.php" class="card-btn" style="background:#f44336; color:white;">
        <i class="fas fa-sign-out-alt"></i>
        Logout
    </a>
</div>

</body>
</html>

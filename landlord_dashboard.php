<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Fetch property counts
$counts = ['pending'=>0, 'approved'=>0, 'taken'=>0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM properties WHERE landlord_id=? GROUP BY status");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $counts[$row['status']] = $row['count'];
}
$stmt->close();

// Fetch pending rental requests count
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM rental_requests WHERE property_id IN (SELECT id FROM properties WHERE landlord_id=?) AND status='pending'");
$stmt2->bind_param("i", $landlord_id);
$stmt2->execute();
$stmt2->bind_result($pending_requests);
$stmt2->fetch();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Landlord Dashboard - RentConnect</title>
<style>
body {
    margin:0;
    font-family:Arial,sans-serif;
    background:#f7f9f7;
}
header {
    background:#2E7D32;
    color:white;
    text-align:center;
    padding:25px 15px;
    font-size:1.6em;
    font-weight:bold;
}
.container {
    max-width:1000px;
    margin:30px auto;
    padding:0 15px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:20px;
}
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
    color:#2E7D32;
    text-decoration:none;
    transition:0.4s;
    position:relative;
}
.card-btn:hover {
    transform:translateY(-6px);
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
}
.card-btn i {
    font-size:2.2em;
    margin-bottom:12px;
}
.card-btn span.badge {
    position:absolute;
    top:10px;
    right:10px;
    background:#FF9800;
    color:#fff;
    padding:3px 7px;
    font-size:0.75em;
    border-radius:50%;
}

/* Message */
.message {
    text-align:center;
    color:green;
    font-weight:bold;
    margin-bottom:20px;
}

/* Mobile */
@media(max-width:500px){
    .container { grid-template-columns:1fr; }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header>Welcome, <?= htmlspecialchars($_SESSION['name']); ?>!</header>

<?php if($message): ?>
<p class="message"><?= htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="container">
    <a href="my_properties.php" class="card-btn">
        <i class="fas fa-home"></i>
        My Properties
        <span class="badge"><?= array_sum($counts) ?></span>
    </a>
    <a href="rental_requests.php" class="card-btn">
        <i class="fas fa-envelope"></i>
        Rental Requests
        <?php if($pending_requests>0): ?>
            <span class="badge"><?= $pending_requests ?></span>
        <?php endif; ?>
    </a>
    <a href="add_property.php" class="card-btn">
        <i class="fas fa-plus-circle"></i>
        Upload Property
    </a>
    <a href="approved_properties.php" class="card-btn">
        <i class="fas fa-check-circle"></i>
        Approved
        <?php if($counts['approved']>0): ?>
            <span class="badge"><?= $counts['approved'] ?></span>
        <?php endif; ?>
    </a>
    <a href="pending_properties.php" class="card-btn">
        <i class="fas fa-hourglass-half"></i>
        Pending
        <?php if($counts['pending']>0): ?>
            <span class="badge"><?= $counts['pending'] ?></span>
        <?php endif; ?>
    </a>
    <a href="taken_properties.php" class="card-btn">
        <i class="fas fa-times-circle"></i>
        Taken
        <?php if($counts['taken']>0): ?>
            <span class="badge"><?= $counts['taken'] ?></span>
        <?php endif; ?>
    </a>
    <a href="chat.php" class="card-btn">
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

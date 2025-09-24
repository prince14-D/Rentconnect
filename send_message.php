<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    http_response_code(403);
    exit;
}

$landlord_id = $_SESSION['user_id'];
$property_id = intval($_POST['property_id']);
$receiver_id = intval($_POST['receiver_id']);
$message = trim($_POST['chat_message']);

if ($message !== "") {
    $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $property_id, $landlord_id, $receiver_id, $message);
    $stmt->execute();
}


session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $subject = trim($_POST["subject"]);
    $message = trim($_POST["message"]);

    if ($name && $email && $subject && $message) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);

        if ($stmt->execute()) {
            // OPTIONAL: Send email to admin (configure your server mail first)
            $adminEmail = "support@rentconnect.com"; 
            $mailSubject = "New Contact Message: " . $subject;
            $mailBody = "Name: $name\nEmail: $email\n\nMessage:\n$message";
            @mail($adminEmail, $mailSubject, $mailBody, "From: $email");

            $_SESSION["msg_success"] = "✅ Your message has been sent successfully!";
        } else {
            $_SESSION["msg_error"] = "❌ Failed to send your message. Please try again.";
        }
    } else {
        $_SESSION["msg_error"] = "⚠ Please fill in all fields.";
    }
}

// Redirect back to contact page
header("Location: contact.php");
exit;
?>

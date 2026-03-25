<?php
session_start();
include "app_init.php";

function redirect_with_flash(string $type, string $message): void {
    $_SESSION[$type] = $message;
    header("Location: contact.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contact.php");
    exit;
}

/*
 * Mode 1: Chat message submission
 * Expected fields: property_id, receiver_id, chat_message
 */
if (isset($_POST["chat_message"], $_POST["property_id"], $_POST["receiver_id"])) {
    if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "landlord") {
        http_response_code(403);
        exit("Access denied.");
    }

    $sender_id = (int) $_SESSION["user_id"];
    $property_id = (int) $_POST["property_id"];
    $receiver_id = (int) $_POST["receiver_id"];
    $chat_message = trim($_POST["chat_message"]);

    if ($property_id <= 0 || $receiver_id <= 0 || $chat_message === "") {
        http_response_code(400);
        exit("Invalid message payload.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        http_response_code(500);
        exit("Failed to prepare chat message.");
    }

    $stmt->bind_param("iiis", $property_id, $sender_id, $receiver_id, $chat_message);
    if (!$stmt->execute()) {
        http_response_code(500);
        exit("Failed to send chat message.");
    }

    http_response_code(204);
    exit;
}

/*
 * Mode 2: Contact form submission
 * Expected fields: name, email, subject, message
 */
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");

if ($name === "" || $email === "" || $subject === "" || $message === "") {
    redirect_with_flash("msg_error", "Please fill in all fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_flash("msg_error", "Please provide a valid email address.");
}

$stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$stmt) {
    redirect_with_flash("msg_error", "Failed to prepare your message. Please try again.");
}

$stmt->bind_param("ssss", $name, $email, $subject, $message);
if (!$stmt->execute()) {
    redirect_with_flash("msg_error", "Failed to send your message. Please try again.");
}

$adminEmail = "support@rentconnect.com";
$mailSubject = "New Contact Message: " . $subject;
$mailBody = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";
$mailHeaders = "From: no-reply@rentconnect.com\r\nReply-To: {$email}";
@mail($adminEmail, $mailSubject, $mailBody, $mailHeaders);

redirect_with_flash("msg_success", "Your message has been sent successfully.");

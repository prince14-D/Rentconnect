<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $request_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action == 'approve') {
        $status = 'approved';
    } elseif ($action == 'decline') {
        $status = 'declined';
    } else {
        die("Invalid action.");
    }

    $sql = "UPDATE requests SET status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $request_id);

    if ($stmt->execute()) {
        header("Location: landlord_dashboard.php?msg=updated");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
} else {
    header("Location: landlord_dashboard.php");
    exit;
}

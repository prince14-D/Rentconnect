<?php
session_start();
include "app_init.php";

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("HTTP/1.1 403 Forbidden");
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
        'code' => 403
    ]);
    exit;
}

// Require POST method for state-changing operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'message' => 'POST method required.',
        'code' => 405
    ]);
    exit;
}

// Validate required parameters
$request_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($request_id <= 0 || empty($action)) {
    header("HTTP/1.1 400 Bad Request");
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid request ID or action.',
        'code' => 400
    ]);
    exit;
}

// Validate action
$valid_actions = ['approve', 'decline'];
if (!in_array($action, $valid_actions)) {
    header("HTTP/1.1 400 Bad Request");
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action. Allowed actions: approve, decline.',
        'code' => 400
    ]);
    exit;
}

// Set status based on action
$status = ($action === 'approve') ? 'approved' : 'declined';

$landlord_id = $_SESSION['user_id'];

$request_data = rc_mig_get_request_detail_for_landlord($conn, $request_id, $landlord_id);
if (!$request_data) {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'message' => 'Request not found or you do not have permission to update it.',
        'code' => 404
    ]);
    exit;
}

// Update the request status
try {
    if (!rc_mig_update_request_status_for_landlord($conn, $request_id, $landlord_id, $status)) {
        throw new Exception('Status update failed');
    }

    $request_data = rc_mig_get_request_detail_for_landlord($conn, $request_id, $landlord_id);
    if (!$request_data) {
        throw new Exception('Failed to reload request');
    }
    
    // Check if request came from AJAX
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($is_ajax) {
        // Return JSON response for AJAX requests
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo json_encode([
            'success' => true,
            'message' => ucfirst($action) . 'd rental request successfully.',
            'data' => [
                'request_id' => $request_data['id'],
                'status' => $request_data['status'],
                'action' => $action
            ],
            'code' => 200
        ]);
    } else {
        // Redirect for traditional form submissions
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => ucfirst($action) . 'd rental request from ' . htmlspecialchars($request_data['renter_name']) . '.'
        ];
        header("Location: rental_requests.php");
    }
    
    exit;
    
} catch (Exception $e) {
    error_log("Request update error: " . $e->getMessage());
    
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($is_ajax) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update request. Please try again.',
            'code' => 500
        ]);
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Failed to update request. Please try again.'
        ];
        header("Location: rental_requests.php");
    }
    exit;
}

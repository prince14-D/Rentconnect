<?php
/**
 * Payment Processing Handler
 * Integrates Stripe and Lonestar Momo payment gateways.
 */

session_start();
include 'app_init.php';
include 'stripe_payment.php';
include 'lonestar_momo.php';
include 'email_notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'stripe_initiate') {
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_month = trim((string) ($_POST['payment_month'] ?? ''));

    if ($booking_id <= 0 || $amount <= 0 || $payment_month === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    if (!rc_mig_verify_booking_access($conn, $booking_id, $renter_id)) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    $payment_id = rc_mig_create_payment_from_booking($conn, $booking_id, $renter_id, $amount, $payment_month, 'card', 'pending');
    if ($payment_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Unable to create payment record']);
        exit;
    }

    $description = 'Rent payment - Booking #' . $booking_id . ' for ' . date('F Y', strtotime($payment_month));
    $stripe_result = stripe_create_payment_intent($booking_id, $amount, 'usd', $description);

    if (!$stripe_result['success']) {
        rc_mig_delete_payment($conn, $payment_id);
        echo json_encode(['success' => false, 'error' => $stripe_result['error'] ?? 'Stripe initiation failed']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'client_secret' => $stripe_result['client_secret'],
        'stripe_public_key' => get_stripe_public_key(),
        'amount' => $amount,
        'currency' => 'usd',
    ]);
    exit;
}

if ($action === 'stripe_confirm') {
    $payment_id = (int) ($_POST['payment_id'] ?? 0);
    $intent_id = trim((string) ($_POST['intent_id'] ?? ''));

    if ($payment_id <= 0 || $intent_id === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $payment = rc_mig_get_payment_detail_for_renter($conn, $payment_id, $renter_id);
    if (!$payment) {
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    $stripe_status = stripe_confirm_payment($intent_id);
    if (!($stripe_status['success'] ?? false) || ($stripe_status['status'] ?? '') !== 'succeeded') {
        echo json_encode(['success' => false, 'error' => $stripe_status['message'] ?? 'Payment failed']);
        exit;
    }

    rc_mig_update_payment_status_reference($conn, $payment_id, 'pending', $intent_id);

    $landlord = rc_mig_get_user_by_id($conn, (int) $payment['landlord_id']);
    $property = rc_mig_get_property_title_by_id($conn, (int) $payment['property_id']);
    $renter = rc_mig_get_user_by_id($conn, $renter_id);

    if ($landlord && $property && $renter) {
        @send_payment_received_notification(
            $landlord['email'],
            $landlord['name'],
            $renter['name'],
            $property['title'],
            (float) $payment['amount'],
            (string) $payment['payment_month'],
            'card',
            $payment_id
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment received. Waiting for landlord approval.',
        'payment_id' => $payment_id,
    ]);
    exit;
}

if ($action === 'momo_initiate') {
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_month = trim((string) ($_POST['payment_month'] ?? ''));
    $phone_number = trim((string) ($_POST['phone_number'] ?? ''));

    if ($booking_id <= 0 || $amount <= 0 || $payment_month === '' || $phone_number === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    if (!validate_lonestar_phone($phone_number)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone number. Use format: 231xxxxxxxxx']);
        exit;
    }

    if (!rc_mig_verify_booking_access($conn, $booking_id, $renter_id)) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    $payment_id = rc_mig_create_payment_from_booking($conn, $booking_id, $renter_id, $amount, $payment_month, 'momo', 'pending');
    if ($payment_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Unable to create payment record']);
        exit;
    }

    $formatted_phone = format_lonestar_phone($phone_number);
    $external_ref = 'RC_' . $payment_id . '_' . time();
    $description = 'Rent for ' . date('F Y', strtotime($payment_month));

    $momo_result = lonestar_initiate_payment($amount, $formatted_phone, $external_ref, $description);
    if (!($momo_result['success'] ?? false)) {
        rc_mig_delete_payment($conn, $payment_id);
        echo json_encode(['success' => false, 'error' => $momo_result['error'] ?? 'Momo initiation failed']);
        exit;
    }

    rc_mig_update_payment_status_reference($conn, $payment_id, 'pending', (string) ($momo_result['transaction_id'] ?? $external_ref));

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'transaction_id' => $momo_result['transaction_id'] ?? null,
        'message' => $momo_result['message'] ?? ('Payment request sent to ' . $formatted_phone),
    ]);
    exit;
}

if ($action === 'momo_check_status') {
    $payment_id = (int) ($_POST['payment_id'] ?? 0);
    if ($payment_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing payment ID']);
        exit;
    }

    $payment = rc_mig_get_payment_detail_for_renter($conn, $payment_id, $renter_id);
    if (!$payment || empty($payment['reference_number'])) {
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    echo json_encode(lonestar_check_payment_status((string) $payment['reference_number']));
    exit;
}

if ($action === 'submit_manual') {
    $payment_id = (int) ($_POST['payment_id'] ?? 0);
    $payment_method = trim((string) ($_POST['payment_method'] ?? 'bank_transfer'));
    $reference = trim((string) ($_POST['reference_number'] ?? ''));

    if ($payment_id <= 0 || $reference === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required manual payment fields']);
        exit;
    }

    if (!in_array($payment_method, ['bank_transfer', 'check', 'money_order'], true)) {
        $payment_method = 'bank_transfer';
    }

    $payment = rc_mig_get_payment_detail_for_renter($conn, $payment_id, $renter_id);
    if (!$payment) {
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE payments SET payment_method = ?, reference_number = ?, status = ?, submitted_at = NOW(), updated_at = NOW() WHERE id = ? AND renter_id = ?');
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Unable to submit manual payment']);
        exit;
    }

    $status = 'submitted';
    $stmt->bind_param('sssii', $payment_method, $reference, $status, $payment_id, $renter_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => 'Manual payment submission failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Manual payment submitted for landlord review.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);

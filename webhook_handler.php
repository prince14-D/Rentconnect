<?php
/**
 * Payment Webhook Handler
 * Handles async confirmations from Stripe and Lonestar Momo
 */

include "db.php";
include "stripe_payment.php";
include "lonestar_momo.php";
include "email_notifications.php";

// Get raw input
$input = file_get_contents('php://input');
$event_type = $_GET['type'] ?? '';

/**
 * Stripe Webhook Handler
 */
if ($event_type === 'stripe') {
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $webhook_secret = getenv('STRIPE_WEBHOOK_SECRET');

    // Verify webhook signature
    $signature = hash_hmac('sha256', $input, $webhook_secret);
    if ($signature !== $sig_header) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $event = json_decode($input, true);

    if (!$event) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Handle charge.succeeded event
    if ($event['type'] === 'charge.succeeded') {
        $charge = $event['data']['object'];
        $intent_id = $charge['payment_intent'] ?? null;

        if ($intent_id) {
            // Update payment record
            $stmt = $conn->prepare("
                SELECT id FROM payments 
                WHERE stripe_intent_id = ? OR reference_number = ?
                LIMIT 1
            ");
            $stmt->bind_param("ss", $intent_id, $intent_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();

            if ($payment) {
                $status = 'confirmed';
                $update = $conn->prepare("
                    UPDATE payments 
                    SET status = ?, paid_at = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("si", $status, $payment['id']);
                $update->execute();

                // Log webhook event
                error_log("[STRIPE WEBHOOK] Payment {$payment['id']} confirmed via webhook");
            }
        }
    }

    // Handle charge.failed event
    if ($event['type'] === 'charge.failed') {
        $charge = $event['data']['object'];
        $intent_id = $charge['payment_intent'] ?? null;

        if ($intent_id) {
            $stmt = $conn->prepare("
                SELECT id FROM payments 
                WHERE stripe_intent_id = ? OR reference_number = ?
                LIMIT 1
            ");
            $stmt->bind_param("ss", $intent_id, $intent_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();

            if ($payment) {
                $status = 'failed';
                $update = $conn->prepare("
                    UPDATE payments 
                    SET status = ?
                    WHERE id = ?
                ");
                $update->bind_param("si", $status, $payment['id']);
                $update->execute();

                error_log("[STRIPE WEBHOOK] Payment {$payment['id']} failed: " . $charge['failure_message']);
            }
        }
    }

    // Respond with success
    echo json_encode(['success' => true]);
    exit;
}

/**
 * Lonestar Momo Webhook Handler
 */
if ($event_type === 'momo') {
    $webhook_secret = getenv('LONESTAR_WEBHOOK_SECRET');
    
    // Verify webhook (if they use HMAC)
    $sig_header = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if ($sig_header && hash_hmac('sha256', $input, $webhook_secret) !== $sig_header) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $event = json_decode($input, true);

    if (!$event) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Handle payment status updates
    if ($event['status'] === 'success' && $event['transaction_id']) {
        $transaction_id = $event['transaction_id'];

        // Find payment
        $stmt = $conn->prepare("
            SELECT id, renter_id, landlord_id, amount, property_id
            FROM payments 
            WHERE reference_number = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if ($payment) {
            // Update payment status
            $status = 'confirmed';
            $update = $conn->prepare("
                UPDATE payments 
                SET status = ?, paid_at = NOW()
                WHERE id = ?
            ");
            $update->bind_param("si", $status, $payment['id']);
            $update->execute();

            // Send notifications
            $renter = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
            $renter->bind_param("i", $payment['renter_id']);
            $renter->execute();
            $renter_data = $renter->get_result()->fetch_assoc();

            $landlord = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
            $landlord->bind_param("i", $payment['landlord_id']);
            $landlord->execute();
            $landlord_data = $landlord->get_result()->fetch_assoc();

            // Send confirmation emails
            @send_payment_confirmation_email(
                $renter_data['email'],
                $renter_data['name'],
                $payment['amount']
            );

            @send_payment_received_notification(
                $landlord_data['email'],
                $landlord_data['name'],
                $renter_data['name'],
                '',
                $payment['amount'],
                date('Y-m-01'),
                'momo',
                $payment['id']
            );

            error_log("[MOMO WEBHOOK] Payment {$payment['id']} confirmed via webhook");
        }
    }

    if ($event['status'] === 'failed' && $event['transaction_id']) {
        $transaction_id = $event['transaction_id'];

        $stmt = $conn->prepare("
            SELECT id, renter_id
            FROM payments 
            WHERE reference_number = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if ($payment) {
            $status = 'failed';
            $update = $conn->prepare("
                UPDATE payments 
                SET status = ?
                WHERE id = ?
            ");
            $update->bind_param("si", $status, $payment['id']);
            $update->execute();

            error_log("[MOMO WEBHOOK] Payment {$payment['id']} failed");
        }
    }

    // Respond with success
    echo json_encode(['success' => true]);
    exit;
}

// Invalid type
http_response_code(400);
echo json_encode(['error' => 'Invalid webhook type']);
?>

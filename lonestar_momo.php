<?php
/**
 * Lonestar Momo Payment Gateway Integration
 * Handles mobile money payments for RentConnect
 */

// Lonestar Momo configuration
define('LONESTAR_MOMO_API_KEY', 'your_api_key_here');
define('LONESTAR_MOMO_API_URL', 'https://api.lonestar.io/v1'); // or sandbox URL
define('LONESTAR_MOMO_MERCHANT_ID', 'your_merchant_id');

/**
 * Initialize Lonestar Momo payment request
 */
function lonestar_initiate_payment($amount, $phone_number, $external_ref, $description = '') {
    // Phone number should be in format: 231xxxxxxxxx (Liberia country code)
    $phone_number = trim($phone_number);
    if (!preg_match('/^231\d{8,9}$/', $phone_number)) {
        return [
            'success' => false,
            'error' => 'Invalid phone number. Use format: 231xxxxxxxxx'
        ];
    }

    // Mock mode for development
    if (!defined('LONESTAR_MOMO_LIVE') || !LONESTAR_MOMO_LIVE) {
        // Simulate payment initiation
        return [
            'success' => true,
            'transaction_id' => 'txn_mock_' . uniqid(),
            'reference_code' => $external_ref,
            'amount' => $amount,
            'phone' => $phone_number,
            'status' => 'initiated',
            'mock_mode' => true,
            'message' => 'Payment request sent (MOCK MODE). In production, user will receive USSD prompt.'
        ];
    }

    try {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => LONESTAR_MOMO_API_URL . '/transfer/request',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . LONESTAR_MOMO_API_KEY,
                'Content-Type: application/json',
                'X-Reference-Id: ' . $external_ref
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => (float)$amount,
                'currency' => 'LRD', // Liberian Dollar
                'externalId' => $external_ref,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone_number
                ],
                'payerMessage' => $description ?: 'Rent Payment',
                'payeeNote' => 'Payment received'
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 202) { // Accepted
            $data = json_decode($response, true);
            return [
                'success' => true,
                'transaction_id' => $data['transactionId'] ?? null,
                'reference_code' => $external_ref,
                'amount' => $amount,
                'phone' => $phone_number,
                'status' => 'initiated'
            ];
        } else {
            $error_data = json_decode($response, true);
            return [
                'success' => false,
                'error' => $error_data['message'] ?? 'Payment initiation failed',
                'code' => $http_code
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Check payment status
 */
function lonestar_check_payment_status($transaction_id) {
    if (!defined('LONESTAR_MOMO_LIVE') || !LONESTAR_MOMO_LIVE) {
        // Mock implementation
        // In production, randomly succeed some payments for testing
        $rand = rand(1, 100);
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'status' => $rand > 30 ? 'succeeded' : 'pending', // 70% success rate in mock
            'mock_mode' => true
        ];
    }

    try {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => LONESTAR_MOMO_API_URL . '/transfer/' . $transaction_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . LONESTAR_MOMO_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'status' => $data['status'] ?? 'unknown', // SUCCESSFUL, FAILED, PENDING
                'amount' => $data['amount'] ?? null,
                'timestamp' => $data['transactionTime'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to check payment status',
                'code' => $http_code
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get transaction history
 */
function lonestar_get_transaction_history($limit = 10) {
    if (!defined('LONESTAR_MOMO_LIVE') || !LONESTAR_MOMO_LIVE) {
        return [
            'success' => true,
            'transactions' => [],
            'mock_mode' => true
        ];
    }

    try {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => LONESTAR_MOMO_API_URL . '/transfer?limit=' . $limit,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . LONESTAR_MOMO_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'transactions' => $data['transfers'] ?? []
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to fetch transaction history'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Validate Lonestar phone number
 */
function validate_lonestar_phone($phone) {
    // Liberia phone format: 231XXXXXXXX
    return preg_match('/^231\d{8,9}$/', trim($phone)) ? true : false;
}

/**
 * Format phone number to Lonestar format
 */
function format_lonestar_phone($phone) {
    $phone = trim($phone);
    
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Handle different input formats
    if (strlen($phone) === 10) {
        // Assume Liberian number without country code
        return '231' . $phone;
    } elseif (strlen($phone) === 12 && strpos($phone, '231') === 0) {
        // Already has country code
        return $phone;
    } elseif (strlen($phone) === 11 && strpos($phone, '+231') === 0) {
        // Has + prefix
        return substr($phone, 1);
    } else {
        return null; // Invalid format
    }
}

/**
 * Webhook handler for Lonestar Momo payment notifications
 */
function handle_lonestar_webhook($payload) {
    // Payload structure:
    // {
    //   "transactionId": "...",
    //   "externalId": "...",
    //   "status": "SUCCESSFUL|FAILED|PENDING",
    //   "amount": 100.00,
    //   "currency": "LRD",
    //   "transactionTime": "2024-03-25T12:00:00Z"
    // }

    $data = json_decode($payload, true);

    if (!$data) {
        return ['success' => false, 'error' => 'Invalid payload'];
    }

    $transaction_id = $data['transactionId'] ?? null;
    $external_id = $data['externalId'] ?? null; // This should be our payment ID
    $status = $data['status'] ?? null;

    if (!$transaction_id || !$external_id) {
        return ['success' => false, 'error' => 'Missing required fields'];
    }

    // Map Lonestar status to our status
    $payment_status = '';
    switch ($status) {
        case 'SUCCESSFUL':
            $payment_status = 'approved';
            break;
        case 'FAILED':
            $payment_status = 'rejected';
            break;
        case 'PENDING':
            $payment_status = 'pending';
            break;
        default:
            $payment_status = 'unknown';
    }

    return [
        'success' => true,
        'transaction_id' => $transaction_id,
        'payment_id' => $external_id,
        'status' => $payment_status,
        'amount' => $data['amount'] ?? null
    ];
}
?>

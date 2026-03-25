<?php
/**
 * Stripe Payment Gateway Integration
 * Handles credit/debit card payments for RentConnect
 */

// Stripe configuration - Update these with your actuals keys from https://dashboard.stripe.com/apikeys
define('STRIPE_PUBLIC_KEY', 'pk_test_51N7Z7Z1LZ7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z');
define('STRIPE_SECRET_KEY', 'sk_test_51N7Z7Z1LZ7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z7Z');

// Check if Stripe library is installed
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    define('STRIPE_MOCK_MODE', true);
    // In production, run: composer require stripe/stripe-php
} else {
    require_once __DIR__ . '/vendor/autoload.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
}

/**
 * Create a Stripe payment intent for rent payment
 */
function stripe_create_payment_intent($booking_id, $amount, $currency = 'usd', $description = '') {
    if (defined('STRIPE_MOCK_MODE')) {
        // Mock response for development
        return [
            'success' => true,
            'client_secret' => 'pi_mock_' . uniqid(),
            'intent_id' => 'pi_mock_' . uniqid(),
            'mock_mode' => true
        ];
    }

    try {
        $intent = \Stripe\PaymentIntent::create([
            'amount' => (int)($amount * 100), // Convert to cents
            'currency' => $currency,
            'description' => $description,
            'metadata' => [
                'booking_id' => $booking_id,
                'platform' => 'rentconnect'
            ]
        ]);

        return [
            'success' => true,
            'client_secret' => $intent->client_secret,
            'intent_id' => $intent->id,
            'mock_mode' => false
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Confirm a Stripe payment
 */
function stripe_confirm_payment($intent_id) {
    if (defined('STRIPE_MOCK_MODE')) {
        return [
            'success' => true,
            'status' => 'succeeded',
            'intent_id' => $intent_id
        ];
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($intent_id);
        
        if ($intent->status === 'succeeded') {
            return [
                'success' => true,
                'status' => 'succeeded',
                'intent_id' => $intent->id,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency
            ];
        } elseif ($intent->status === 'processing') {
            return [
                'success' => false,
                'status' => 'processing',
                'message' => 'Payment is still processing'
            ];
        } else {
            return [
                'success' => false,
                'status' => $intent->status,
                'message' => 'Payment failed or was cancelled'
            ];
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create a Stripe customer (for recurring payments)
 */
function stripe_create_customer($email, $name, $phone = '') {
    if (defined('STRIPE_MOCK_MODE')) {
        return [
            'success' => true,
            'customer_id' => 'cus_mock_' . uniqid(),
            'mock_mode' => true
        ];
    }

    try {
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => $name,
            'phone' => $phone
        ]);

        return [
            'success' => true,
            'customer_id' => $customer->id
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create a Stripe setup intent (for saving payment method)
 */
function stripe_create_setup_intent($customer_id) {
    if (defined('STRIPE_MOCK_MODE')) {
        return [
            'success' => true,
            'client_secret' => 'seti_mock_' . uniqid(),
            'mock_mode' => true
        ];
    }

    try {
        $intent = \Stripe\SetupIntent::create([
            'customer' => $customer_id,
            'usage' => 'off_session' // Allows charging without customer present
        ]);

        return [
            'success' => true,
            'client_secret' => $intent->client_secret,
            'intent_id' => $intent->id
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get Stripe public key for frontend
 */
function get_stripe_public_key() {
    return STRIPE_PUBLIC_KEY;
}

/**
 * Process webhook from Stripe (for payment confirmations)
 */
function handle_stripe_webhook($payload, $sig_header) {
    $endpoint_secret = 'whsec_test_YOUR_WEBHOOK_SECRET'; // Set this up in Stripe dashboard
    
    if (defined('STRIPE_MOCK_MODE')) {
        return ['success' => true, 'mock' => true];
    }

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $endpoint_secret
        );

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $booking_id = $paymentIntent->metadata->booking_id ?? null;
                // Update payment status in database as 'completed'
                return ['success' => true, 'action' => 'payment_confirmed', 'booking_id' => $booking_id];
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $booking_id = $paymentIntent->metadata->booking_id ?? null;
                // Update payment status in database as 'failed'
                return ['success' => true, 'action' => 'payment_failed', 'booking_id' => $booking_id];
                break;

            default:
                return ['success' => false, 'message' => 'Unhandled event type'];
        }
    } catch (\UnexpectedValueException $e) {
        return ['success' => false, 'error' => 'Invalid payload'];
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return ['success' => false, 'error' => 'Invalid signature'];
    }
}

/**
 * Get payment status
 */
function stripe_get_payment_status($intent_id) {
    if (defined('STRIPE_MOCK_MODE')) {
        return [
            'success' => true,
            'status' => 'succeeded',
            'mock_mode' => true
        ];
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($intent_id);
        return [
            'success' => true,
            'status' => $intent->status,
            'amount' => $intent->amount / 100,
            'currency' => $intent->currency
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>

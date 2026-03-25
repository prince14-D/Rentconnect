<?php
session_start();
include "app_init.php";
include "stripe_payment.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$payment_id = intval($_GET['payment_id'] ?? 0);
$booking_id = intval($_GET['booking_id'] ?? 0);

// Fetch payment details
$stmt = $conn->prepare("
    SELECT py.*, b.property_id, p.title 
    FROM payments py
    JOIN bookings b ON py.booking_id = b.id
    JOIN properties p ON b.property_id = p.id
    WHERE py.id = ? AND py.renter_id = ?
");
$stmt->bind_param("ii", $payment_id, $renter_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Payment not found");
}

$stripe_public_key = get_stripe_public_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Complete your rent payment with Stripe.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Pay with Card - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<script src="https://js.stripe.com/v3/"></script>
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
  min-height: 100vh;
}
.container { width: min(500px, 90vw); margin: 40px auto; }
.card {
  background: rgba(255,255,255,0.95);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow);
}
.header {
  text-align: center;
  margin-bottom: 24px;
}
.header h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.8rem;
  color: var(--ink);
  margin-bottom: 8px;
}
.header p {
  color: var(--muted);
  font-size: 0.95rem;
}
.payment-summary {
  background: #f6f9fc;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 24px;
  border-left: 4px solid var(--brand);
}
.summary-row {
  display: flex;
  justify-content: space-between;
  margin: 8px 0;
  font-size: 0.95rem;
}
.summary-row strong {
  color: var(--ink);
}
.summary-row span {
  color: var(--muted);
}
.summary-total {
  border-top: 1px solid var(--line);
  padding-top: 12px;
  margin-top: 12px;
  font-weight: 700;
  font-size: 1.1rem;
}
.StripeElement {
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: white;
  margin-bottom: 16px;
}
.StripeElement--focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.1);
}
label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--ink);
  font-size: 0.9rem;
}
.form-group {
  margin-bottom: 16px;
}
.error {
  color: #c0392b;
  font-size: 0.85rem;
  margin-top: 4px;
}
.success {
  color: #27ae60;
  font-size: 0.85rem;
  margin-top: 4px;
}
.btn {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--brand), var(--brand-deep));
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 12px;
}
.btn:hover {
  box-shadow: 0 10px 24px rgba(31, 143, 103, 0.3);
  transform: translateY(-2px);
}
.btn:disabled {
  background: var(--muted);
  cursor: not-allowed;
  opacity: 0.6;
}
.cancel-btn {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}
.cancel-btn:hover {
  background: rgba(31, 36, 48, 0.15);
}
.spinner {
  height: 20px;
  width: 20px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.loading-text {
  display: none;
  text-align: center;
  color: var(--muted);
  margin-top: 12px;
  font-size: 0.9rem;
}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="header">
      <h1>💳 Pay with Card</h1>
      <p>Secure payment powered by Stripe</p>
    </div>

    <div class="payment-summary">
      <div class="summary-row">
        <strong><?php echo htmlspecialchars($payment['title']); ?></strong>
        <span>Property</span>
      </div>
      <div class="summary-row">
        <strong><?php echo date('F Y', strtotime($payment['payment_month'])); ?></strong>
        <span>Payment For</span>
      </div>
      <div class="summary-total">
        Amount Due: $<?php echo number_format($payment['amount'], 2); ?>
      </div>
    </div>

    <form id="payment-form">
      <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">

      <div class="form-group">
        <label for="card-element">Card Details</label>
        <div id="card-element" class="StripeElement"></div>
        <div id="card-errors" class="error"></div>
      </div>

      <div id="payment-message" class="success" style="display: none;"></div>

      <button type="submit" id="submit-btn" class="btn">
        Pay $<?php echo number_format($payment['amount'], 2); ?>
      </button>
      <button type="button" class="btn cancel-btn" onclick="window.history.back()">Cancel</button>

      <div class="loading-text" id="loading-text">
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
          <div class="spinner"></div>
          Processing payment...
        </div>
      </div>
    </form>
  </div>
</div>

<script>
const stripe = Stripe('<?php echo htmlspecialchars($stripe_public_key); ?>');
const elements = stripe.elements();
const cardElement = elements.create('card');
cardElement.mount('#card-element');

const cardErrorsDisplay = document.querySelector('#card-errors');
cardElement.on('change', (event) => {
    if (event.error) {
        cardErrorsDisplay.textContent = event.error.message;
    } else {
        cardErrorsDisplay.textContent = '';
    }
});

const form = document.getElementById('payment-form');
const submitBtn = document.getElementById('submit-btn');
const loadingText = document.getElementById('loading-text');
const paymentMessage = document.getElementById('payment-message');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    
    submitBtn.disabled = true;
    loadingText.style.display = 'block';
    paymentMessage.style.display = 'none';

    const paymentId = document.querySelector('input[name="payment_id"]').value;

    // Step 1: Initiate payment with backend
    try {
        const initResponse = await fetch('process_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'stripe_initiate',
                booking_id: '<?php echo $booking_id; ?>',
                amount: '<?php echo $payment['amount']; ?>',
                payment_month: '<?php echo $payment['payment_month']; ?>'
            })
        });

        const initData = await initResponse.json();
        if (!initData.success) {
            throw new Error(initData.error);
        }

        // Step 2: Confirm payment with Stripe
        const { error, paymentIntent } = await stripe.confirmCardPayment(initData.client_secret, {
            payment_method: {
                card: cardElement,
                billing_details: {}
            }
        });

        if (error) {
            cardErrorsDisplay.textContent = error.message;
            submitBtn.disabled = false;
            loadingText.style.display = 'none';
        } else if (paymentIntent.status === 'succeeded') {
            // Step 3: Confirm on backend
            const confirmResponse = await fetch('process_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'stripe_confirm',
                    payment_id: initData.payment_id,
                    intent_id: paymentIntent.id
                })
            });

            const confirmData = await confirmResponse.json();
            if (confirmData.success) {
                paymentMessage.textContent = confirmData.message;
                paymentMessage.style.display = 'block';
                cardErrorsDisplay.textContent = '';
                setTimeout(() => {
                    window.location.href = 'rent_payments.php';
                }, 2000);
            } else {
                throw new Error(confirmData.error);
            }
        }
    } catch (err) {
        cardErrorsDisplay.textContent = err.message || 'Payment processing failed';
        submitBtn.disabled = false;
        loadingText.style.display = 'none';
    }
});
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => console.log('[PWA] Service Worker registered'))
            .catch((error) => console.warn('[PWA] Service Worker registration failed:', error));
    });
}
</script>
</body>
</html>

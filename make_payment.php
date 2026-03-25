<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$payment_id = intval($_GET['payment_id'] ?? 0);
$booking_id = intval($_GET['booking_id'] ?? 0);

// If payment_id is provided, fetch payment details
$payment = null;
if ($payment_id > 0) {
  $payment = rc_mig_get_payment_detail_for_renter($conn, $payment_id, $renter_id);

    if (!$payment) {
        die("Payment not found");
    }
    $booking_id = $payment['booking_id'];
}
// If booking_id is provided, create a new payment or fetch existing
else if ($booking_id > 0) {
  $booking = rc_mig_get_booking_detail_for_renter($conn, $booking_id, $renter_id);

    if (!$booking) {
        die("Booking not found");
    }

    // Check for unpaid payments for this booking
  $payment = rc_mig_get_open_payment_for_booking($conn, $booking_id, $renter_id);

  if ($payment) {
        $payment_id = $payment['id'];
    } else {
        // Create new payment for current month
        $payment_month = date('Y-m-01');
    $amount = (float) ($booking['monthly_rent'] ?: $booking['price']);
    $payment_id = rc_mig_create_payment_from_booking($conn, $booking_id, $renter_id, $amount, $payment_month, 'card', 'draft');
    if ($payment_id <= 0) {
      die('Unable to initialize payment');
    }

    $payment = rc_mig_get_payment_detail_for_renter($conn, $payment_id, $renter_id);
    if (!$payment) {
      die('Payment initialization failed');
    }
    }
} else {
    die("No payment or booking specified");
}

$stripe_public_key = getenv('STRIPE_PUBLIC_KEY') ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Complete your rent payment on RentConnect.">
<meta name="theme-color" content="#1f8f67">
<title>Make Payment - RentConnect</title>
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

header {
  background: linear-gradient(135deg, var(--brand), var(--brand-deep));
  color: white;
  padding: 20px;
  box-shadow: var(--shadow);
}

.header-content {
  max-width: 600px;
  margin: 0 auto;
}

.header-content h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.8rem;
  margin-bottom: 4px;
}

.header-content p {
  font-size: 0.95rem;
  opacity: 0.9;
}

.container {
  max-width: 600px;
  margin: 0 auto;
  padding: 32px 24px;
}

.card {
  background: rgba(255,255,255,0.95);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow);
  margin-bottom: 24px;
}

.payment-summary {
  background: #f6f9fc;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 24px;
  border-left: 4px solid var(--brand);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin: 10px 0;
  font-size: 0.95rem;
}

.summary-row label {
  color: var(--muted);
  font-weight: 500;
}

.summary-row span {
  font-weight: 600;
  color: var(--ink);
}

.summary-total {
  border-top: 2px solid var(--line);
  padding-top: 16px;
  margin-top: 16px;
  display: flex;
  justify-content: space-between;
  font-size: 1.2rem;
  font-weight: 800;
}

.payment-methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}

.payment-method {
  padding: 20px;
  border: 2px solid var(--line);
  border-radius: 10px;
  cursor: pointer;
  text-align: center;
  transition: all 0.3s;
  background: white;
}

.payment-method:hover {
  border-color: var(--brand);
  box-shadow: 0 6px 12px rgba(31, 143, 103, 0.15);
}

.payment-method.active {
  border-color: var(--brand);
  background: rgba(31, 143, 103, 0.05);
  box-shadow: 0 6px 12px rgba(31, 143, 103, 0.2);
}

.method-icon {
  font-size: 2rem;
  margin-bottom: 8px;
}

.method-name {
  font-weight: 700;
  color: var(--ink);
  font-size: 0.95rem;
  margin-bottom: 4px;
}

.method-fee {
  font-size: 0.8rem;
  color: var(--muted);
}

.form-group {
  margin-bottom: 20px;
}

label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--ink);
  font-size: 0.9rem;
}

input[type="text"],
input[type="email"],
input[type="tel"],
textarea,
.StripeElement {
  width: 100%;
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.95rem;
  transition: all 0.2s;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="tel"]:focus,
textarea:focus,
.StripeElement--focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.1);
}

textarea {
  resize: vertical;
  min-height: 80px;
}

.error-message {
  color: #c0392b;
  font-size: 0.85rem;
  margin-top: 4px;
}

.success-message {
  background: rgba(39, 174, 96, 0.1);
  border: 1px solid #27ae60;
  border-radius: 8px;
  padding: 12px;
  color: #27ae60;
  margin-bottom: 16px;
  font-weight: 600;
}

.button-group {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.btn {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 1rem;
}

.btn-primary {
  background: linear-gradient(135deg, var(--brand), var(--brand-deep));
  color: white;
}

.btn-primary:hover {
  box-shadow: 0 10px 24px rgba(31, 143, 103, 0.3);
  transform: translateY(-2px);
}

.btn-primary:disabled {
  background: var(--muted);
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-secondary {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}

.btn-secondary:hover {
  background: rgba(31, 36, 48, 0.15);
}

.loading {
  display: none;
  text-align: center;
  color: var(--muted);
  font-size: 0.9rem;
  margin-top: 12px;
}

.spinner {
  height: 20px;
  width: 20px;
  border: 3px solid rgba(31, 143, 103, 0.3);
  border-radius: 50%;
  border-top-color: var(--brand);
  animation: spin 0.8s linear infinite;
  margin: 0 auto 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.payment-info {
  background: rgba(52, 152, 219, 0.05);
  border-left: 4px solid #3498db;
  padding: 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  color: var(--muted);
  margin-bottom: 20px;
}

@media (max-width: 600px) {
  .header-content h1 {
    font-size: 1.5rem;
  }

  .payment-methods {
    grid-template-columns: repeat(2, 1fr);
  }

  .button-group {
    flex-direction: column;
  }

  .card {
    padding: 20px;
  }
}
</style>
</head>
<body>
<header>
  <div class="header-content">
    <h1>💳 Make Payment</h1>
    <p>Secure payment for <?php echo date('F Y', strtotime($payment['payment_month'])); ?></p>
  </div>
</header>

<div class="container">
  <div class="card">
    <!-- Payment Summary -->
    <div class="payment-summary">
      <div class="summary-row">
        <label>Property:</label>
        <span><?php echo htmlspecialchars(substr($payment['title'] ?? '', 0, 30)); ?></span>
      </div>
      <div class="summary-row">
        <label>Landlord:</label>
        <span><?php echo htmlspecialchars($payment['landlord_name'] ?? ''); ?></span>
      </div>
      <div class="summary-row">
        <label>Month:</label>
        <span><?php echo date('F Y', strtotime($payment['payment_month'])); ?></span>
      </div>
      <div class="summary-total">
        <span>Amount Due:</span>
        <span>$<?php echo number_format($payment['amount'], 2); ?></span>
      </div>
    </div>

    <!-- Payment Methods -->
    <div>
      <h3 style="margin-bottom: 16px; font-size: 1rem; font-weight: 700;">Choose Payment Method</h3>
      <div class="payment-methods">
        <div class="payment-method active" data-method="stripe" onclick="selectMethod('stripe')">
          <div class="method-icon">💳</div>
          <div class="method-name">Card</div>
          <div class="method-fee">2.9% fee</div>
        </div>
        <div class="payment-method" data-method="momo" onclick="selectMethod('momo')">
          <div class="method-icon">📱</div>
          <div class="method-name">Lonestar</div>
          <div class="method-fee">Mobile Money</div>
        </div>
        <div class="payment-method" data-method="bank" onclick="selectMethod('bank')">
          <div class="method-icon">🏦</div>
          <div class="method-name">Bank</div>
          <div class="method-fee">Transfer</div>
        </div>
        <div class="payment-method" data-method="check" onclick="selectMethod('check')">
          <div class="method-icon">✓</div>
          <div class="method-name">Check</div>
          <div class="method-fee">Mailed</div>
        </div>
      </div>
    </div>

    <!-- Stripe Payment Form -->
    <div id="stripe-form" class="method-form" style="display: block;">
      <form id="payment-form">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
        <input type="hidden" name="action" value="stripe_initiate">

        <div class="form-group">
          <label>Card Details</label>
          <div id="card-element" style="padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: white;"></div>
          <div id="card-errors" class="error-message"></div>
        </div>

        <div id="payment-message" class="success-message" style="display: none;"></div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary" id="submit-btn">
            Pay $<?php echo number_format($payment['amount'], 2); ?>
          </button>
          <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
        </div>

        <div class="loading" id="loading">
          <div class="spinner"></div>
          Processing payment...
        </div>
      </form>
    </div>

    <!-- Lonestar Momo Form -->
    <div id="momo-form" class="method-form" style="display: none;">
      <div class="payment-info">
        💡 You'll receive a prompt on your phone to confirm the payment. No additional charges.
      </div>
      <form id="momo-payment-form">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
        <input type="hidden" name="action" value="momo_initiate">

        <div class="form-group">
          <label for="phone">Phone Number *</label>
          <input type="tel" id="phone" name="phone_number" placeholder="231xxxxxxxxx" required>
          <small style="color: var(--muted); font-size: 0.8rem; margin-top: 4px; display: block;">Format: 231 followed by 9 digits</small>
        </div>

        <div id="momo-message" class="success-message" style="display: none;"></div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary">
            Send Payment Request
          </button>
          <button type="button" class="btn btn-secondary" onclick="selectMethod('stripe')">Back</button>
        </div>

        <div class="loading" id="momo-loading">
          <div class="spinner"></div>
          Sending payment request...
        </div>
      </form>
    </div>

    <!-- Bank Transfer Form -->
    <div id="bank-form" class="method-form" style="display: none;">
      <div class="payment-info">
        📋 Submit your bank transfer details. We'll verify payment within 2-3 business days.
      </div>
      <form id="bank-payment-form">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <input type="hidden" name="action" value="submit_manual">
        <input type="hidden" name="payment_method" value="bank_transfer">

        <div class="form-group">
          <label for="ref">Reference Number *</label>
          <input type="text" id="ref" name="reference_number" placeholder="e.g., Transfer confirmation number" required>
        </div>

        <div class="form-group">
          <label for="notes">Transaction Details *</label>
          <textarea id="notes" name="notes" placeholder="Bank name, date of transfer, account last 4 digits, etc." required></textarea>
        </div>

        <div id="bank-message" class="success-message" style="display: none;"></div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary">Submit Transfer Info</button>
          <button type="button" class="btn btn-secondary" onclick="selectMethod('stripe')">Back</button>
        </div>
      </form>
    </div>

    <!-- Check Form -->
    <div id="check-form" class="method-form" style="display: none;">
      <div class="payment-info">
        ✓ Send check to your landlord. Include your name and property address on the check.
      </div>
      <form id="check-payment-form">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <input type="hidden" name="action" value="submit_manual">
        <input type="hidden" name="payment_method" value="check">

        <div class="form-group">
          <label for="check-ref">Check Number *</label>
          <input type="text" id="check-ref" name="reference_number" placeholder="e.g., 12345" required>
        </div>

        <div class="form-group">
          <label for="check-notes">Notes *</label>
          <textarea id="check-notes" name="notes" placeholder="Expected mail date, any special instructions, etc." required></textarea>
        </div>

        <div id="check-message" class="success-message" style="display: none;"></div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary">Confirm Check Payment</button>
          <button type="button" class="btn btn-secondary" onclick="selectMethod('stripe')">Back</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 16px;">
    <p>🔒 Your payment is secure and encrypted. All transactions are protected.</p>
  </div>
</div>

<script>
// Initialize Stripe
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

// Payment method selection
function selectMethod(method) {
    document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
    document.querySelector(`[data-method="${method}"]`).classList.add('active');
    document.querySelectorAll('.method-form').forEach(f => f.style.display = 'none');
    document.getElementById(`${method}-form`).style.display = 'block';
}

// Stripe payment form submission
const form = document.getElementById('payment-form');
if (form) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = document.getElementById('submit-btn');
        const loading = document.getElementById('loading');
        const paymentMessage = document.getElementById('payment-message');

        submitBtn.disabled = true;
        loading.style.display = 'block';
        paymentMessage.style.display = 'none';

        const bookingId = document.querySelector('input[name="booking_id"]').value;
        const paymentId = document.querySelector('input[name="payment_id"]').value;
        const amount = '<?php echo $payment['amount']; ?>';
        const paymentMonth = '<?php echo $payment['payment_month']; ?>';

        try {
            // Step 1: Initiate payment
            const initResponse = await fetch('process_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'stripe_initiate',
                    booking_id: bookingId,
                    amount: amount,
                    payment_month: paymentMonth
                })
            });

            const initData = await initResponse.json();
            if (!initData.success) {
                throw new Error(initData.error);
            }

            // Step 2: Confirm with Stripe
            const { error, paymentIntent } = await stripe.confirmCardPayment(initData.client_secret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {}
                }
            });

            if (error) {
                cardErrorsDisplay.textContent = error.message;
                submitBtn.disabled = false;
                loading.style.display = 'none';
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
                        window.location.href = 'rental_requests.php?status=approved';
                    }, 2000);
                } else {
                    throw new Error(confirmData.error);
                }
            }
        } catch (err) {
            cardErrorsDisplay.textContent = err.message || 'Payment processing failed';
            submitBtn.disabled = false;
            loading.style.display = 'none';
        }
    });
}

// Lonestar Momo form submission
const momoForm = document.getElementById('momo-payment-form');
if (momoForm) {
    momoForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const loading = document.getElementById('momo-loading');
        const message = document.getElementById('momo-message');

        submitBtn.disabled = true;
        loading.style.display = 'block';

        try {
            const formData = new FormData(momoForm);
            const response = await fetch('process_payment.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });

            const data = await response.json();
            if (data.success) {
                message.textContent = data.message || 'Payment request sent!';
                message.style.display = 'block';
                setTimeout(() => {
                    window.location.href = 'rental_requests.php?status=approved';
                }, 2000);
            } else {
                alert('Error: ' + data.error);
                submitBtn.disabled = false;
            }
        } catch (err) {
            alert('Payment failed: ' + err.message);
            submitBtn.disabled = false;
        }
        loading.style.display = 'none';
    });
}

// Bank transfer form submission
const bankForm = document.getElementById('bank-payment-form');
if (bankForm) {
    bankForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const message = document.getElementById('bank-message');

        try {
            const formData = new FormData(bankForm);
            const response = await fetch('process_payment.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });

            const data = await response.json();
            if (data.success) {
                message.textContent = data.message || 'Bank transfer details submitted!';
                message.style.display = 'block';
                setTimeout(() => {
                    window.location.href = 'rental_requests.php?status=approved';
                }, 2000);
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Submission failed: ' + err.message);
        }
    });
}

// Check form submission
const checkForm = document.getElementById('check-payment-form');
if (checkForm) {
    checkForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const message = document.getElementById('check-message');

        try {
            const formData = new FormData(checkForm);
            const response = await fetch('process_payment.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });

            const data = await response.json();
            if (data.success) {
                message.textContent = data.message || 'Check payment confirmed!';
                message.style.display = 'block';
                setTimeout(() => {
                    window.location.href = 'rental_requests.php?status=approved';
                }, 2000);
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Submission failed: ' + err.message);
        }
    });
}
</script>
</body>
</html>

# RentConnect Payment System - Installation & Setup Guide

## Quick Start

This guide walks through setting up the complete payment system for RentConnect with Stripe and Lonestar Mobile Money integration.

## Prerequisites

- PHP 7.4+
- MySQL 5.7+
- cURL extension enabled
- OpenSSL extension enabled
- Valid Stripe account
- Valid Lonestar Momo business account (optional)

## Step 1: Database Setup

Run these SQL commands to create required tables:

```sql
-- Payments Table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    renter_id INT NOT NULL,
    landlord_id INT NOT NULL,
    property_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_month DATE NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(255),
    stripe_intent_id VARCHAR(255),
    status ENUM('draft', 'pending', 'submitted', 'confirmed', 'failed', 'refunded') DEFAULT 'draft',
    payment_date TIMESTAMP,
    paid_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    verification_note TEXT,
    refund_reason TEXT,
    refunded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payment (booking_id, payment_month),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    INDEX idx_status (status),
    INDEX idx_renter_id (renter_id),
    INDEX idx_landlord_id (landlord_id),
    INDEX idx_created_at (created_at)
);

-- Payment Logs Table
CREATE TABLE payment_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    transaction_type VARCHAR(100),
    status VARCHAR(50),
    gateway_response JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_created_at (created_at)
);
```

## Step 2: Environment Configuration

Create or update your `.env` file with these variables:

```env
# Stripe Configuration
STRIPE_PUBLIC_KEY=pk_live_your_public_key_here
STRIPE_SECRET_KEY=sk_live_your_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# Lonestar Mobile Money Configuration (Optional)
LONESTAR_API_KEY=your_api_key_here
LONESTAR_API_URL=https://api.lonestar.com/v1
LONESTAR_WEBHOOK_SECRET=your_webhook_secret_here

# Application Settings
APP_URL=https://your-domain.com
APP_ENV=production
LOG_LEVEL=debug
```

### Alternative: Direct PHP Constants

If you don't use .env, add to `db.php`:

```php
// Stripe Configuration
define('STRIPE_PUBLIC_KEY', 'pk_live_your_public_key_here');
define('STRIPE_SECRET_KEY', 'sk_live_your_secret_key_here');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret_here');

// Lonestar Mobile Money
define('LONESTAR_API_KEY', 'your_api_key_here');
define('LONESTAR_API_URL', 'https://api.lonestar.com/v1');
define('LONESTAR_WEBHOOK_SECRET', 'your_webhook_secret_here');
```

## Step 3: Get Stripe Credentials

1. **Create Stripe Account**
   - Go to https://stripe.com
   - Sign up for a business account
   - Verify your business details

2. **Get API Keys**
   - Dashboard > Developers > API keys
   - Copy "Publishable key" → STRIPE_PUBLIC_KEY
   - Copy "Secret key" → STRIPE_SECRET_KEY

3. **Create Webhook**
   - Settings > Webhooks
   - Add endpoint: `https://your-domain.com/webhook_handler.php?type=stripe`
   - Events to trigger:
     - `charge.succeeded`
     - `charge.failed`
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`
   - Copy webhook signing secret → STRIPE_WEBHOOK_SECRET

## Step 4: Get Lonestar Credentials (Optional)

1. **Contact Lonestar**
   - Business contact: business@lonestarmoney.com
   - Request API integration
   - Provide callback URLs

2. **Receive Credentials**
   - API Key → LONESTAR_API_KEY
   - Webhook Secret → LONESTAR_WEBHOOK_SECRET
   - API Endpoint → LONESTAR_API_URL

3. **Configure Webhook**
   - Callback URL: `https://your-domain.com/webhook_handler.php?type=momo`

## Step 5: File Verification

Verify all payment files are present:

```
/var/www/html/rentconnect/
├── stripe_payment.php          ✓ Stripe integration
├── lonestar_momo.php           ✓ Lonestar integration
├── stripe_checkout.php         ✓ Card payment form
├── make_payment.php            ✓ Payment method selector
├── process_payment.php         ✓ Payment processor
├── rent_payments.php           ✓ Renter payment dashboard
├── download_receipt.php        ✓ Receipt generator
├── landlord_payment_verification.php  ✓ Landlord approval
├── admin_payment_management.php       ✓ Admin dashboard
├── webhook_handler.php         ✓ Webhook processor
└── PAYMENT_SYSTEM.md           ✓ Documentation
```

## Step 6: Update Navigation

Add payment links to user dashboards:

### For Renters
In `renter_dashboard.php`, add:
```html
<a href="rent_payments.php" class="btn">💰 View Payments</a>
<a href="make_payment.php?booking_id=<?php echo $booking_id; ?>" class="btn">💳 Make Payment</a>
```

### For Landlords
In `landlord_dashboard.php`, add:
```html
<a href="landlord_payment_verification.php" class="btn">💳 Verify Payments</a>
<a href="rental_requests.php" class="btn">💬 View Requests</a>
```

### For Admins
In `super_admin_dashboard.php`, add:
```html
<a href="admin_payment_management.php" class="btn">💳 Payment Management</a>
```

## Step 7: Test the System

### 1. Test Stripe Card Payments

```
1. Log in as renter
2. Go to "Make Payment" or "Rent Payments"
3. Click "Pay Now" on a property
4. Select "Card" payment method
5. Use test card: 4242 4242 4242 4242
6. Enter any future date and any 3 digits CVC
7. Complete payment
8. Verify confirmation message
9. Check payment status in rent_payments.php
```

### 2. Test Lonestar Mobile Money

```
1. Log in as renter
2. Go to "Make Payment"
3. Select "Lonestar Momo" method
4. Enter test phone: 231886123456
5. Click "Send Payment Request"
6. Verify success message
7. Check payment status updates
```

### 3. Test Manual Payment

```
1. Log in as renter
2. Go to "Make Payment"
3. Select "Bank Transfer"
4. Enter reference number and details
5. Click "Submit"
6. Log in as landlord
7. Go to "Verify Payments"
8. Review submitted payment
9. Click "Approve" or "Reject"
10. Verify status updates
```

### 4. Test Webhooks

Stripe provides webhook testing tool:
```
1. Dashboard > Developers > Webhooks
2. Click "Send test webhook"
3. Check webhook_handler.php logs
4. Verify payment status updated
```

## Step 8: Security Hardening

### 1. Enable HTTPS Only
```php
// In process_payment.php, webhook_handler.php
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    die('HTTPS required for payment processing');
}
```

### 2. Set Secure Headers
```php
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://js.stripe.com');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
```

### 3. Rate Limiting
```php
// Limit payment attempts per user
$payment_attempts = cache_get("payment_attempts_{$_SESSION['user_id']}") ?? 0;
if ($payment_attempts > 5) {
    die('Too many payment attempts. Try again later.');
}
```

## Step 9: Monitoring & Logging

### Enable Payment Logging
```php
// In db.php or config file
define('ENABLE_PAYMENT_LOGS', true);
define('PAYMENT_LOG_FILE', '/var/log/rentconnect/payments.log');
```

### Check Logs
```bash
tail -f /var/log/rentconnect/payments.log
tail -f /var/log/php-fpm.log
```

### Monitor Webhooks
```bash
# Check webhook response times
mysql -u root -p rentconnect -e "
    SELECT DATE(created_at), COUNT(*), AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at))
    FROM payment_logs
    WHERE transaction_type = 'webhook'
    GROUP BY DATE(created_at);
"
```

## Step 10: Go Live

### Before Production:

- [ ] Test all payment methods thoroughly
- [ ] Verify database backups
- [ ] Enable HTTPS throughout
- [ ] Configure SSL certificate
- [ ] Set up monitoring/alerts
- [ ] Train support team
- [ ] Create runbook for common issues
- [ ] Set up payment reconciliation
- [ ] Enable payment logging
- [ ] Configure error notifications
- [ ] Test webhook failover
- [ ] Set up PCI scanning
- [ ] Verify compliance checklist

### Production Checklist:

```bash
# 1. Verify HTTPS
curl -I https://your-domain.com
# Should return 200 with HTTPS

# 2. Test API connectivity
php -r "
    include 'stripe_payment.php';
    echo get_stripe_public_key() ? 'Stripe: OK' : 'Stripe: ERROR';
"

# 3. Check database
mysql -u root -p rentconnect -e "
    SELECT COUNT(*) FROM payments;
    SELECT COUNT(*) FROM payment_logs;
"

# 4. Verify file permissions
ls -la /var/www/html/rentconnect/*payment*.php
```

## Troubleshooting

### Stripe Connection Issues
```php
// Verify credentials
define('STRIPE_SECRET_KEY', 'sk_live_...');
$stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
try {
    $stripe->plans->all();
    echo "Connection: OK";
} catch (Exception $e) {
    echo "Connection Error: " . $e->getMessage();
}
```

### Database Issues
```sql
-- Check table structure
DESCRIBE payments;

-- Verify foreign keys
SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'payments';

-- Check for errors
SELECT * FROM payment_logs 
WHERE error_message IS NOT NULL;
```

### Webhook Issues
```bash
# Test webhook endpoint
curl -X POST \
  https://your-domain.com/webhook_handler.php?type=stripe \
  -H 'stripe-signature: test_signature' \
  -d '{
    "id": "evt_test",
    "type": "charge.succeeded"
  }'

# Should return 200 OK
```

## Performance Optimization

### Database Indexing
Already configured in setup, but verify:
```sql
SHOW INDEXES FROM payments;
SHOW INDEXES FROM payment_logs;
```

### Query Optimization
- Rent payments page: Uses pagination (10 per page)
- Payment history: Indexed by created_at
- Dashboard: Caches statistics

### Webhook Performance
- Webhooks process asynchronously
- Database updates are atomic
- Email notifications are non-blocking

## Backup Strategy

```bash
# Daily backup
mysqldump -u root -p rentconnect payments payment_logs > /backups/payments_$(date +%Y%m%d).sql

# Verify backup
mysql -u root -p rentconnect < /backups/payments_20240101.sql
```

## Support & Resources

- Stripe Documentation: https://stripe.com/docs
- Lonestar API Guide: Contact your account manager
- RentConnect Docs: See PAYMENT_SYSTEM.md
- Support Email: support@rentconnect.com

---

**Setup Complete!** Your payment system is ready for use.

For questions or issues, refer to PAYMENT_SYSTEM.md or contact support.

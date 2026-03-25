# RentConnect Payment System - Quick Reference

## 🚀 Quick Start (5 Minutes to Payment Page)

```bash
1. Update .env with Stripe keys:
   STRIPE_PUBLIC_KEY=pk_live_xxxxx
   STRIPE_SECRET_KEY=sk_live_xxxxx

2. Create database tables:
   mysql < PAYMENT_SETUP.md (SQL section)

3. Test the system:
   https://yourdomain.com/make_payment.php?booking_id=1
```

---

## 📂 File Map

```
PAYMENT PROCESSING
├── process_payment.php ............... Central router
└── webhook_handler.php .............. Async updates

PAYMENT GATEWAYS
├── stripe_payment.php ............... Stripe API
└── lonestar_momo.php ................ Lonestar API

USER INTERFACES
├── make_payment.php ................. Payment selector (Renter)
├── rent_payments.php ................ Dashboard (Renter)
├── landlord_payment_verification.php  Approval (Landlord)
├── admin_payment_management.php ...... Analytics (Admin)
└── download_receipt.php ............. Receipt (Renter)

DOCUMENTATION
├── PAYMENT_SYSTEM.md ................ Technical spec
├── PAYMENT_SETUP.md ................. Install guide
├── PAYMENT_ARCHITECTURE.md .......... System design
└── This file ....................... Quick reference
```

---

## 🔌 API Quick Reference

### Process a Payment
```php
// Step 1: Initiate
POST /process_payment.php
{
    action: 'stripe_initiate',
    booking_id: 123,
    amount: 1500.00,
    payment_month: '2024-01-01'
}
// Response: { success: true, client_secret: 'pi_xxxxx', payment_id: 456 }

// Step 2: Confirm
POST /process_payment.php
{
    action: 'stripe_confirm',
    payment_id: 456,
    intent_id: 'pi_xxxxx'
}
// Response: { success: true, message: 'Payment received!' }
```

### Check Payment Status
```sql
SELECT * FROM payments WHERE id = 456;
-- Status values: draft, pending, submitted, confirmed, failed, refunded
```

### Handle Webhook
```php
// Automatically processes:
POST /webhook_handler.php?type=stripe
POST /webhook_handler.php?type=momo

// Database updates automatically
// Emails sent automatically (if configured)
```

---

## 🎯 Implementation Checklist

### Database
- [ ] Run SQL to create `payments` table
- [ ] Run SQL to create `payment_logs` table
- [ ] Verify tables with: `SHOW TABLES;`

### Configuration
- [ ] Create .env file with Stripe keys
- [ ] Add Stripe webhook URL to Stripe dashboard
- [ ] Test Stripe connection with test key

### Files
- [ ] All 12 files present in /var/www/html/rentconnect/
- [ ] File permissions correct (644)
- [ ] Database connection working

### Testing
- [ ] Test Stripe payment with test card
- [ ] Test bank transfer submission
- [ ] Test landlord approval
- [ ] Download receipt

### Deployment
- [ ] Switch to production Stripe keys
- [ ] Enable HTTPS
- [ ] Set up email notifications
- [ ] Configure backups
- [ ] Monitor payment logs

---

## 💡 Common Tasks

### Get Payment Status
```php
$stmt = $conn->prepare("SELECT status FROM payments WHERE id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo $result['status']; // 'confirmed', 'pending', 'failed', etc.
```

### Approve Manual Payment (Landlord)
```php
$update = $conn->prepare("UPDATE payments SET status = 'confirmed', verified_at = NOW() WHERE id = ?");
$update->bind_param("i", $payment_id);
$update->execute();
```

### Refund Payment (Admin)
```php
$refund = $conn->prepare("UPDATE payments SET status = 'refunded', refunded_at = NOW(), refund_reason = ? WHERE id = ?");
$refund->bind_param("si", $reason, $payment_id);
$refund->execute();
```

### Get Payment History
```php
$stmt = $conn->prepare("
    SELECT * FROM payments 
    WHERE renter_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
```

---

## 🔍 Debugging

### Check Stripe Connection
```php
define('STRIPE_SECRET_KEY', 'sk_test_xxxxx');
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
curl_setopt($curl, CURLOPT_USERPWD, 'STRIPE_SECRET_KEY' . ':');
curl_exec($curl);
echo curl_getinfo($curl, CURLINFO_HTTP_CODE); // Should be 200
```

### Check Database
```sql
SELECT COUNT(*) FROM payments;
SELECT status, COUNT(*) FROM payments GROUP BY status;
SELECT * FROM payment_logs WHERE error_message IS NOT NULL LIMIT 5;
```

### Check Logs
```bash
tail -f /var/log/php-fpm.log        # PHP errors
tail -f /var/log/mysql/error.log    # MySQL errors
grep "payment" /var/log/apache2/error.log  # Payment errors
```

### Test Webhook Signature
```php
$webhook_secret = getenv('STRIPE_WEBHOOK_SECRET');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, $webhook_secret);
echo ($signature === $sig_header) ? 'Valid' : 'Invalid';
```

---

## 📊 Status Definitions

| Status | Meaning | Next Action |
|--------|---------|------------|
| **draft** | Payment form started | User completes payment |
| **pending** | Waiting for gateway | Webhook will update |
| **submitted** | Manual payment submitted | Landlord must approve |
| **confirmed** | Payment complete ✓ | Send receipt |
| **failed** | Payment failed ✗ | User can retry |
| **refunded** | Money returned | Close transaction |

---

## 🛠️ Setup Variables

Copy these to your `.env` file:

```env
# Stripe
STRIPE_PUBLIC_KEY=pk_test_yourkey
STRIPE_SECRET_KEY=sk_test_yourkey
STRIPE_WEBHOOK_SECRET=whsec_yourwebhook

# Lonestar (Optional)
LONESTAR_API_KEY=your_key
LONESTAR_API_URL=https://api.lonestar.com/v1
LONESTAR_WEBHOOK_SECRET=your_secret

# App Settings
APP_URL=http://localhost:8000
APP_ENV=development
LOG_LEVEL=debug
```

---

## 🔐 Security Checklist

- [ ] STRIPE_SECRET_KEY never exposed to browser
- [ ] HTTPS enabled on all payment pages
- [ ] Webhook signatures verified
- [ ] CSRF tokens on all forms
- [ ] User authentication required
- [ ] Payment ownership verified
- [ ] Input validation enforced
- [ ] Error messages don't leak info
- [ ] No payment data in logs
- [ ] Database encrypted at rest

---

## 📞 Support Contacts

**Stripe Issues:**
- Dashboard: https://dashboard.stripe.com
- Docs: https://stripe.com/docs
- Support: https://support.stripe.com

**Lonestar Issues:**
- Contact: business@lonestarmoney.com
- Documentation: Provided by Lonestar

**RentConnect Issues:**
- See: PAYMENT_SYSTEM.md
- See: PAYMENT_SETUP.md
- Email: support@rentconnect.com

---

## ⚡ Performance Tips

1. Use database indexes (already configured)
2. Cache payment methods list
3. Use async webhooks (already implemented)
4. Optimize images on pages
5. Enable gzip compression
6. Use CDN for Stripe.js

---

## 🎓 Learning Resources

1. **Stripe API Docs:** https://stripe.com/docs/api
2. **Lonestar API:** Contact provider
3. **Payment System Docs:** See PAYMENT_SYSTEM.md
4. **Architecture Diagram:** See PAYMENT_ARCHITECTURE.md

---

## 📝 Common Code Snippets

### Get Renter Payments
```php
$stmt = $conn->prepare("
    SELECT * FROM payments WHERE renter_id = ? AND status = 'confirmed'
");
$stmt->bind_param("i", $renter_id);
$stmt->execute();
return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
```

### Get Unpaid Payments
```php
$stmt = $conn->prepare("
    SELECT * FROM payments WHERE renter_id = ? AND status IN ('pending', 'failed')
");
$stmt->bind_param("i", $renter_id);
$stmt->execute();
return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
```

### Send Payment Confirmation
```php
// Email sent automatically on confirmation
// If not working, check email_notifications.php is included:
include "email_notifications.php";
send_payment_confirmation_email($email, $name, $amount);
```

---

**Quick Reference Version:** 1.0
**Last Updated:** 2024
**Status:** Ready to Use

For more details, see the comprehensive documentation files.

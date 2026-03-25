# RentConnect Payment System Documentation

## Overview
Complete payment processing system with support for multiple payment methods and gateways.

## Architecture

### Payment Methods Supported
1. **Stripe (Credit/Debit Card)** - Primary payment method
2. **Lonestar Mobile Money** - Liberia-specific mobile money
3. **Bank Transfer** - Manual verification
4. **Check** - Mailed payment
5. **Money Order** - Mailed payment

### Database Schema

#### `payments` Table
```sql
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    renter_id INT NOT NULL,
    landlord_id INT NOT NULL,
    property_id INT NOT NULL,
    amount DECIMAL(10,2),
    payment_month DATE,
    payment_method VARCHAR(50),
    reference_number VARCHAR(255),
    stripe_intent_id VARCHAR(255),
    status ENUM('draft', 'pending', 'submitted', 'confirmed', 'failed', 'refunded'),
    payment_date TIMESTAMP,
    paid_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    verification_note TEXT,
    refund_reason TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id)
);

CREATE TABLE payment_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    transaction_type VARCHAR(50),
    status VARCHAR(50),
    gateway_response JSON,
    created_at TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);
```

## Core Files

### Backend Files

#### 1. `stripe_payment.php` - Stripe Integration
**Functions:**
- `stripe_create_payment_intent($booking_id, $amount, $currency, $description)` - Creates payment intent
- `stripe_confirm_payment($intent_id)` - Confirms payment status
- `get_stripe_public_key()` - Returns public key
- `get_stripe_secret_key()` - Returns secret key (internal)

**Configuration:**
```php
// .env or environment
STRIPE_PUBLIC_KEY=pk_live_xxxxx
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

#### 2. `lonestar_momo.php` - Lonestar Mobile Money Integration
**Functions:**
- `lonestar_initiate_payment($amount, $phone, $external_ref, $description)` - Starts payment request
- `lonestar_check_payment_status($transaction_id)` - Polls payment status
- `validate_lonestar_phone($phone)` - Validates phone format
- `format_lonestar_phone($phone)` - Formats phone number

**Configuration:**
```php
// .env or environment
LONESTAR_API_KEY=xxxxx
LONESTAR_API_URL=https://api.lonestar.com/v1
LONESTAR_WEBHOOK_SECRET=xxxxx
```

#### 3. `process_payment.php` - Payment Processing Handler
**Endpoints:**
- `POST /process_payment.php?action=stripe_initiate` - Creates Stripe PaymentIntent
- `POST /process_payment.php?action=stripe_confirm` - Confirms Stripe payment
- `POST /process_payment.php?action=momo_initiate` - Initiates Lonestar payment
- `POST /process_payment.php?action=momo_check_status` - Checks Lonestar status
- `POST /process_payment.php?action=submit_manual` - Submits manual payment (check/transfer)
- `POST /process_payment.php?action=get_payment_methods` - Returns available methods

**Response Format:**
```json
{
    "success": true,
    "message": "Payment initiated",
    "client_secret": "pi_xxxxx",
    "payment_id": 123
}
```

#### 4. `webhook_handler.php` - Async Payment Confirmation
**Endpoints:**
- `GET /webhook_handler.php?type=stripe` - Stripe webhook
- `GET /webhook_handler.php?type=momo` - Lonestar webhook

**Setup Instructions:**
```
Stripe Dashboard:
- Settings > Webhooks
- URL: https://yourdomain.com/webhook_handler.php?type=stripe
- Events: charge.succeeded, charge.failed, payment_intent.succeeded

Lonestar Dashboard:
- Settings > Webhooks
- URL: https://yourdomain.com/webhook_handler.php?type=momo
- Events: payment_successful, payment_failed
```

### Frontend Files

#### 1. `make_payment.php` - Payment Method Selection
- Multi-method payment interface
- Stripe card form with real-time validation
- Lonestar phone number input
- Bank transfer and check submission forms
- Mobile responsive design

**URL Parameters:**
- `payment_id` - Existing payment to process
- `booking_id` - Booking to create payment for

#### 2. `stripe_checkout.php` - Stripe Payment Form
- Dedicated Stripe card payment interface
- Clean, minimal design
- Payment summary display
- Success/error messaging

#### 3. `rent_payments.php` - Payment Dashboard
- Shows payment history with pagination
- Statistics on taxes (paid, pending, failed)
- Active properties grid with quick pay buttons
- Download receipts for confirmed payments
- Filter and search capabilities

#### 4. `download_receipt.php` - Payment Receipt
- Generates HTML receipt for confirmed payments
- Shows payment details, amounts, and transaction ID
- Can be printed or saved as PDF

#### 5. `landlord_payment_verification.php` - Landlord Verification
- Shows pending/submitted payments from tenants
- Landlord can approve, reject, or request more details
- Statistics on verified vs pending payments
- Modal-based approval/rejection workflow

#### 6. `admin_payment_management.php` - Admin Dashboard
- Platform-wide payment statistics
- Payment method breakdown
- Recent payment history
- Refund management interface
- Monthly revenue trends

## Payment Flow

### Stripe Card Payment Flow
```
1. Renter selects Stripe method → make_payment.php
2. Form submitted → process_payment.php?action=stripe_initiate
3. Backend creates PaymentIntent
4. Frontend confirms payment with Stripe API
5. Stripe returns payment status
6. Backend confirms in DB → process_payment.php?action=stripe_confirm
7. Webhook handler receives async confirmation
8. Send notifications to both parties
```

### Lonestar Mobile Money Flow
```
1. Renter enters phone → make_payment.php
2. Form submitted → process_payment.php?action=momo_initiate
3. Backend initiates Lonestar payment request
4. User receives USSD/App prompt on phone
5. User confirms payment
6. Webhook handler receives confirmation
7. Backend updates payment status
8. Send notifications
```

### Manual Payment Flow
```
1. Renter selects Bank Transfer/Check → make_payment.php
2. Renter enters transaction details
3. Form submitted → process_payment.php?action=submit_manual
4. Payment marked as "submitted"
5. Landlord reviews → landlord_payment_verification.php
6. Landlord approves or rejects
7. Status updated to "confirmed" or "rejected"
8. Notifications sent
```

## API Integration Details

### Stripe Integration
**Payment Intent Creation:**
```php
POST https://api.stripe.com/v1/payment_intents
Parameters:
- amount: 5000 (in cents)
- currency: usd
- metadata: {booking_id: 123}
```

**Card Payment Confirmation:**
```javascript
stripe.confirmCardPayment(clientSecret, {
    payment_method: {
        card: cardElement,
        billing_details: {}
    }
})
```

### Lonestar Momo Integration
**Payment Initiation:**
```php
POST {API_URL}/paymentRequest
Headers:
- X-API-KEY: {API_KEY}
Body JSON:
{
    "amount": "5000",
    "phoneNumber": "231xxxxxxxxx",
    "externalReference": "RC_123_1234567890",
    "description": "Rent for January 2024"
}
```

**Status Check:**
```php
GET {API_URL}/paymentRequest/{externalReference}
Headers:
- X-API-KEY: {API_KEY}
```

## Security Features

### 1. Payment Data Protection
- PCI DSS compliance via Stripe
- No sensitive card data stored locally
- Webhooks use HMAC signatures
- HTTPS only
- Rate limiting on payment endpoints

### 2. Authentication & Authorization
- Session-based auth for renters/landlords
- Role-based access control
- Payment ownership verification
- CSRF tokens on forms

### 3. Input Validation
- Phone number format validation
- Amount range validation
- Payment method validation
- Reference number format validation

## Error Handling

### Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| `stripe_public_key` missing | Env variable not set | Set STRIPE_PUBLIC_KEY in .env |
| Invalid phone format | Wrong format for Lonestar | Use 231xxxxxxxxx format |
| Payment intent failed | Insufficient funds | Try with different card or amount |
| Webhook not received | Not configured | Set up webhook in Stripe dashboard |
| Payment not confirmed | Async issues | Check webhook_handler.php logs |

## Testing

### Test Cards (Stripe)
```
Success: 4242 4242 4242 4242
Decline: 4000 0000 0000 0002
3D Secure: 4000 0025 0000 3155
```

### Test Phone Numbers (Lonestar)
```
Success: 231886123456
Failure: 231886654321
Invalid: 123456789
```

## Configuration Checklist

- [ ] Create `payments` and `payment_logs` tables
- [ ] Set STRIPE_PUBLIC_KEY env variable
- [ ] Set STRIPE_SECRET_KEY env variable
- [ ] Set STRIPE_WEBHOOK_SECRET env variable
- [ ] Configure Stripe webhook endpoint
- [ ] Set LONESTAR_API_KEY env variable
- [ ] Set LONESTAR_WEBHOOK_SECRET env variable
- [ ] Configure Lonestar webhook endpoint
- [ ] Set up email notifications
- [ ] Test payment flow end-to-end
- [ ] Monitor webhook logs

## Monitoring & Logging

### Log Locations
- Payment errors: `error_log` in php.ini location
- Webhook events: Check application logs
- Transaction history: `payment_logs` table

### Key Metrics to Monitor
- Payment success rate
- Average payment processing time
- Failed payments percentage
- Popular payment methods
- Refund rate

## Troubleshooting

### Common Issues

**1. Stripe payments stuck in draft**
```
Check: process_payment.php request logs
Verify: Client secret is correct
Verify: Stripe API keys are valid
```

**2. Lonestar webhooks not firing**
```
Check: Webhook URL is publicly accessible
Verify: Webhook secret matches in config
Check: Firewall allows Lonestar IPs
```

**3. Payments not updating status**
```
Check: Database connection
Verify: Payment record exists
Check: User permissions on payment record
```

## Future Enhancements

- [ ] Payment plans/installments
- [ ] Automatic payment retries
- [ ] Multi-currency support
- [ ] PayPal integration
- [ ] Square integration
- [ ] Payment analytics dashboard
- [ ] Automated dunning for failed payments
- [ ] Subscription billing
- [ ] Real-time payment notifications
- [ ] Advanced reconciliation tools

## Support

For issues or questions:
1. Check error logs
2. Review webhook_handler.php logs
3. Verify environment variables
4. Test with test credentials
5. Contact payment gateway support

---
Documentation Version: 1.0
Last Updated: 2024

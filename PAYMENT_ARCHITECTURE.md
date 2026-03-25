# RentConnect Payment System Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          RENTCONNECT PAYMENT SYSTEM                         │
└─────────────────────────────────────────────────────────────────────────────┘

                              ┌──────────────────┐
                              │    USERS (Web)   │
                              └────────┬─────────┘
                                       │
                ┌──────────────────────┼──────────────────────┐
                │                      │                      │
         ┌──────▼──────┐       ┌──────▼──────┐       ┌──────▼──────┐
         │   RENTERS   │       │  LANDLORDS  │       │    ADMINS    │
         └──────┬──────┘       └──────┬──────┘       └──────┬──────┘
                │                     │                      │
         ┌──────▼──────────────┐ ┌────▼─────────────┐ ┌────▼────────────┐
         │  make_payment.php   │ │landlord_payment_ │ │admin_payment_   │
         │  rent_payments.php  │ │verification.php  │ │management.php   │
         └──────┬──────────────┘ └────┬─────────────┘ └────┬────────────┘
                │                     │                    │
                └──────────────────────┼────────────────────┘
                                       │
                            ┌──────────▼────────────┐
                            │ process_payment.php   │
                            │  (Central Router)     │
                            └──┬───────┬───────┬────┘
                               │       │       │
                ┌──────────────┬┘       │       └───┬──────────────┐
                │              │       │           │              │
         ┌─────▼────┐  ┌──────▼────┐ │    ┌──────▼──────┐  ┌───▼─────┐
         │  Stripe  │  │ Lonestar  │ │    │   Manual    │  │ Logging │
         │  Gateway │  │   Momo    │ │    │ Verification│  │   DB    │
         └─────┬────┘  └──────┬────┘ │    └──────┬──────┘  └───┬─────┘
               │              │      │           │            │
         ┌─────▼────┐  ┌──────▼───┐ │    ┌──────▼──────┐     │
         │Stripe    │  │Lonestar  │ │    │ Landlord    │     │
         │Webhook   │  │ Webhook  │ │    │ Approval    │     │
         └──────────┘  └──────────┘ │    └─────────────┘     │
                                     │                        │
                            ┌────────▼─────────────────────────▼────┐
                            │  webhook_handler.php                  │
                            │  (Async Confirmation Handler)         │
                            └────────┬────────────────────────────┬─┘
                                     │                            │
                                     └────────┬───────────────────┘
                                              │
                                    ┌─────────▼────────────┐
                                    │  Database Layer      │
                                    ├─────────────────────┤
                                    │ ✓ payments          │
                                    │ ✓ payment_logs      │
                                    │ ✓ bookings          │
                                    │ ✓ users             │
                                    │ ✓ properties        │
                                    └─────────────────────┘
```

## Payment Flow Sequences

### 1. Stripe Card Payment Flow

```
RENTER                  BROWSER              BACKEND              STRIPE API
  │                       │                    │                      │
  │ Click "Pay Now"       │                    │                      │
  ├──────────────────────►│                    │                      │
  │                       │                    │                      │
  │                       │ GET make_payment   │                      │
  │                       │────────────────────►                      │
  │                       │◄────────────────────                      │
  │                       │ HTML Form Rendered │                      │
  │                       │                    │                      │
  │ Enter Card Details    │                    │                      │
  │ Click "Pay"           │                    │                      │
  ├──────────────────────►│                    │                      │
  │                       │ POST /process_payment.php?action=stripe_initiate
  │                       │────────────────────►                      │
  │                       │                    │ stripe_create_payment_intent()
  │                       │                    ├─────────────────────►│
  │                       │                    │◄─────────────────────┤
  │                       │                    │ client_secret        │
  │                       │                    │                      │
  │                       │ Return client_secret                      │
  │                       │◄────────────────────                      │
  │                       │                    │                      │
  │                       │ stripe.confirmCardPayment()               │
  │                       │──────────────────────────────────────────►│
  │                       │◄──────────────────────────────────────────┤
  │                       │ success: true                             │
  │                       │                    │                      │
  │                       │ POST /process_payment.php?action=stripe_confirm
  │                       │────────────────────►                      │
  │                       │                    │ UPDATE payments SET status='confirmed'
  │                       │                    │ (database)          │
  │                       │◄────────────────────                      │
  │                       │ success: true      │                      │
  │                       │                    │                      │
  │ Redirect to          │                    │                      │
  │ rental_requests      │◄────────────────────                      │
  │◄──────────────────────                    │                      │
  │                       │                    │                      │
  │ [Later] WEBHOOK EVENT │                    │                      │
  │                       │                    │                      │
  │                       │                    │◄─────────────────────│
  │                       │                    │ charge.succeeded     │
  │                       │ POST /webhook_handler.php?type=stripe     │
  │                       │◄───────────────────────────────────────────┤
  │                       │                    │ UPDATE payments SET status='confirmed'
  │                       │                    │ SEND EMAIL NOTIFICATIONS
```

### 2. Lonestar Mobile Money Flow

```
RENTER              BROWSER            BACKEND          LONESTAR API      PHONE
  │                   │                   │                  │               │
  │ Click "Pay"       │                   │                  │               │
  ├──────────────────►│                   │                  │               │
  │                   │ GET make_payment  │                  │               │
  │                   │──────────────────►│                  │               │
  │                   │ HTML Form         │                  │               │
  │                   │◄──────────────────│                  │               │
  │                   │                   │                  │               │
  │ Enter Phone       │                   │                  │               │
  │ Click "Send"      │                   │                  │               │
  ├──────────────────►│                   │                  │               │
  │                   │ POST /process_payment.php?action=momo_initiate
  │                   │──────────────────►│                  │               │
  │                   │                   │ lonestar_initiate_payment()
  │                   │                   ├─────────────────►│               │
  │                   │                   │◄─────────────────┤               │
  │                   │                   │ transaction_id   │               │
  │                   │                   │                  │ USSD/App      │
  │                   │                   │                  ├──────────────►│
  │                   │                   │                  │ Enter PIN     │
  │                   │                   │                  │◄──────────────┤
  │                   │                   │                  │               │
  │                   │ success message   │                  │               │
  │                   │◄──────────────────│                  │               │
  │                   │                   │                  │               │
  │ "Check Your Phone"│                   │                  │               │
  │◄───────────────────                   │                  │               │
  │                   │                   │                  │               │
  │ [Background]      │                   │                  │               │
  │ Polling...        │ POST /process_payment.php?action=momo_check_status
  │                   │──────────────────►│                  │               │
  │                   │                   │ lonestar_check_payment_status()
  │                   │                   ├─────────────────►│               │
  │                   │                   │◄─────────────────┤               │
  │                   │                   │ status: success  │               │
  │                   │                   │                  │               │
  │                   │ [Later] WEBHOOK   │                  │               │
  │                   │                   │◄─────────────────┤               │
  │                   │ POST /webhook_handler.php?type=momo  │               │
  │                   │──────────────────►│                  │               │
  │                   │                   │ UPDATE payments  │               │
  │                   │                   │ SEND EMAILS      │               │
  │                   │                   │                  │               │
  │ Payment Complete  │                   │                  │               │
  │                   │                   │                  │               │
```

### 3. Manual Payment (Bank Transfer/Check)

```
RENTER              BROWSER            BACKEND         LANDLORD             
  │                   │                   │                  │
  │ Click "Pay"       │                   │                  │
  ├──────────────────►│                   │                  │
  │                   │ Render form       │                  │
  │                   │◄──────────────────│                  │
  │                   │                   │                  │
  │ Enter Bank        │                   │                  │
  │ Details           │                   │                  │
  │ Click "Submit"    │                   │                  │
  ├──────────────────►│                   │                  │
  │                   │ POST /process_payment.php
  │                   │ action=submit_manual
  │                   │──────────────────►│                  │
  │                   │                   │ INSERT payment   │
  │                   │                   │ SET status='submitted'
  │                   │                   │ SEND NOTIFICATION
  │                   │                   ├─────────────────►│
  │                   │◄──────────────────│                  │
  │                   │ success: true     │                  │
  │                   │                   │                  │
  │ "Payment"         │                   │                  │
  │ "Submitted for    │                   │                  │
  │ Verification"     │                   │                  │
  │                   │                   │                  │
  │ [Landlord Action] │                   │                  │
  │                   │                   │ Log in           │
  │                   │                   │ Go to Verify     │
  │                   │                   │ Payments         │
  │                   │                   │                  │
  │                   │                   │ Click Approve    │
  │                   │                   │ POST form        │
  │                   │                   │ action=approve   │
  │                   │                   ├─────┐
  │                   │                   │ UPDATE payments
  │                   │                   │ SET status='confirmed'
  │                   │                   │ SEND EMAIL
  │                   │                   │
  │ Payment Approved  │                   │
  │ (Email)           │                   │
  │

```

## File Dependencies & Imports

```
index.php
├── db.php (database connection)
├── session/auth
└── header/footer

make_payment.php
├── db.php
├── stripe_payment.php
├── lonestar_momo.php
└── JavaScript (Stripe.js)

process_payment.php
├── db.php
├── stripe_payment.php
├── lonestar_momo.php
├── email_notifications.php (optional)
└── log_transaction() helper

webhook_handler.php
├── db.php
├── stripe_payment.php
├── lonestar_momo.php
├── email_notifications.php
└── log_transaction() helper

landlord_payment_verification.php
├── db.php
├── session/auth (landlord check)
└── function getStatusBadge()

admin_payment_management.php
├── db.php
├── session/auth (admin check)
└── function getStatusBadge()

rent_payments.php
├── db.php
├── session/auth (renter check)
└── function getStatusBadge()
```

## Data Flow Diagram

```
USER INPUT
    ↓
make_payment.php (Form Rendering)
    ↓
Browser (Client-side Stripe/Validation)
    ↓
process_payment.php (Entry Point)
    ├─► stripe_payment.php (if Stripe)
    ├─► lonestar_momo.php (if Momo)
    └─► Database Insertion
        ├─ INSERT INTO payments
        └─ INSERT INTO payment_logs
    ↓
GATEWAY (API Call)
    ├─► Stripe (/v1/payment_intents)
    └─► Lonestar (/paymentRequest)
    ↓
Response → process_payment.php
    ↓
UPDATE Database
    ├─ payments.status
    ├─ payments.reference_number
    ├─ payments.stripe_intent_id
    └─ INSERT payment_logs
    ↓
Send Confirmation Email
    ↓
Browser Redirect
    ↓
Display Success Page
    ↓
[ASYNC]
WEBHOOK EVENT
    ↓
webhook_handler.php
    ↓
Verify Signature
    ↓
UPDATE Database
    ↓
Send Final Notifications
```

## Status State Machine

```
                    ┌─────────┐
                    │  DRAFT  │ (Not used currently)
                    └────┬────┘
                         │
                    ┌────▼────────┐
              ┌─────►  PENDING    │
              │     └────┬────────┘
              │          │
              │     ┌────▼──────────┐
    User     │     │  SUBMITTED    │ (Manual payments)
    initiates│     └────┬───┬──────┘
    payment  │          │   │
              │          │   │ (Landlord action)
              │     ┌────▼───▼──┐
              │     │ CONFIRMED │◄─── (Auto after Stripe webhook)
              │     └────┬──────┘
              │          │
              │     ┌────▼──────┐
              └────►  FAILED   │
                    └────┬──────┘
                         │
                    ┌────▼───────┐
                    │ REFUNDED   │
                    └────────────┘
```

---

This diagram shows the complete architecture of the RentConnect payment system with all the payment gateways, user flows, and data interactions.

# Supabase Migration Checklist

Date: 2026-03-25

## Completed in this pass

- Added shared migration bridge: `supabase_migration.php`
- Loaded migration bridge globally in `db.php`
- Migrated property read path to helper abstraction:
  - `property.php`
  - `viewProperty.php`
- Migrated landlord request workflow to helper abstraction:
  - `rental_requests.php`
  - `update_request.php`
- Migrated payment flow to helper abstraction and repaired handler:
  - `make_payment.php`
  - `process_payment.php`
  - `rent_payments.php`
- Added missing manual payment action in `process_payment.php` (`submit_manual`)
- Verified syntax for all touched files (no errors)

## Current migration model

- Runtime remains hybrid for safety.
- Business pages now call migration helper functions instead of embedding most SQL in controllers.
- Helper layer can attempt Supabase REST when `SUPABASE_USE_REST=1`.
- Fallback path remains local `mysqli` for compatibility.

## Remaining MySQL hotspots (audit)

Audit command showed 85 `mysqli`/prepared-statement references still present across the app.

Priority migration queue:

1. Auth/account flows
- `login.php`
- `signup.php` / `register.php`
- `forgot-password.php` / `reset-password.php`
- `firebase_auth.php`

2. Core dashboards and discovery
- `index.php`
- `renter_dashboard.php`
- `landlord_dashboard.php`
- `super_admin_dashboard.php`

3. Property CRUD and media
- `add_property.php`
- `edit_property.php`
- `upload_property.php`
- `display_image.php`
- `display_property_image.php`

4. Messaging and chat
- `chat.php`
- `chat_list.php`
- `chat_refresh.php`
- `fetch_messages.php`
- `send_message.php`

5. Legacy/admin subfolder pages
- `admin/manage_properties.php`
- `admin/manage_users.php`
- `admin/create_user.php`

## Next technical steps

- Add Supabase-specific read/write implementations for each helper function in `supabase_migration.php`.
- Gradually replace direct prepared statements in untouched pages with helper calls.
- After each module:
  - run lint/syntax checks,
  - test role-specific page flows,
  - confirm Supabase RLS/policies for affected tables.

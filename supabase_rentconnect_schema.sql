-- RentConnect schema for Supabase (PostgreSQL)
-- Date: 2026-03-25
-- Run this entire file in Supabase SQL Editor.

begin;

create extension if not exists pgcrypto;

-- Shared trigger function for updated_at columns.
create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

-- USERS
create table if not exists public.users (
  id bigserial primary key,
  name text not null,
  email text not null unique,
  password text not null,
  role text not null default 'renter' check (role in ('renter', 'landlord', 'admin', 'super_admin')),
  phone text,
  theme_color text default '#2e7d32',
  firebase_uid text unique,
  auth_provider text not null default 'local' check (auth_provider in ('local', 'firebase_google', 'firebase_email')),
  avatar_url text,
  email_verified boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- PROPERTIES
create table if not exists public.properties (
  id bigserial primary key,
  landlord_id bigint references public.users(id) on delete cascade,
  owner_id bigint references public.users(id) on delete set null,
  title text not null,
  location text,
  address text,
  price numeric(12,2),
  monthly_rent numeric(12,2),
  contact text,
  bedrooms integer,
  bathrooms integer,
  description text,
  purpose text,
  image text,
  status text not null default 'pending' check (status in ('pending', 'approved', 'rejected', 'taken', 'available')),
  approved_by bigint references public.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- PROPERTY IMAGES
create table if not exists public.property_images (
  id bigserial primary key,
  property_id bigint not null references public.properties(id) on delete cascade,
  image bytea not null,
  mime_type text not null,
  created_at timestamptz not null default now()
);

-- LEGACY REQUESTS (used by renter_dashboard/update_request flow)
create table if not exists public.requests (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  property_id bigint not null references public.properties(id) on delete cascade,
  status text not null default 'pending' check (status in ('pending', 'approved', 'rejected', 'cancelled')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- RENTAL REQUESTS
create table if not exists public.rental_requests (
  id bigserial primary key,
  property_id bigint not null references public.properties(id) on delete cascade,
  renter_id bigint not null references public.users(id) on delete cascade,
  landlord_id bigint not null references public.users(id) on delete cascade,
  message text,
  status text not null default 'pending' check (status in ('pending', 'approved', 'rejected', 'cancelled')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- BOOKINGS
create table if not exists public.bookings (
  id bigserial primary key,
  property_id bigint not null references public.properties(id) on delete cascade,
  renter_id bigint not null references public.users(id) on delete cascade,
  landlord_id bigint not null references public.users(id) on delete cascade,
  move_in_date date not null,
  move_out_date date,
  monthly_rent numeric(12,2) not null,
  lease_duration_months integer not null default 12,
  status text not null default 'active' check (status in ('active', 'expired', 'cancelled')),
  cancelled_by text check (cancelled_by in ('renter', 'landlord')),
  cancellation_reason text,
  cancelled_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- RENTAL AGREEMENTS
create table if not exists public.rental_agreements (
  id bigserial primary key,
  booking_id bigint not null unique references public.bookings(id) on delete cascade,
  property_id bigint not null references public.properties(id) on delete cascade,
  renter_id bigint not null references public.users(id) on delete cascade,
  landlord_id bigint not null references public.users(id) on delete cascade,
  monthly_rent numeric(12,2) not null,
  lease_start date not null,
  lease_end date,
  duration_months integer,
  deposit_amount numeric(12,2),
  utilities_included boolean not null default false,
  pets_allowed boolean not null default false,
  parking boolean not null default false,
  additional_terms text,
  signed_by_renter boolean not null default false,
  signed_by_landlord boolean not null default false,
  renter_signature_date timestamptz,
  landlord_signature_date timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- PAYMENTS
create table if not exists public.payments (
  id bigserial primary key,
  booking_id bigint not null references public.bookings(id) on delete cascade,
  renter_id bigint not null references public.users(id) on delete cascade,
  landlord_id bigint not null references public.users(id) on delete cascade,
  property_id bigint not null references public.properties(id) on delete cascade,
  amount numeric(12,2) not null,
  payment_month date not null,
  payment_method text default 'card' check (payment_method in ('card', 'momo', 'bank_transfer', 'check', 'money_order')),
  payment_date timestamptz,
  submitted_at timestamptz,
  paid_at timestamptz,
  verified_at timestamptz,
  refunded_at timestamptz,
  status text not null default 'pending' check (status in ('draft', 'pending', 'submitted', 'approved', 'confirmed', 'rejected', 'failed', 'refunded')),
  reference_number text,
  stripe_intent_id text,
  landlord_verified boolean not null default false,
  notes text,
  verification_note text,
  refund_reason text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (booking_id, payment_month)
);

-- CHAT MESSAGES
create table if not exists public.messages (
  id bigserial primary key,
  property_id bigint references public.properties(id) on delete set null,
  sender_id bigint not null references public.users(id) on delete cascade,
  receiver_id bigint not null references public.users(id) on delete cascade,
  message text not null,
  read_status boolean not null default false,
  created_at timestamptz not null default now()
);

-- PASSWORD RESETS
create table if not exists public.password_resets (
  id bigserial primary key,
  user_id bigint not null references public.users(id) on delete cascade,
  token text not null unique,
  created_at timestamptz not null default now()
);

-- RENT REMINDERS
create table if not exists public.rent_reminders (
  id bigserial primary key,
  booking_id bigint not null references public.bookings(id) on delete cascade,
  landlord_id bigint not null references public.users(id) on delete cascade,
  renter_id bigint not null references public.users(id) on delete cascade,
  payment_month date not null,
  reminder_type text not null default 'manual' check (reminder_type in ('due_soon', 'overdue', 'manual')),
  message text,
  sent_at timestamptz not null default now(),
  is_read boolean not null default false,
  read_by_renter boolean not null default false,
  read_at timestamptz
);

-- INDEXES
create index if not exists idx_users_role on public.users(role);
create index if not exists idx_users_firebase_uid on public.users(firebase_uid);

create index if not exists idx_properties_landlord_id on public.properties(landlord_id);
create index if not exists idx_properties_status on public.properties(status);

create index if not exists idx_property_images_property_id on public.property_images(property_id);

create index if not exists idx_requests_user_id on public.requests(user_id);
create index if not exists idx_requests_property_id on public.requests(property_id);
create index if not exists idx_requests_status on public.requests(status);

create index if not exists idx_rental_requests_renter_id on public.rental_requests(renter_id);
create index if not exists idx_rental_requests_landlord_id on public.rental_requests(landlord_id);
create index if not exists idx_rental_requests_status on public.rental_requests(status);

create index if not exists idx_bookings_property_id on public.bookings(property_id);
create index if not exists idx_bookings_renter_id on public.bookings(renter_id);
create index if not exists idx_bookings_landlord_id on public.bookings(landlord_id);
create index if not exists idx_bookings_status on public.bookings(status);

create index if not exists idx_rental_agreements_booking_id on public.rental_agreements(booking_id);

create index if not exists idx_payments_booking_id on public.payments(booking_id);
create index if not exists idx_payments_renter_id on public.payments(renter_id);
create index if not exists idx_payments_landlord_id on public.payments(landlord_id);
create index if not exists idx_payments_status on public.payments(status);
create index if not exists idx_payments_payment_month on public.payments(payment_month);

create index if not exists idx_messages_property_id on public.messages(property_id);
create index if not exists idx_messages_sender_receiver on public.messages(sender_id, receiver_id);
create index if not exists idx_messages_created_at on public.messages(created_at);

create index if not exists idx_password_resets_user_id on public.password_resets(user_id);
create index if not exists idx_password_resets_created_at on public.password_resets(created_at);

create index if not exists idx_rent_reminders_booking_id on public.rent_reminders(booking_id);
create index if not exists idx_rent_reminders_renter_id on public.rent_reminders(renter_id);

-- TRIGGERS FOR updated_at

drop trigger if exists trg_users_updated_at on public.users;
create trigger trg_users_updated_at
before update on public.users
for each row execute function public.set_updated_at();

drop trigger if exists trg_properties_updated_at on public.properties;
create trigger trg_properties_updated_at
before update on public.properties
for each row execute function public.set_updated_at();

drop trigger if exists trg_requests_updated_at on public.requests;
create trigger trg_requests_updated_at
before update on public.requests
for each row execute function public.set_updated_at();

drop trigger if exists trg_rental_requests_updated_at on public.rental_requests;
create trigger trg_rental_requests_updated_at
before update on public.rental_requests
for each row execute function public.set_updated_at();

drop trigger if exists trg_bookings_updated_at on public.bookings;
create trigger trg_bookings_updated_at
before update on public.bookings
for each row execute function public.set_updated_at();

drop trigger if exists trg_rental_agreements_updated_at on public.rental_agreements;
create trigger trg_rental_agreements_updated_at
before update on public.rental_agreements
for each row execute function public.set_updated_at();

drop trigger if exists trg_payments_updated_at on public.payments;
create trigger trg_payments_updated_at
before update on public.payments
for each row execute function public.set_updated_at();

commit;

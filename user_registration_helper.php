<?php

require_once __DIR__ . '/supabase_migration.php';
require_once __DIR__ . '/supabase_rest.php';

function rc_register_user($conn, string $name, string $email, string $rawPassword, string $role): array {
    $name = trim($name);
    $email = trim($email);

    if ($name === '' || $email === '' || $rawPassword === '' || $role === '') {
        return ['ok' => false, 'error' => 'Please complete all required fields.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    if (strlen($rawPassword) < 6) {
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    if (!in_array($role, ['landlord', 'renter'], true)) {
        return ['ok' => false, 'error' => 'Invalid role selected.'];
    }

    $existing = rc_mig_get_user_auth_by_email($conn, $email);
    if ($existing) {
        return ['ok' => false, 'error' => 'Email already registered. Please login.'];
    }

    $password = password_hash($rawPassword, PASSWORD_BCRYPT);
    $supabaseSync = rc_supabase_upsert_user([
        'email' => $email,
        'name' => $name,
        'password' => $password,
        'role' => $role,
        'auth_provider' => 'local',
        'email_verified' => false,
    ]);
    if (!$supabaseSync['ok']) {
        return ['ok' => false, 'error' => 'Something went wrong. Try again!'];
    }

    $created = rc_mig_get_user_auth_by_email($conn, $email);
    if (!$created) {
        return ['ok' => false, 'error' => 'Failed to load newly created user profile.'];
    }

    return [
        'ok' => true,
        'user_id' => (int) ($created['id'] ?? 0),
        'name' => (string) ($created['name'] ?? $name),
        'email' => (string) ($created['email'] ?? $email),
        'role' => (string) ($created['role'] ?? $role),
    ];
}

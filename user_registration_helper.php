<?php

require_once __DIR__ . '/supabase_rest.php';

function rc_register_user(mysqli $conn, string $name, string $email, string $rawPassword, string $role): array {
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

    $check = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    if (!$check) {
        return ['ok' => false, 'error' => 'Something went wrong. Try again!'];
    }

    $check->bind_param('s', $email);
    if (!$check->execute()) {
        return ['ok' => false, 'error' => 'Something went wrong. Try again!'];
    }

    if ($check->get_result()->num_rows > 0) {
        return ['ok' => false, 'error' => 'Email already registered. Please login.'];
    }

    $password = password_hash($rawPassword, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Something went wrong. Try again!'];
    }

    $stmt->bind_param('ssss', $name, $email, $password, $role);
    if (!$stmt->execute()) {
        return ['ok' => false, 'error' => 'Something went wrong. Try again!'];
    }

    $supabaseSync = rc_supabase_upsert_user([
        'email' => $email,
        'name' => $name,
        'password' => $password,
        'role' => $role,
        'auth_provider' => 'local',
        'email_verified' => false,
    ]);
    if (!$supabaseSync['ok']) {
        error_log('Supabase user sync warning: ' . ($supabaseSync['error'] ?? 'unknown error'));
    }

    return [
        'ok' => true,
        'user_id' => (int) $conn->insert_id,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ];
}

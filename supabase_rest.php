<?php

require_once __DIR__ . "/supabase_config.php";

/**
 * Upsert a user record into Supabase via PostgREST.
 * This is the canonical server-side auth profile sync path.
 */
function rc_supabase_upsert_user(array $user): array {
    if (!rc_supabase_rest_enabled()) {
        return ['ok' => false, 'error' => 'Supabase REST integration is disabled'];
    }

    $cfg = rc_supabase_config();
    $url = rtrim((string) ($cfg['url'] ?? ''), '/');
    $key = (string) ($cfg['restKey'] ?? '');

    if ($url === '' || $key === '') {
        return ['ok' => false, 'error' => 'Supabase config is missing'];
    }

    $endpoint = $url . '/rest/v1/users?on_conflict=email';

    $firebaseUid = isset($user['firebase_uid']) ? trim((string) $user['firebase_uid']) : '';
    $avatarUrl = isset($user['avatar_url']) ? trim((string) $user['avatar_url']) : '';

    $payload = [
        [
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['name'] ?? 'User'),
            'password' => (string) ($user['password'] ?? ''),
            'role' => (string) ($user['role'] ?? 'renter'),
            // Keep nullable fields as null to satisfy unique constraints across many users.
            'firebase_uid' => $firebaseUid !== '' ? $firebaseUid : null,
            'auth_provider' => (string) ($user['auth_provider'] ?? 'firebase_google'),
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            'email_verified' => (bool) ($user['email_verified'] ?? false),
        ],
    ];

    if ($payload[0]['email'] === '' || $payload[0]['password'] === '') {
        return ['ok' => false, 'error' => 'Missing required user payload'];
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Prefer: return=minimal,resolution=merge-duplicates',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Supabase request failed: ' . $error];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'Supabase upsert failed', 'http_code' => $httpCode, 'response' => $raw];
    }

    return ['ok' => true];
}

<?php

function rc_firebase_config(): array {
    return [
        'apiKey' => getenv('FIREBASE_API_KEY') ?: '',
        'authDomain' => getenv('FIREBASE_AUTH_DOMAIN') ?: '',
        'projectId' => getenv('FIREBASE_PROJECT_ID') ?: '',
        'storageBucket' => getenv('FIREBASE_STORAGE_BUCKET') ?: '',
        'messagingSenderId' => getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '',
        'appId' => getenv('FIREBASE_APP_ID') ?: '',
        // Optional strict audience validation for Firebase/Google ID tokens.
        'webClientId' => getenv('FIREBASE_WEB_CLIENT_ID') ?: '',
    ];
}

function rc_verify_firebase_id_token(string $idToken): array {
    if ($idToken === '') {
        return ['ok' => false, 'error' => 'Missing Firebase ID token.'];
    }

    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Token verification request failed: ' . $err];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => 'Invalid Firebase token.'];
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return ['ok' => false, 'error' => 'Invalid token verification response.'];
    }

    $cfg = rc_firebase_config();
    if ($cfg['webClientId'] !== '' && ($payload['aud'] ?? '') !== $cfg['webClientId']) {
        return ['ok' => false, 'error' => 'Token audience mismatch.'];
    }

    if (($payload['email'] ?? '') === '') {
        return ['ok' => false, 'error' => 'Verified token is missing email.'];
    }

    return [
        'ok' => true,
        'uid' => (string) ($payload['sub'] ?? ''),
        'email' => (string) $payload['email'],
        'name' => (string) ($payload['name'] ?? 'User'),
        'picture' => (string) ($payload['picture'] ?? ''),
        'email_verified' => (string) ($payload['email_verified'] ?? '') === 'true' || ($payload['email_verified'] ?? false) === true,
        'raw' => $payload,
    ];
}

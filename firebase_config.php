<?php

function rc_firebase_config(): array {
    $envValue = function_exists('rc_env_value') ? 'rc_env_value' : static function (string $name, string $default = ''): string {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string) $_ENV[$name];
        }

        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string) $_SERVER[$name];
        }

        return $default;
    };

    return [
        'apiKey' => $envValue('FIREBASE_API_KEY'),
        'authDomain' => $envValue('FIREBASE_AUTH_DOMAIN'),
        'projectId' => $envValue('FIREBASE_PROJECT_ID'),
        'storageBucket' => $envValue('FIREBASE_STORAGE_BUCKET'),
        'messagingSenderId' => $envValue('FIREBASE_MESSAGING_SENDER_ID'),
        'appId' => $envValue('FIREBASE_APP_ID'),
        // Optional strict audience validation for Firebase/Google ID tokens.
        'webClientId' => $envValue('FIREBASE_WEB_CLIENT_ID'),
    ];
}

function rc_firebase_auth_configured(): bool {
    $cfg = rc_firebase_config();
    return ($cfg['apiKey'] ?? '') !== ''
        && ($cfg['authDomain'] ?? '') !== ''
        && ($cfg['projectId'] ?? '') !== ''
        && ($cfg['appId'] ?? '') !== '';
}

function rc_verify_firebase_id_token(string $idToken): array {
    if ($idToken === '') {
        return ['ok' => false, 'error' => 'Missing Firebase ID token.'];
    }

    $cfg = rc_firebase_config();
    if (($cfg['apiKey'] ?? '') === '') {
        return ['ok' => false, 'error' => 'Firebase API key is missing on the server.'];
    }

    // Primary verification path for Firebase ID tokens.
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode((string) $cfg['apiKey']);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Token verification request failed: ' . $err];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $payload = json_decode($raw, true);
        $user = is_array($payload) && isset($payload['users'][0]) && is_array($payload['users'][0])
            ? $payload['users'][0]
            : null;

        if (!is_array($user) || ($user['email'] ?? '') === '') {
            return ['ok' => false, 'error' => 'Invalid Firebase token payload.'];
        }

        return [
            'ok' => true,
            'uid' => (string) ($user['localId'] ?? ''),
            'email' => (string) $user['email'],
            'name' => (string) ($user['displayName'] ?? 'User'),
            'picture' => (string) ($user['photoUrl'] ?? ''),
            'email_verified' => (bool) ($user['emailVerified'] ?? false),
            'raw' => $user,
        ];
    }

    // Fallback path for compatible OIDC tokens.
    $oidcUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
    $ch = curl_init($oidcUrl);
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

    if (($cfg['webClientId'] ?? '') !== '' && ($payload['aud'] ?? '') !== $cfg['webClientId']) {
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

<?php
session_start();
include "app_init.php";
require_once "firebase_config.php";
require_once "supabase_rest.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!rc_firebase_auth_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Firebase auth is not configured on the server']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

$idToken = trim((string) ($input['idToken'] ?? ''));
$requestedRole = trim((string) ($input['role'] ?? 'renter'));
if (!in_array($requestedRole, ['renter', 'landlord'], true)) {
    $requestedRole = 'renter';
}

$verified = rc_verify_firebase_id_token($idToken);
if (!$verified['ok']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $verified['error']]);
    exit;
}

$email = $verified['email'];
$name = $verified['name'] ?: 'User';
$uid = $verified['uid'];
$picture = $verified['picture'];
$emailVerified = $verified['email_verified'] ? 1 : 0;

$existingUser = rc_mig_get_user_auth_by_email($conn, $email);
$role = $existingUser ? (string) ($existingUser['role'] ?? 'renter') : $requestedRole;
$passwordHash = $existingUser
    ? (string) ($existingUser['password'] ?? '')
    : password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

$supabaseSync = rc_supabase_upsert_user([
    'email' => $email,
    'name' => $name,
    'password' => $passwordHash,
    'role' => $role,
    'firebase_uid' => $uid,
    'auth_provider' => 'firebase_google',
    'avatar_url' => $picture,
    'email_verified' => (bool) $emailVerified,
]);
if (!$supabaseSync['ok']) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to sync user profile to Supabase',
        'details' => (string) ($supabaseSync['error'] ?? 'unknown error'),
    ]);
    exit;
}

$resolvedUser = rc_mig_get_user_auth_by_email($conn, $email);
if (!$resolvedUser || empty($resolvedUser['id'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load user after sync']);
    exit;
}

$userId = (int) $resolvedUser['id'];
$name = (string) ($resolvedUser['name'] ?? $name);
$role = (string) ($resolvedUser['role'] ?? $role);

$_SESSION['user_id'] = $userId;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;

$redirect = 'renter_dashboard.php';
if ($role === 'landlord') {
    $redirect = 'landlord_dashboard.php';
} elseif ($role === 'super_admin') {
    $redirect = 'super_admin_dashboard.php';
}

echo json_encode([
    'success' => true,
    'redirect' => $redirect,
    'role' => $role,
]);

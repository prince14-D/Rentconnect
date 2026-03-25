<?php
session_start();
include "db.php";
require_once "firebase_config.php";
require_once "supabase_rest.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
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

$stmt = $conn->prepare("SELECT id, name, role, password FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    $userId = (int) $user['id'];
    $role = (string) $user['role'];
    $passwordHash = (string) ($user['password'] ?? '');

    // Best-effort optional update for Firebase metadata if columns exist.
    $update = $conn->prepare("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?");
    if ($update) {
        $update->bind_param('si', $name, $userId);
        $update->execute();
        $update->close();
    }

    $updateFirebase = $conn->prepare("UPDATE users SET firebase_uid = ?, auth_provider = 'firebase_google', avatar_url = ?, email_verified = ? WHERE id = ?");
    if ($updateFirebase) {
        $updateFirebase->bind_param('ssii', $uid, $picture, $emailVerified, $userId);
        $updateFirebase->execute();
        $updateFirebase->close();
    }
} else {
    $role = $requestedRole;
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $passwordHash = $randomPassword;

    $insert = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$insert) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        exit;
    }

    $insert->bind_param('ssss', $name, $email, $randomPassword, $role);
    if (!$insert->execute()) {
        $insert->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        exit;
    }

    $userId = (int) $conn->insert_id;
    $insert->close();

    // Best-effort optional update for Firebase metadata if columns exist.
    $updateFirebase = $conn->prepare("UPDATE users SET firebase_uid = ?, auth_provider = 'firebase_google', avatar_url = ?, email_verified = ? WHERE id = ?");
    if ($updateFirebase) {
        $updateFirebase->bind_param('ssii', $uid, $picture, $emailVerified, $userId);
        $updateFirebase->execute();
        $updateFirebase->close();
    }
}

// Best-effort Supabase mirror for migrated paths.
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
    error_log('Supabase user sync warning: ' . ($supabaseSync['error'] ?? 'unknown error'));
}

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

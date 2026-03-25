<?php

require_once __DIR__ . '/supabase_config.php';

function rc_mig_supabase_should_try(): bool {
    return getenv('SUPABASE_USE_REST') === '1';
}

function rc_mig_supabase_key(): string {
    $serviceKey = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');
    if ($serviceKey !== '') {
        return $serviceKey;
    }

    $cfg = rc_supabase_config();
    return (string) ($cfg['publishableKey'] ?? '');
}

function rc_mig_supabase_request(string $method, string $resource, array $query = [], ?array $payload = null): array {
    $cfg = rc_supabase_config();
    $url = rtrim((string) ($cfg['url'] ?? ''), '/');
    $key = rc_mig_supabase_key();

    if ($url === '' || $key === '') {
        return ['ok' => false, 'error' => 'Supabase config missing'];
    }

    $endpoint = $url . '/rest/v1/' . ltrim($resource, '/');
    if (!empty($query)) {
        $endpoint .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ];

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    if (strtoupper($method) === 'POST') {
        $headers[] = 'Prefer: return=representation';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => $error];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'http' => $httpCode, 'raw' => $raw];
    }

    $decoded = json_decode($raw, true);
    return ['ok' => true, 'data' => is_array($decoded) ? $decoded : []];
}

function rc_mig_get_property_by_id(mysqli $conn, int $propertyId, bool $approvedOnly = false): ?array {
    if ($propertyId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $query = [
            'select' => 'id,title,location,price,bedrooms,bathrooms,description,created_at,photo,owner_id,landlord_id,booking_status,status,users!owner_id(name,email)',
            'id' => 'eq.' . $propertyId,
            'limit' => '1',
        ];
        if ($approvedOnly) {
            $query['status'] = 'eq.approved';
        }
        $res = rc_mig_supabase_request('GET', 'properties', $query);
        if ($res['ok'] && !empty($res['data'][0])) {
            $row = $res['data'][0];
            $row['owner_name'] = (string) ($row['users']['name'] ?? '');
            $row['owner_email'] = (string) ($row['users']['email'] ?? '');
            $row['landlord_name'] = $row['owner_name'];
            $row['landlord_email'] = $row['owner_email'];
            return $row;
        }
    }

    $sql = "SELECT p.*, u.name AS owner_name, u.email AS owner_email, u.name AS landlord_name, u.email AS landlord_email
            FROM properties p
            JOIN users u ON p.owner_id = u.id
            WHERE p.id = ?";
    if ($approvedOnly) {
        $sql .= " AND p.status = 'approved'";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_get_property_image_ids(mysqli $conn, int $propertyId): array {
    if ($propertyId <= 0) {
        return [];
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'property_images', [
            'select' => 'id',
            'property_id' => 'eq.' . $propertyId,
            'order' => 'id.asc',
        ]);
        if ($res['ok']) {
            return $res['data'];
        }
    }

    $stmt = $conn->prepare('SELECT id FROM property_images WHERE property_id = ? ORDER BY id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_landlord_requests_grouped(mysqli $conn, int $landlordId): array {
    $stmt = $conn->prepare(
        "SELECT
            p.id AS property_id,
            p.title AS property_title,
            p.price,
            p.location,
            GROUP_CONCAT(CONCAT(u.name,'|',u.id,'|',r.status,'|',r.id) SEPARATOR '||') AS renters
        FROM properties p
        JOIN requests r ON r.property_id = p.id
        JOIN users u ON r.user_id = u.id
        WHERE (p.owner_id = ? OR p.landlord_id = ?)
        GROUP BY p.id
        ORDER BY p.id DESC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $landlordId, $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_update_request_status_for_landlord(mysqli $conn, int $requestId, int $landlordId, string $status): bool {
    $stmt = $conn->prepare(
        "UPDATE requests r
         JOIN properties p ON r.property_id = p.id
         SET r.status = ?, r.updated_at = NOW()
         WHERE r.id = ? AND (p.owner_id = ? OR p.landlord_id = ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siii', $status, $requestId, $landlordId, $landlordId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function rc_mig_get_request_detail_for_landlord(mysqli $conn, int $requestId, int $landlordId): ?array {
    $stmt = $conn->prepare(
        "SELECT r.id, r.status, r.created_at,
                u.name AS renter_name, u.email AS renter_email,
                p.title AS property_title
         FROM requests r
         JOIN users u ON r.user_id = u.id
         JOIN properties p ON r.property_id = p.id
         WHERE r.id = ? AND (p.owner_id = ? OR p.landlord_id = ?)
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iii', $requestId, $landlordId, $landlordId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_get_payment_detail_for_renter(mysqli $conn, int $paymentId, int $renterId): ?array {
    $stmt = $conn->prepare(
        "SELECT p.*, b.property_id, pr.title, u.name AS landlord_name
         FROM payments p
         JOIN bookings b ON p.booking_id = b.id
         JOIN properties pr ON b.property_id = pr.id
         JOIN users u ON p.landlord_id = u.id
         WHERE p.id = ? AND p.renter_id = ? LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $paymentId, $renterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_get_booking_detail_for_renter(mysqli $conn, int $bookingId, int $renterId): ?array {
    $stmt = $conn->prepare(
        "SELECT b.*, p.title, p.monthly_rent, p.price, p.landlord_id, p.owner_id, u.name AS landlord_name, u.email AS landlord_email
         FROM bookings b
         JOIN properties p ON b.property_id = p.id
         JOIN users u ON b.landlord_id = u.id
         WHERE b.id = ? AND b.renter_id = ? LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $bookingId, $renterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_get_open_payment_for_booking(mysqli $conn, int $bookingId, int $renterId): ?array {
    $stmt = $conn->prepare(
        "SELECT * FROM payments
         WHERE booking_id = ? AND renter_id = ? AND status IN ('pending','failed','draft')
         ORDER BY payment_month ASC
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $bookingId, $renterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_create_payment_from_booking(mysqli $conn, int $bookingId, int $renterId, float $amount, string $paymentMonth, string $method, string $status): int {
    $stmt = $conn->prepare(
        "INSERT INTO payments (booking_id, renter_id, landlord_id, property_id, amount, payment_month, payment_method, status, created_at)
         SELECT ?, ?, landlord_id, property_id, ?, ?, ?, ?, NOW()
         FROM bookings WHERE id = ? AND renter_id = ?"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iidsssii', $bookingId, $renterId, $amount, $paymentMonth, $method, $status, $bookingId, $renterId);
    $ok = $stmt->execute();
    $insertId = $ok ? (int) $conn->insert_id : 0;
    $stmt->close();
    return $insertId;
}

function rc_mig_verify_booking_access(mysqli $conn, int $bookingId, int $renterId): bool {
    $stmt = $conn->prepare('SELECT id FROM bookings WHERE id = ? AND renter_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $bookingId, $renterId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function rc_mig_delete_payment(mysqli $conn, int $paymentId): bool {
    $stmt = $conn->prepare('DELETE FROM payments WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $paymentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_update_payment_status_reference(mysqli $conn, int $paymentId, string $status, ?string $reference = null): bool {
    if ($reference !== null) {
        $stmt = $conn->prepare('UPDATE payments SET reference_number = ?, status = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssi', $reference, $status, $paymentId);
    } else {
        $stmt = $conn->prepare('UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $status, $paymentId);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_user_by_id(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_get_property_title_by_id(mysqli $conn, int $propertyId): ?array {
    $stmt = $conn->prepare('SELECT id, title FROM properties WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_payment_exists_for_month(mysqli $conn, int $bookingId, string $paymentMonth): bool {
    $stmt = $conn->prepare('SELECT id FROM payments WHERE booking_id = ? AND payment_month = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $bookingId, $paymentMonth);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function rc_mig_get_active_bookings_with_stats(mysqli $conn, int $renterId): array {
    $stmt = $conn->prepare(
        "SELECT
            b.id, b.property_id, b.monthly_rent, b.move_in_date, b.status,
            p.title, p.location,
            u.name AS landlord_name,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND status IN ('approved','confirmed')) AS total_paid,
            (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status IN ('pending','submitted','draft')) AS pending_payments
         FROM bookings b
         JOIN properties p ON b.property_id = p.id
         JOIN users u ON b.landlord_id = u.id
         WHERE b.renter_id = ? AND b.status = 'active'
         ORDER BY b.created_at DESC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $renterId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_payment_history(mysqli $conn, int $renterId, int $limit = 20): array {
    $stmt = $conn->prepare(
        "SELECT
            py.*,
            p.title AS property_title,
            u.name AS landlord_name
         FROM payments py
         JOIN bookings b ON py.booking_id = b.id
         JOIN properties p ON py.property_id = p.id
         JOIN users u ON py.landlord_id = u.id
         WHERE py.renter_id = ?
         ORDER BY py.created_at DESC
         LIMIT ?"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $renterId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_user_auth_by_email(mysqli $conn, string $email): ?array {
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'users', [
            'select' => 'id,name,password,role,email',
            'email' => 'eq.' . $email,
            'limit' => '1',
        ]);
        if ($res['ok'] && !empty($res['data'][0])) {
            return $res['data'][0];
        }
    }

    $stmt = $conn->prepare('SELECT id, name, password, role, email FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_prune_password_reset_tokens(mysqli $conn, int $seconds): bool {
    $seconds = max(60, $seconds);
    $stmt = $conn->prepare('DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $seconds);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_store_password_reset_token(mysqli $conn, int $userId, string $token): bool {
    if ($userId <= 0 || $token === '') {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO password_resets (user_id, token) VALUES (?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $userId, $token);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_password_reset_by_token(mysqli $conn, string $token): ?array {
    if ($token === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT user_id, created_at FROM password_resets WHERE token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_delete_password_reset_token(mysqli $conn, string $token): bool {
    if ($token === '') {
        return false;
    }

    $stmt = $conn->prepare('DELETE FROM password_resets WHERE token = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $token);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_update_user_password(mysqli $conn, int $userId, string $passwordHash): bool {
    if ($userId <= 0 || $passwordHash === '') {
        return false;
    }

    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $passwordHash, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

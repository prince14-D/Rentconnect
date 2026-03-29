<?php

require_once __DIR__ . '/supabase_config.php';

function rc_mig_supabase_should_try(): bool {
    return rc_supabase_rest_enabled();
}

function rc_mig_supabase_key(): string {
    $cfg = rc_supabase_config();
    return (string) ($cfg['restKey'] ?? '');
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

function rc_mig_get_property_by_id($conn, int $propertyId, bool $approvedOnly = false): ?array {
    if ($propertyId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $query = [
            'select' => 'id,title,location,price,bedrooms,bathrooms,description,created_at,image,owner_id,landlord_id,status',
            'id' => 'eq.' . $propertyId,
            'limit' => '1',
        ];
        if ($approvedOnly) {
            $query['status'] = 'eq.approved';
        }
        $res = rc_mig_supabase_request('GET', 'properties', $query);
        if ($res['ok'] && !empty($res['data'][0])) {
            $row = $res['data'][0];
            $ownerId = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
            $owner = $ownerId > 0 ? rc_mig_get_user_by_id($conn, $ownerId) : null;
            $row['owner_name'] = (string) ($owner['name'] ?? '');
            $row['owner_email'] = (string) ($owner['email'] ?? '');
            $row['landlord_name'] = $row['owner_name'];
            $row['landlord_email'] = $row['owner_email'];
            if (!isset($row['photo']) && isset($row['image'])) {
                $row['photo'] = (string) $row['image'];
            }
            if (!isset($row['booking_status'])) {
                $row['booking_status'] = 'available';
            }
            return $row;
        }
    }

    $sqlPrimary = "SELECT p.*, u.name AS owner_name, u.email AS owner_email, u.name AS landlord_name, u.email AS landlord_email
                   FROM properties p
                   LEFT JOIN users u ON u.id = COALESCE(NULLIF(p.owner_id, 0), NULLIF(p.landlord_id, 0))
                   WHERE p.id = ?";
    if ($approvedOnly) {
        $sqlPrimary .= " AND p.status = 'approved'";
    }

    $stmt = $conn->prepare($sqlPrimary);
    if (!$stmt) {
        $sqlFallback = "SELECT p.*, u.name AS owner_name, u.email AS owner_email, u.name AS landlord_name, u.email AS landlord_email
                        FROM properties p
                        LEFT JOIN users u ON p.landlord_id = u.id
                        WHERE p.id = ?";
        if ($approvedOnly) {
            $sqlFallback .= " AND p.status = 'approved'";
        }
        $stmt = $conn->prepare($sqlFallback);
        if (!$stmt) {
            return null;
        }
    }

    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if ($row && !isset($row['booking_status'])) {
        $row['booking_status'] = 'available';
    }
    return $row;
}

function rc_mig_get_property_image_ids($conn, int $propertyId): array {
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

function rc_mig_decode_binary_field($value): string {
    if (is_string($value)) {
        // Postgres bytea often comes back as hex, prefixed with either "\\x" or "\x".
        if (strncmp($value, '\\x', 2) === 0) {
            $hex = substr($value, 2);
            if ($hex !== '' && ctype_xdigit($hex)) {
                $decoded = hex2bin($hex);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }
        if (strncmp($value, '\\\\x', 3) === 0) {
            $hex = substr($value, 3);
            if ($hex !== '' && ctype_xdigit($hex)) {
                $decoded = hex2bin($hex);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }
        return $value;
    }

    if (is_resource($value)) {
        $data = stream_get_contents($value);
        return is_string($data) ? $data : '';
    }

    return '';
}

function rc_mig_get_property_image_by_id($conn, int $imageId): ?array {
    if ($imageId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'property_images', [
            'select' => 'id,image,mime_type',
            'id' => 'eq.' . $imageId,
            'limit' => '1',
        ]);
        if (!empty($res['ok']) && !empty($res['data'][0])) {
            $row = $res['data'][0];
            $binary = rc_mig_decode_binary_field($row['image'] ?? null);
            if ($binary === '') {
                return null;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'image' => $binary,
                'mime_type' => (string) ($row['mime_type'] ?? ''),
            ];
        }
        return null;
    }

    $stmt = $conn->prepare('SELECT id, image, mime_type FROM property_images WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $binary = rc_mig_decode_binary_field($row['image'] ?? null);
    if ($binary === '') {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'image' => $binary,
        'mime_type' => (string) ($row['mime_type'] ?? ''),
    ];
}

function rc_mig_get_property_primary_image($conn, int $propertyId): ?array {
    if ($propertyId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'property_images', [
            'select' => 'id,image,mime_type',
            'property_id' => 'eq.' . $propertyId,
            'order' => 'id.asc',
            'limit' => '1',
        ]);
        if (!empty($res['ok']) && !empty($res['data'][0])) {
            $row = $res['data'][0];
            $binary = rc_mig_decode_binary_field($row['image'] ?? null);
            if ($binary !== '') {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'image' => $binary,
                    'mime_type' => (string) ($row['mime_type'] ?? ''),
                ];
            }
        }
        return null;
    }

    $stmt = $conn->prepare('SELECT id, image, mime_type FROM property_images WHERE property_id = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $binary = rc_mig_decode_binary_field($row['image'] ?? null);
    if ($binary === '') {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'image' => $binary,
        'mime_type' => (string) ($row['mime_type'] ?? ''),
    ];
}

function rc_mig_create_property($conn, int $landlordId, array $property): array {
    $title = trim((string) ($property['title'] ?? ''));
    $location = trim((string) ($property['location'] ?? ''));
    $contact = trim((string) ($property['contact'] ?? ''));
    $description = trim((string) ($property['description'] ?? ''));
    $status = trim((string) ($property['status'] ?? 'pending'));
    $image = trim((string) ($property['image'] ?? ''));
    $price = (float) ($property['price'] ?? 0);
    $bedrooms = (int) ($property['bedrooms'] ?? 0);
    $bathrooms = (int) ($property['bathrooms'] ?? 0);

    if ($landlordId <= 0 || $title === '') {
        return ['ok' => false, 'error' => 'Missing required property fields'];
    }

    if (rc_mig_supabase_should_try()) {
        $payload = [
            'landlord_id' => $landlordId,
            'owner_id' => $landlordId,
            'title' => $title,
            'location' => $location,
            'price' => $price,
            'contact' => $contact,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'description' => $description,
            'status' => $status === '' ? 'pending' : $status,
        ];
        if ($image !== '') {
            $payload['image'] = $image;
        }

        $res = rc_mig_supabase_request('POST', 'properties?select=id', [], $payload);
        if (!empty($res['ok']) && !empty($res['data'][0]['id'])) {
            return ['ok' => true, 'id' => (int) $res['data'][0]['id']];
        }
        return ['ok' => false, 'error' => (string) ($res['error'] ?? $res['raw'] ?? 'Failed to create property')];
    }

    $stmt = $conn->prepare(
        'INSERT INTO properties (landlord_id, owner_id, title, location, price, contact, bedrooms, bathrooms, description, status, image, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => (string) ($conn->error ?? 'prepare failed')];
    }

    $stmt->bind_param('iissdsiisss', $landlordId, $landlordId, $title, $location, $price, $contact, $bedrooms, $bathrooms, $description, $status, $image);
    $ok = $stmt->execute();
    $insertId = (int) ($stmt->insert_id ?? 0);
    $error = (string) ($stmt->error ?? 'insert failed');
    $stmt->close();

    return $ok
        ? ['ok' => true, 'id' => $insertId]
        : ['ok' => false, 'error' => $error];
}

function rc_mig_add_property_image($conn, int $propertyId, string $imageData, string $mimeType): bool {
    if ($propertyId <= 0 || $imageData === '' || $mimeType === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $payload = [
            'property_id' => $propertyId,
            // PostgREST accepts bytea as hex string prefixed with "\\x".
            'image' => '\\x' . bin2hex($imageData),
            'mime_type' => $mimeType,
        ];
        $res = rc_mig_supabase_request('POST', 'property_images', [], $payload);
        return (bool) ($res['ok'] ?? false);
    }

    $stmt = $conn->prepare('INSERT INTO property_images (property_id, image, mime_type) VALUES (?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $null = null;
    $stmt->bind_param('ibs', $propertyId, $null, $mimeType);
    $stmt->send_long_data(1, $imageData);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_property_for_landlord($conn, int $propertyId, int $landlordId): ?array {
    if ($propertyId <= 0 || $landlordId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,location,price,contact,bedrooms,bathrooms,description,owner_id,landlord_id,status,created_at',
            'id' => 'eq.' . $propertyId,
            'or' => '(owner_id.eq.' . $landlordId . ',landlord_id.eq.' . $landlordId . ')',
            'limit' => '1',
        ]);
        if (!empty($res['ok']) && !empty($res['data'][0])) {
            return $res['data'][0];
        }
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM properties WHERE id = ? AND (owner_id = ? OR landlord_id = ?) LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iii', $propertyId, $landlordId, $landlordId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function rc_mig_update_property_for_landlord($conn, int $propertyId, int $landlordId, array $fields): array {
    if ($propertyId <= 0 || $landlordId <= 0) {
        return ['ok' => false, 'error' => 'Invalid property update request'];
    }

    $title = trim((string) ($fields['title'] ?? ''));
    $location = trim((string) ($fields['location'] ?? ''));
    $price = (float) ($fields['price'] ?? 0);
    $contact = trim((string) ($fields['contact'] ?? ''));
    $bedrooms = (int) ($fields['bedrooms'] ?? 0);
    $bathrooms = (int) ($fields['bathrooms'] ?? 0);
    $description = trim((string) ($fields['description'] ?? ''));

    if ($title === '' || $location === '' || $contact === '') {
        return ['ok' => false, 'error' => 'Title, location, and contact are required'];
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('PATCH', 'properties', [
            'id' => 'eq.' . $propertyId,
            'or' => '(owner_id.eq.' . $landlordId . ',landlord_id.eq.' . $landlordId . ')',
        ], [
            'title' => $title,
            'location' => $location,
            'price' => $price,
            'contact' => $contact,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'description' => $description,
            'updated_at' => gmdate('c'),
        ]);

        if (!empty($res['ok'])) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => (string) ($res['error'] ?? $res['raw'] ?? 'Failed to update property')];
    }

    $stmt = $conn->prepare(
        'UPDATE properties
         SET title = ?, location = ?, price = ?, contact = ?, bedrooms = ?, bathrooms = ?, description = ?
         WHERE id = ? AND (owner_id = ? OR landlord_id = ?)'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => (string) ($conn->error ?? 'prepare failed')];
    }

    $stmt->bind_param('ssdsiisiii', $title, $location, $price, $contact, $bedrooms, $bathrooms, $description, $propertyId, $landlordId, $landlordId);
    $ok = $stmt->execute();
    $error = (string) ($stmt->error ?? 'update failed');
    $stmt->close();

    return $ok ? ['ok' => true] : ['ok' => false, 'error' => $error];
}

function rc_mig_get_landlord_requests_grouped($conn, int $landlordId): array {
    if ($landlordId <= 0) {
        return [];
    }

    $grouped = [];

    if (rc_mig_supabase_should_try()) {
        $propRes = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,price,location,owner_id,landlord_id',
            'or' => '(owner_id.eq.' . $landlordId . ',landlord_id.eq.' . $landlordId . ')',
            'order' => 'id.desc',
            'limit' => '2000',
        ]);

        if (empty($propRes['ok']) || !is_array($propRes['data']) || empty($propRes['data'])) {
            return [];
        }

        $propertyMap = [];
        $propertyIds = [];
        foreach ($propRes['data'] as $property) {
            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId <= 0) {
                continue;
            }
            $propertyIds[$propertyId] = true;
            $propertyMap[$propertyId] = [
                'property_id' => $propertyId,
                'property_title' => (string) ($property['title'] ?? 'Property'),
                'price' => (float) ($property['price'] ?? 0),
                'location' => (string) ($property['location'] ?? ''),
                'renters_list' => [],
            ];
        }

        if (empty($propertyIds)) {
            return [];
        }

        $requestRes = rc_mig_supabase_request('GET', 'requests', [
            'select' => 'id,status,created_at,user_id,property_id',
            'property_id' => 'in.(' . implode(',', array_keys($propertyIds)) . ')',
            'order' => 'created_at.desc',
            'limit' => '5000',
        ]);

        if (empty($requestRes['ok']) || !is_array($requestRes['data']) || empty($requestRes['data'])) {
            return array_values($propertyMap);
        }

        $userIds = [];
        foreach ($requestRes['data'] as $request) {
            $uid = (int) ($request['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = true;
            }
        }

        $userMap = [];
        if (!empty($userIds)) {
            $usersRes = rc_mig_supabase_request('GET', 'users', [
                'select' => 'id,name,email,phone,avatar_url',
                'id' => 'in.(' . implode(',', array_keys($userIds)) . ')',
                'limit' => (string) count($userIds),
            ]);

            if (!empty($usersRes['ok']) && is_array($usersRes['data'])) {
                foreach ($usersRes['data'] as $user) {
                    $uid = (int) ($user['id'] ?? 0);
                    if ($uid <= 0) {
                        continue;
                    }
                    $userMap[$uid] = [
                        'name' => (string) ($user['name'] ?? 'Renter'),
                        'email' => (string) ($user['email'] ?? ''),
                        'phone' => (string) ($user['phone'] ?? ''),
                        'profile_pic' => (string) ($user['avatar_url'] ?? ''),
                    ];
                }
            }
        }

        foreach ($requestRes['data'] as $request) {
            $propertyId = (int) ($request['property_id'] ?? 0);
            $renterId = (int) ($request['user_id'] ?? 0);
            if ($propertyId <= 0 || !isset($propertyMap[$propertyId])) {
                continue;
            }

            $profile = $userMap[$renterId] ?? ['name' => 'Renter', 'email' => '', 'phone' => '', 'profile_pic' => ''];
            $propertyMap[$propertyId]['renters_list'][] = [
                'name' => $profile['name'],
                'id' => $renterId,
                'status' => (string) ($request['status'] ?? 'pending'),
                'request_id' => (int) ($request['id'] ?? 0),
                'email' => $profile['email'],
                'phone' => $profile['phone'],
                'profile_pic' => $profile['profile_pic'],
                'requested_at' => (string) ($request['created_at'] ?? ''),
            ];
        }

        $grouped = array_values($propertyMap);
    } else {
        $stmt = $conn->prepare(
            "SELECT
                p.id AS property_id,
                p.title AS property_title,
                p.price,
                p.location,
                r.id AS request_id,
                r.status,
                r.created_at,
                u.id AS renter_id,
                u.name AS renter_name,
                u.email AS renter_email,
                u.phone AS renter_phone,
                u.profile_pic,
                u.avatar_url
            FROM properties p
            JOIN requests r ON r.property_id = p.id
            JOIN users u ON r.user_id = u.id
            WHERE (p.owner_id = ? OR p.landlord_id = ?)
            ORDER BY p.id DESC, r.created_at DESC"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $landlordId, $landlordId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            if ($propertyId <= 0) {
                continue;
            }

            if (!isset($grouped[$propertyId])) {
                $grouped[$propertyId] = [
                    'property_id' => $propertyId,
                    'property_title' => (string) ($row['property_title'] ?? 'Property'),
                    'price' => (float) ($row['price'] ?? 0),
                    'location' => (string) ($row['location'] ?? ''),
                    'renters_list' => [],
                ];
            }

            $grouped[$propertyId]['renters_list'][] = [
                'name' => (string) ($row['renter_name'] ?? 'Renter'),
                'id' => (int) ($row['renter_id'] ?? 0),
                'status' => (string) ($row['status'] ?? 'pending'),
                'request_id' => (int) ($row['request_id'] ?? 0),
                'email' => (string) ($row['renter_email'] ?? ''),
                'phone' => (string) ($row['renter_phone'] ?? ''),
                'profile_pic' => (string) (($row['profile_pic'] ?? '') ?: ($row['avatar_url'] ?? '')),
                'requested_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        $grouped = array_values($grouped);
    }

    foreach ($grouped as &$row) {
        $parts = [];
        foreach ((array) ($row['renters_list'] ?? []) as $renter) {
            $safeName = str_replace(['|', '||'], ['/', '/'], (string) ($renter['name'] ?? ''));
            $safeEmail = str_replace(['|', '||'], ['/', '/'], (string) ($renter['email'] ?? ''));
            $safePhone = str_replace(['|', '||'], ['/', '/'], (string) ($renter['phone'] ?? ''));
            $safePic = str_replace(['|', '||'], ['/', '/'], (string) ($renter['profile_pic'] ?? ''));
            $parts[] = implode('|', [
                $safeName,
                (int) ($renter['id'] ?? 0),
                (string) ($renter['status'] ?? 'pending'),
                (int) ($renter['request_id'] ?? 0),
                $safeEmail,
                $safePhone,
                $safePic,
            ]);
        }
        $row['renters'] = implode('||', $parts);
    }
    unset($row);

    return $grouped;
}

function rc_mig_update_request_status_for_landlord($conn, int $requestId, int $landlordId, string $status): bool {
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

function rc_mig_get_request_detail_for_landlord($conn, int $requestId, int $landlordId): ?array {
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

function rc_mig_get_payment_detail_for_renter($conn, int $paymentId, int $renterId): ?array {
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

function rc_mig_get_booking_detail_for_renter($conn, int $bookingId, int $renterId): ?array {
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

function rc_mig_get_open_payment_for_booking($conn, int $bookingId, int $renterId): ?array {
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

function rc_mig_create_payment_from_booking($conn, int $bookingId, int $renterId, float $amount, string $paymentMonth, string $method, string $status): int {
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

function rc_mig_verify_booking_access($conn, int $bookingId, int $renterId): bool {
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

function rc_mig_delete_payment($conn, int $paymentId): bool {
    $stmt = $conn->prepare('DELETE FROM payments WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $paymentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_update_payment_status_reference($conn, int $paymentId, string $status, ?string $reference = null): bool {
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

function rc_mig_get_user_by_id($conn, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'users', [
            'select' => 'id,name,email,profile_pic,avatar_url,role',
            'id' => 'eq.' . $userId,
            'limit' => '1',
        ]);
        if ($res['ok'] && !empty($res['data'][0])) {
            $row = $res['data'][0];
            if (!isset($row['profile_pic']) && isset($row['avatar_url'])) {
                $row['profile_pic'] = $row['avatar_url'];
            }
            return $row;
        }
    }

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

function rc_mig_get_user_profile($conn, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'users', [
            'select' => 'id,name,email,phone,role,avatar_url',
            'id' => 'eq.' . $userId,
            'limit' => '1',
        ]);

        if (!empty($res['ok']) && !empty($res['data'][0])) {
            $row = $res['data'][0];
            $row['profile_pic'] = (string) ($row['avatar_url'] ?? '');
            return $row;
        }

        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    if (!isset($row['profile_pic'])) {
        $row['profile_pic'] = (string) ($row['avatar_url'] ?? '');
    }

    return $row;
}

function rc_mig_update_user_profile($conn, int $userId, array $fields): array {
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Invalid user'];
    }

    $name = trim((string) ($fields['name'] ?? ''));
    $phone = trim((string) ($fields['phone'] ?? ''));
    $profilePic = trim((string) ($fields['profile_pic'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => 'Name is required'];
    }

    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,25}$/', $phone)) {
        return ['ok' => false, 'error' => 'Phone format is invalid'];
    }

    if (rc_mig_supabase_should_try()) {
        $payload = [
            'name' => $name,
            'phone' => $phone === '' ? null : $phone,
            'updated_at' => gmdate('c'),
        ];

        if ($profilePic !== '') {
            $payload['avatar_url'] = $profilePic;
        }

        $res = rc_mig_supabase_request('PATCH', 'users', [
            'id' => 'eq.' . $userId,
        ], $payload);

        if (!empty($res['ok'])) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => (string) ($res['error'] ?? $res['raw'] ?? 'Failed to update profile')];
    }

    $stmt = $conn->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Failed to prepare profile update'];
    }

    $stmt->bind_param('ssi', $name, $phone, $userId);
    $ok = $stmt->execute();
    $error = (string) ($stmt->error ?? 'Failed to update profile');
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => $error];
    }

    if ($profilePic !== '') {
        $stmt2 = $conn->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
        if ($stmt2) {
            $stmt2->bind_param('si', $profilePic, $userId);
            $stmt2->execute();
            $stmt2->close();
        }

        $stmt3 = $conn->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
        if ($stmt3) {
            $stmt3->bind_param('si', $profilePic, $userId);
            $stmt3->execute();
            $stmt3->close();
        }
    }

    return ['ok' => true];
}

function rc_mig_can_access_chat($conn, int $actorId, string $actorRole, int $propertyId, int $withUserId): bool {
    if ($actorId <= 0 || $propertyId <= 0 || $withUserId <= 0 || $actorId === $withUserId) {
        return false;
    }

    $property = rc_mig_get_property_by_id($conn, $propertyId, false);
    if (!$property) {
        return false;
    }

    $ownerId = (int) (($property['owner_id'] ?? 0) ?: ($property['landlord_id'] ?? 0));

    if ($actorRole === 'renter') {
        if ($ownerId !== $withUserId) {
            return false;
        }

        if (rc_mig_supabase_should_try()) {
            $res = rc_mig_supabase_request('GET', 'requests', [
                'select' => 'id',
                'user_id' => 'eq.' . $actorId,
                'property_id' => 'eq.' . $propertyId,
                'limit' => '1',
            ]);
            return $res['ok'] && !empty($res['data'][0]);
        }

        $stmt = $conn->prepare('SELECT id FROM requests WHERE user_id = ? AND property_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $actorId, $propertyId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    if ($actorRole === 'landlord') {
        return $ownerId === $actorId;
    }

    return false;
}

function rc_mig_create_chat_message($conn, int $propertyId, int $senderId, int $receiverId, string $message): bool {
    $message = trim($message);
    if ($propertyId <= 0 || $senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId || $message === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $payload = [
            'property_id' => $propertyId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
            'created_at' => gmdate('c'),
        ];

        $res = rc_mig_supabase_request('POST', 'messages', [], $payload);
        return (bool) ($res['ok'] ?? false);
    }

    $stmt = $conn->prepare('INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iiis', $propertyId, $senderId, $receiverId, $message);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_chat_messages($conn, int $propertyId, int $userAId, int $userBId): array {
    if ($propertyId <= 0 || $userAId <= 0 || $userBId <= 0) {
        return [];
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'messages', [
            'select' => 'id,property_id,sender_id,receiver_id,message,created_at,is_read,read_status',
            'property_id' => 'eq.' . $propertyId,
            'or' => '(and(sender_id.eq.' . $userAId . ',receiver_id.eq.' . $userBId . '),and(sender_id.eq.' . $userBId . ',receiver_id.eq.' . $userAId . '))',
            'order' => 'created_at.asc',
        ]);

        if ($res['ok']) {
            $messages = $res['data'];
            $senderIds = [];
            foreach ($messages as $msg) {
                $sid = (int) ($msg['sender_id'] ?? 0);
                if ($sid > 0) {
                    $senderIds[$sid] = true;
                }
            }

            $nameMap = [];
            if (!empty($senderIds)) {
                $idList = implode(',', array_keys($senderIds));
                $usersRes = rc_mig_supabase_request('GET', 'users', [
                    'select' => 'id,name',
                    'id' => 'in.(' . $idList . ')',
                    'limit' => (string) count($senderIds),
                ]);
                if ($usersRes['ok']) {
                    foreach ($usersRes['data'] as $u) {
                        $nameMap[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? 'User');
                    }
                }
            }

            foreach ($messages as &$msg) {
                $sid = (int) ($msg['sender_id'] ?? 0);
                $msg['sender_name'] = $nameMap[$sid] ?? 'User';
            }
            unset($msg);

            return $messages;
        }
    }

    $stmt = $conn->prepare(
        'SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.property_id = ?
           AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.created_at ASC'
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iiiii', $propertyId, $userAId, $userBId, $userBId, $userAId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_chat_conversations($conn, int $currentUserId): array {
    if ($currentUserId <= 0) {
        return [];
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'messages', [
            'select' => 'id,property_id,sender_id,receiver_id,message,created_at,is_read,read_status',
            'or' => '(sender_id.eq.' . $currentUserId . ',receiver_id.eq.' . $currentUserId . ')',
            'order' => 'created_at.desc',
            'limit' => '500',
        ]);

        if ($res['ok']) {
            $conversations = [];
            $userIds = [];
            $propertyIds = [];

            foreach ($res['data'] as $msg) {
                $senderId = (int) ($msg['sender_id'] ?? 0);
                $receiverId = (int) ($msg['receiver_id'] ?? 0);
                $propertyId = (int) ($msg['property_id'] ?? 0);
                if ($propertyId <= 0) {
                    continue;
                }

                $otherId = $senderId === $currentUserId ? $receiverId : $senderId;
                if ($otherId <= 0) {
                    continue;
                }

                $key = $propertyId . ':' . $otherId;
                if (!isset($conversations[$key])) {
                    $conversations[$key] = [
                        'property_id' => $propertyId,
                        'with_id' => $otherId,
                        'with_name' => 'User',
                        'profile_pic' => '',
                        'property_title' => 'Property',
                        'last_time' => (string) ($msg['created_at'] ?? ''),
                        'last_message' => (string) ($msg['message'] ?? ''),
                        'unread_count' => 0,
                    ];
                }

                $hasReadFlag = array_key_exists('is_read', $msg) || array_key_exists('read_status', $msg);
                $isUnread = false;
                if ($hasReadFlag) {
                    if (array_key_exists('is_read', $msg)) {
                        $isUnread = !((bool) $msg['is_read']);
                    } elseif (array_key_exists('read_status', $msg)) {
                        $isUnread = ((int) $msg['read_status']) !== 1;
                    }
                }

                if ($receiverId === $currentUserId && $senderId === $otherId && $isUnread) {
                    $conversations[$key]['unread_count']++;
                }

                $userIds[$otherId] = true;
                $propertyIds[$propertyId] = true;
            }

            $userMap = [];
            if (!empty($userIds)) {
                $idList = implode(',', array_keys($userIds));
                $usersRes = rc_mig_supabase_request('GET', 'users', [
                    'select' => 'id,name,profile_pic,avatar_url',
                    'id' => 'in.(' . $idList . ')',
                    'limit' => (string) count($userIds),
                ]);
                if ($usersRes['ok']) {
                    foreach ($usersRes['data'] as $user) {
                        $id = (int) ($user['id'] ?? 0);
                        $userMap[$id] = [
                            'name' => (string) ($user['name'] ?? 'User'),
                            'profile_pic' => (string) (($user['profile_pic'] ?? '') ?: ($user['avatar_url'] ?? '')),
                        ];
                    }
                }
            }

            $propertyMap = [];
            if (!empty($propertyIds)) {
                $idList = implode(',', array_keys($propertyIds));
                $propRes = rc_mig_supabase_request('GET', 'properties', [
                    'select' => 'id,title',
                    'id' => 'in.(' . $idList . ')',
                    'limit' => (string) count($propertyIds),
                ]);
                if ($propRes['ok']) {
                    foreach ($propRes['data'] as $property) {
                        $propertyMap[(int) ($property['id'] ?? 0)] = (string) ($property['title'] ?? 'Property');
                    }
                }
            }

            foreach ($conversations as &$conv) {
                $withId = (int) $conv['with_id'];
                $propertyId = (int) $conv['property_id'];
                if (isset($userMap[$withId])) {
                    $conv['with_name'] = $userMap[$withId]['name'];
                    $conv['profile_pic'] = $userMap[$withId]['profile_pic'];
                }
                if (isset($propertyMap[$propertyId])) {
                    $conv['property_title'] = $propertyMap[$propertyId];
                }
            }
            unset($conv);

            $rows = array_values($conversations);
            usort($rows, static function (array $a, array $b): int {
                return strtotime((string) ($b['last_time'] ?? '')) <=> strtotime((string) ($a['last_time'] ?? ''));
            });
            return $rows;
        }
    }

    $sql = "SELECT
                m.property_id,
                u.id AS with_id,
                u.name AS with_name,
                u.profile_pic,
                p.title AS property_title,
                MAX(m.created_at) AS last_time,
                (SELECT message FROM messages
                 WHERE property_id = m.property_id
                   AND ((sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?))
                 ORDER BY created_at DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM messages
                 WHERE property_id = m.property_id
                   AND receiver_id = ?
                   AND sender_id = u.id
                   AND is_read = 0) AS unread_count
            FROM messages m
            JOIN properties p ON p.id = m.property_id
            JOIN users u ON
                (CASE
                    WHEN m.sender_id = ? THEN m.receiver_id = u.id
                    ELSE m.sender_id = u.id
                 END)
            WHERE (m.sender_id = ? OR m.receiver_id = ?)
            GROUP BY m.property_id, u.id
            ORDER BY last_time DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iiiiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_mark_chat_read($conn, int $propertyId, int $senderId, int $receiverId): bool {
    if ($propertyId <= 0 || $senderId <= 0 || $receiverId <= 0) {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $query = [
            'property_id' => 'eq.' . $propertyId,
            'sender_id' => 'eq.' . $senderId,
            'receiver_id' => 'eq.' . $receiverId,
        ];

        $r1 = rc_mig_supabase_request('PATCH', 'messages', $query, ['is_read' => true]);
        if (!empty($r1['ok'])) {
            return true;
        }

        $r2 = rc_mig_supabase_request('PATCH', 'messages', $query, ['read_status' => 1]);
        return !empty($r2['ok']);
    }

    $stmt = $conn->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND property_id = ?');
    if ($stmt) {
        $stmt->bind_param('iii', $senderId, $receiverId, $propertyId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    $stmt2 = $conn->prepare('UPDATE messages SET read_status = 1 WHERE sender_id = ? AND receiver_id = ? AND property_id = ?');
    if (!$stmt2) {
        return false;
    }
    $stmt2->bind_param('iii', $senderId, $receiverId, $propertyId);
    $ok = $stmt2->execute();
    $stmt2->close();
    return $ok;
}

function rc_mig_store_contact_message($conn, string $name, string $email, string $subject, string $message): bool {
    $name = trim($name);
    $email = trim($email);
    $subject = trim($subject);
    $message = trim($message);
    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $payload = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'created_at' => gmdate('c'),
        ];

        $res = rc_mig_supabase_request('POST', 'contact_messages', [], $payload);
        if (!empty($res['ok'])) {
            return true;
        }

        $legacyRes = rc_mig_supabase_request('POST', 'messages', [], $payload);
        return !empty($legacyRes['ok']);
    }

    $stmt = $conn->prepare('INSERT INTO messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssss', $name, $email, $subject, $message);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_property_title_by_id($conn, int $propertyId): ?array {
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

function rc_mig_payment_exists_for_month($conn, int $bookingId, string $paymentMonth): bool {
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

function rc_mig_get_active_bookings_with_stats($conn, int $renterId): array {
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

function rc_mig_get_payment_history($conn, int $renterId, int $limit = 20): array {
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

function rc_mig_get_user_auth_by_email($conn, string $email): ?array {
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

function rc_mig_prune_password_reset_tokens($conn, int $seconds): bool {
    if (rc_mig_supabase_should_try()) {
        $threshold = gmdate('c', time() - max(60, $seconds));
        $res = rc_mig_supabase_request('DELETE', 'password_resets', [
            'created_at' => 'lt.' . $threshold,
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    if (!is_object($conn) || !method_exists($conn, 'prepare')) {
        return false;
    }

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

function rc_mig_store_password_reset_token($conn, int $userId, string $token): bool {
    if ($userId <= 0 || $token === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('POST', 'password_resets', [], [
            'user_id' => $userId,
            'token' => $token,
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    if (!is_object($conn) || !method_exists($conn, 'prepare')) {
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

function rc_mig_get_password_reset_by_token($conn, string $token): ?array {
    if ($token === '') {
        return null;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'password_resets', [
            'select' => 'user_id,created_at',
            'token' => 'eq.' . $token,
            'limit' => '1',
        ]);
        if ($res['ok'] && !empty($res['data'][0])) {
            return $res['data'][0];
        }
        return null;
    }

    if (!is_object($conn) || !method_exists($conn, 'prepare')) {
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

function rc_mig_delete_password_reset_token($conn, string $token): bool {
    if ($token === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('DELETE', 'password_resets', [
            'token' => 'eq.' . $token,
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    if (!is_object($conn) || !method_exists($conn, 'prepare')) {
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

function rc_mig_update_user_password($conn, int $userId, string $passwordHash): bool {
    if ($userId <= 0 || $passwordHash === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('PATCH', 'users', [
            'id' => 'eq.' . $userId,
        ], [
            'password' => $passwordHash,
            'updated_at' => gmdate('c'),
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    if (!is_object($conn) || !method_exists($conn, 'prepare')) {
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

function rc_mig_get_index_properties($conn, ?int $userId, ?string $userRole, string $searchLocation, float $minPrice, float $maxPrice, int $limit = 12): array {
    if (rc_mig_supabase_should_try()) {
        $query = [
            'select' => 'id,title,location,price,status,landlord_id,owner_id,created_at',
            'order' => 'created_at.desc',
            'limit' => (string) max(1, $limit),
        ];

        if ($userRole === 'landlord' && $userId !== null) {
            $query['or'] = '(landlord_id.eq.' . (int) $userId . ',owner_id.eq.' . (int) $userId . ')';
            $query['status'] = 'in.(pending,approved)';
        } else {
            $query['status'] = 'eq.approved';
        }

        $searchLocation = trim($searchLocation);
        if ($searchLocation !== '') {
            $query['location'] = 'ilike.*' . $searchLocation . '*';
        }
        if ($minPrice > 0) {
            $query['price'] = 'gte.' . $minPrice;
        }
        if ($maxPrice > 0) {
            // PostgREST does not allow duplicate keys in simple query arrays; apply max filter client-side below.
        }

        $res = rc_mig_supabase_request('GET', 'properties', $query);
        if ($res['ok']) {
            $rows = [];
            foreach ($res['data'] as $row) {
                $price = (float) ($row['price'] ?? 0);
                if ($maxPrice > 0 && $price > $maxPrice) {
                    continue;
                }

                $landlordId = (int) (($row['landlord_id'] ?? 0) ?: ($row['owner_id'] ?? 0));
                $landlord = $landlordId > 0 ? rc_mig_get_user_by_id($conn, $landlordId) : null;
                $row['landlord_name'] = (string) ($landlord['name'] ?? 'Unknown');
                $rows[] = $row;
            }
            return $rows;
        }
    }

    $sql = "SELECT p.*, l.name AS landlord_name
            FROM properties p
            JOIN users l ON p.landlord_id = l.id
            WHERE 1=1";
    $params = [];
    $types = '';

    if ($userRole === 'landlord' && $userId !== null) {
        $sql .= " AND p.landlord_id = ? AND p.status IN ('pending', 'approved')";
        $params[] = $userId;
        $types .= 'i';
    } else {
        $sql .= " AND p.status = 'approved'";
    }

    if ($searchLocation !== '') {
        $sql .= ' AND p.location LIKE ?';
        $params[] = '%' . $searchLocation . '%';
        $types .= 's';
    }
    if ($minPrice > 0) {
        $sql .= ' AND p.price >= ?';
        $params[] = $minPrice;
        $types .= 'd';
    }
    if ($maxPrice > 0) {
        $sql .= ' AND p.price <= ?';
        $params[] = $maxPrice;
        $types .= 'd';
    }

    $sql .= ' ORDER BY p.created_at DESC LIMIT ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types .= 'i';
    $params[] = max(1, $limit);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_renter_active_bookings($conn, int $renterId): array {
    $stmt = $conn->prepare(
        "SELECT b.id, b.property_id, b.move_in_date, b.move_out_date, b.monthly_rent, b.status,
                p.title, p.location,
                u.name AS landlord_name
         FROM bookings b
         JOIN properties p ON b.property_id = p.id
         JOIN users u ON b.landlord_id = u.id
         WHERE b.renter_id = ? AND b.status = 'active'
         ORDER BY b.move_in_date DESC"
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

function rc_mig_get_renter_unread_reminders_count($conn, int $renterId): int {
    $stmt = $conn->prepare('SELECT COUNT(*) AS unread_reminders FROM rent_reminders WHERE renter_id = ? AND is_read = 0');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $renterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['unread_reminders' => 0];
    $stmt->close();
    return (int) ($row['unread_reminders'] ?? 0);
}

function rc_mig_get_renter_pending_payments_count($conn, int $renterId): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS pending_payments FROM payments WHERE renter_id = ? AND status = 'pending'");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $renterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['pending_payments' => 0];
    $stmt->close();
    return (int) ($row['pending_payments'] ?? 0);
}

function rc_mig_create_request_if_missing($conn, int $renterId, int $propertyId): bool {
    $check = $conn->prepare('SELECT id FROM requests WHERE user_id = ? AND property_id = ? LIMIT 1');
    if (!$check) {
        return false;
    }

    $check->bind_param('ii', $renterId, $propertyId);
    $check->execute();
    $exists = (bool) $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO requests (user_id, property_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $renterId, $propertyId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_cancel_pending_request($conn, int $requestId, int $renterId): bool {
    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $requestId, $renterId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function rc_mig_search_approved_properties($conn, string $search): array {
    $search = trim($search);

    if (rc_mig_supabase_should_try()) {
        $query = [
            'select' => 'id,title,location,price,description,bedrooms,bathrooms,owner_id,landlord_id,status,booking_status,created_at',
            'status' => 'eq.approved',
            'order' => 'created_at.desc',
            'limit' => '300',
        ];

        $res = rc_mig_supabase_request('GET', 'properties', $query);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return [];
        }

        $rows = $res['data'];
        if ($search !== '') {
            $needle = strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $title = strtolower((string) ($row['title'] ?? ''));
                $location = strtolower((string) ($row['location'] ?? ''));
                $price = strtolower((string) ($row['price'] ?? ''));
                return strpos($title, $needle) !== false
                    || strpos($location, $needle) !== false
                    || strpos($price, $needle) !== false;
            }));
        }

        foreach ($rows as &$row) {
            $ownerId = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
            $owner = $ownerId > 0 ? rc_mig_get_user_by_id($conn, $ownerId) : null;
            $row['landlord_name'] = (string) ($owner['name'] ?? '');
            $row['landlord_email'] = (string) ($owner['email'] ?? '');
        }
        unset($row);

        return $rows;
    }

    $searchTerm = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT p.*, u.name AS landlord_name, u.email AS landlord_email
                            FROM properties p
                            LEFT JOIN users u ON p.owner_id = u.id
                            WHERE p.status = 'approved'
                              AND (p.title LIKE ? OR p.location LIKE ? OR CAST(p.price AS CHAR) LIKE ?)
                            ORDER BY p.created_at DESC");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_get_renter_requests($conn, int $renterId): array {
    if ($renterId <= 0) {
        return [];
    }

    if (rc_mig_supabase_should_try()) {
        $reqRes = rc_mig_supabase_request('GET', 'requests', [
            'select' => 'id,status,created_at,property_id',
            'user_id' => 'eq.' . $renterId,
            'order' => 'created_at.desc',
            'limit' => '500',
        ]);

        if (empty($reqRes['ok']) || !is_array($reqRes['data']) || empty($reqRes['data'])) {
            return [];
        }

        $propertyIds = [];
        foreach ($reqRes['data'] as $req) {
            $pid = (int) ($req['property_id'] ?? 0);
            if ($pid > 0) {
                $propertyIds[$pid] = true;
            }
        }

        if (empty($propertyIds)) {
            return [];
        }

        $propRes = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,price,location,owner_id,landlord_id',
            'id' => 'in.(' . implode(',', array_keys($propertyIds)) . ')',
            'limit' => (string) count($propertyIds),
        ]);

        $propertyMap = [];
        if (!empty($propRes['ok']) && is_array($propRes['data'])) {
            foreach ($propRes['data'] as $property) {
                $propertyMap[(int) ($property['id'] ?? 0)] = $property;
            }
        }

        $ownerIds = [];
        foreach ($propertyMap as $property) {
            $ownerId = (int) (($property['owner_id'] ?? 0) ?: ($property['landlord_id'] ?? 0));
            if ($ownerId > 0) {
                $ownerIds[$ownerId] = true;
            }
        }

        $ownerMap = [];
        if (!empty($ownerIds)) {
            $ownersRes = rc_mig_supabase_request('GET', 'users', [
                'select' => 'id,name,email',
                'id' => 'in.(' . implode(',', array_keys($ownerIds)) . ')',
                'limit' => (string) count($ownerIds),
            ]);

            if (!empty($ownersRes['ok']) && is_array($ownersRes['data'])) {
                foreach ($ownersRes['data'] as $owner) {
                    $ownerMap[(int) ($owner['id'] ?? 0)] = $owner;
                }
            }
        }

        $rows = [];
        foreach ($reqRes['data'] as $req) {
            $propertyId = (int) ($req['property_id'] ?? 0);
            if (!isset($propertyMap[$propertyId])) {
                continue;
            }

            $property = $propertyMap[$propertyId];
            $ownerId = (int) (($property['owner_id'] ?? 0) ?: ($property['landlord_id'] ?? 0));
            $owner = $ownerMap[$ownerId] ?? [];

            $rows[] = [
                'request_id' => (int) ($req['id'] ?? 0),
                'status' => (string) ($req['status'] ?? 'pending'),
                'created_at' => (string) ($req['created_at'] ?? ''),
                'property_id' => $propertyId,
                'title' => (string) ($property['title'] ?? 'Property'),
                'price' => (float) ($property['price'] ?? 0),
                'location' => (string) ($property['location'] ?? ''),
                'landlord_id' => $ownerId,
                'landlord_name' => (string) ($owner['name'] ?? ''),
                'landlord_email' => (string) ($owner['email'] ?? ''),
            ];
        }

        return $rows;
    }

    $stmt = $conn->prepare(
        "SELECT r.id AS request_id, r.status, r.created_at,
                p.id AS property_id, p.title, p.price, p.location,
                p.owner_id AS landlord_id,
                u.name AS landlord_name, u.email AS landlord_email
         FROM requests r
         INNER JOIN properties p ON r.property_id = p.id
         INNER JOIN users u ON p.owner_id = u.id
         WHERE r.user_id = ?
         ORDER BY r.created_at DESC"
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

function rc_mig_get_landlord_dashboard_stats($conn, int $landlordId): array {
    $counts = ['pending' => 0, 'approved' => 0, 'taken' => 0];
    $pending_requests = 0;
    $booked_count = 0;
    $pending_payments = 0;
    $pending_income = 0.0;

    if (rc_mig_supabase_should_try()) {
        $propsRes = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,status,landlord_id,owner_id',
            'or' => '(landlord_id.eq.' . $landlordId . ',owner_id.eq.' . $landlordId . ')',
            'limit' => '2000',
        ]);

        $propertyIds = [];
        if (!empty($propsRes['ok']) && is_array($propsRes['data'])) {
            foreach ($propsRes['data'] as $row) {
                $status = (string) ($row['status'] ?? '');
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
                $pid = (int) ($row['id'] ?? 0);
                if ($pid > 0) {
                    $propertyIds[$pid] = true;
                }
            }
        }

        $requestsRes = rc_mig_supabase_request('GET', 'rental_requests', [
            'select' => 'property_id,status',
            'status' => 'eq.pending',
            'limit' => '2000',
        ]);
        if (!empty($requestsRes['ok']) && is_array($requestsRes['data']) && !empty($propertyIds)) {
            foreach ($requestsRes['data'] as $row) {
                $pid = (int) ($row['property_id'] ?? 0);
                if (isset($propertyIds[$pid])) {
                    $pending_requests++;
                }
            }
        }

        $bookingsRes = rc_mig_supabase_request('GET', 'bookings', [
            'select' => 'id,landlord_id,property_id,status',
            'status' => 'eq.active',
            'limit' => '2000',
        ]);
        if (!empty($bookingsRes['ok']) && is_array($bookingsRes['data'])) {
            foreach ($bookingsRes['data'] as $row) {
                $lid = (int) ($row['landlord_id'] ?? 0);
                $pid = (int) ($row['property_id'] ?? 0);
                if ($lid === $landlordId || isset($propertyIds[$pid])) {
                    $booked_count++;
                }
            }
        }

        $paymentsRes = rc_mig_supabase_request('GET', 'payments', [
            'select' => 'landlord_id,property_id,amount,status',
            'status' => 'eq.pending',
            'limit' => '2000',
        ]);
        if (!empty($paymentsRes['ok']) && is_array($paymentsRes['data'])) {
            foreach ($paymentsRes['data'] as $row) {
                $lid = (int) ($row['landlord_id'] ?? 0);
                $pid = (int) ($row['property_id'] ?? 0);
                if ($lid === $landlordId || isset($propertyIds[$pid])) {
                    $pending_payments++;
                    $pending_income += (float) ($row['amount'] ?? 0);
                }
            }
        }

        return [
            'counts' => $counts,
            'pending_requests' => $pending_requests,
            'booked_count' => $booked_count,
            'pending_payments' => $pending_payments,
            'pending_income' => $pending_income,
        ];
    }

    $stmt = $conn->prepare('SELECT status, COUNT(*) AS count FROM properties WHERE (landlord_id = ? OR owner_id = ?) GROUP BY status');
    if ($stmt) {
        $stmt->bind_param('ii', $landlordId, $landlordId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['count'];
        }
    }

    $stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM rental_requests WHERE property_id IN (SELECT id FROM properties WHERE landlord_id = ? OR owner_id = ?) AND status = 'pending'");
    if ($stmt2) {
        $stmt2->bind_param('ii', $landlordId, $landlordId);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $pending_requests = (int) ($row['c'] ?? 0);
        $stmt2->close();
    }

    $stmt3 = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE landlord_id = ? AND status = 'active'");
    if ($stmt3) {
        $stmt3->bind_param('i', $landlordId);
        $stmt3->execute();
        $row = $stmt3->get_result()->fetch_assoc();
        $booked_count = (int) ($row['c'] ?? 0);
        $stmt3->close();
    }

    $stmt4 = $conn->prepare("SELECT COUNT(*) AS c FROM payments WHERE landlord_id = ? AND status = 'pending'");
    if ($stmt4) {
        $stmt4->bind_param('i', $landlordId);
        $stmt4->execute();
        $row = $stmt4->get_result()->fetch_assoc();
        $pending_payments = (int) ($row['c'] ?? 0);
        $stmt4->close();
    }

    $stmt5 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE landlord_id = ? AND status = 'pending'");
    if ($stmt5) {
        $stmt5->bind_param('i', $landlordId);
        $stmt5->execute();
        $row = $stmt5->get_result()->fetch_assoc();
        $pending_income = (float) ($row['total'] ?? 0);
        $stmt5->close();
    }

    return [
        'counts' => $counts,
        'pending_requests' => $pending_requests,
        'booked_count' => $booked_count,
        'pending_payments' => $pending_payments,
        'pending_income' => $pending_income,
    ];
}

function rc_mig_get_landlord_properties($conn, int $landlordId, string $statusFilter = 'all', string $search = ''): array {
    if ($landlordId <= 0) {
        return [];
    }

    $statusFilter = trim($statusFilter);
    $search = trim($search);

    if (rc_mig_supabase_should_try()) {
        $query = [
            'select' => 'id,title,location,price,bedrooms,bathrooms,created_at,status,owner_id,landlord_id',
            'or' => '(landlord_id.eq.' . $landlordId . ',owner_id.eq.' . $landlordId . ')',
            'order' => 'created_at.desc',
            'limit' => '2000',
        ];
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query['status'] = 'eq.' . $statusFilter;
        }

        $res = rc_mig_supabase_request('GET', 'properties', $query);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return [];
        }

        $rows = $res['data'];
        if ($search !== '') {
            $needle = strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $title = strtolower((string) ($row['title'] ?? ''));
                $location = strtolower((string) ($row['location'] ?? ''));
                return strpos($title, $needle) !== false || strpos($location, $needle) !== false;
            }));
        }

        usort($rows, static function (array $a, array $b): int {
            $order = ['pending' => 0, 'approved' => 1, 'taken' => 2, 'inactive' => 3];
            $sa = $order[(string) ($a['status'] ?? '')] ?? 99;
            $sb = $order[(string) ($b['status'] ?? '')] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
        });

        return $rows;
    }

    $sql = "SELECT id, title, location, price, bedrooms, bathrooms, created_at, status
            FROM properties
            WHERE (landlord_id = ? OR owner_id = ?)";
    $params = [$landlordId, $landlordId];
    $types = 'ii';

    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $sql .= ' AND status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $sql .= ' AND (title LIKE ? OR location LIKE ?)';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $sql .= " ORDER BY FIELD(status,'pending','approved','taken','inactive'), created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function rc_mig_set_property_status($conn, int $propertyId, string $status): bool {
    if ($propertyId <= 0 || trim($status) === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('PATCH', 'properties', [
            'id' => 'eq.' . $propertyId,
        ], [
            'status' => $status,
            'updated_at' => gmdate('c'),
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    $stmt = $conn->prepare('UPDATE properties SET status = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $status, $propertyId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_delete_property_with_images($conn, int $propertyId): bool {
    if ($propertyId <= 0) {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        rc_mig_supabase_request('DELETE', 'property_images', [
            'property_id' => 'eq.' . $propertyId,
        ]);
        $res = rc_mig_supabase_request('DELETE', 'properties', [
            'id' => 'eq.' . $propertyId,
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    $imgStmt = $conn->prepare('DELETE FROM property_images WHERE property_id = ?');
    if ($imgStmt) {
        $imgStmt->bind_param('i', $propertyId);
        $imgStmt->execute();
        $imgStmt->close();
    }

    $stmt = $conn->prepare('DELETE FROM properties WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $propertyId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_get_admin_dashboard_stats($conn): array {
    if (rc_mig_supabase_should_try()) {
        $users = rc_mig_supabase_request('GET', 'users', ['select' => 'id']);
        $properties = rc_mig_supabase_request('GET', 'properties', ['select' => 'id']);
        $requests = rc_mig_supabase_request('GET', 'requests', ['select' => 'id,status']);

        $requestRows = (!empty($requests['ok']) && is_array($requests['data'])) ? $requests['data'] : [];
        $pending = 0;
        foreach ($requestRows as $row) {
            if ((string) ($row['status'] ?? '') === 'pending') {
                $pending++;
            }
        }

        return [
            'total_users' => !empty($users['ok']) && is_array($users['data']) ? count($users['data']) : 0,
            'total_properties' => !empty($properties['ok']) && is_array($properties['data']) ? count($properties['data']) : 0,
            'total_requests' => count($requestRows),
            'pending_requests' => $pending,
        ];
    }

    $total_users = (int) (($conn->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'] ?? 0));
    $total_properties = (int) (($conn->query('SELECT COUNT(*) AS c FROM properties')->fetch_assoc()['c'] ?? 0));
    $total_requests = (int) (($conn->query('SELECT COUNT(*) AS c FROM requests')->fetch_assoc()['c'] ?? 0));
    $pending_requests = (int) (($conn->query("SELECT COUNT(*) AS c FROM requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0));

    return [
        'total_users' => $total_users,
        'total_properties' => $total_properties,
        'total_requests' => $total_requests,
        'pending_requests' => $pending_requests,
    ];
}

function rc_mig_get_pending_properties_for_admin($conn): array {
    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,location,price,owner_id,landlord_id,created_at,status',
            'status' => 'eq.pending',
            'order' => 'created_at.desc',
        ]);
        if (!empty($res['ok']) && is_array($res['data'])) {
            $rows = $res['data'];
            $ownerIds = [];
            foreach ($rows as $row) {
                $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
                if ($oid > 0) {
                    $ownerIds[$oid] = true;
                }
            }

            $owners = [];
            if (!empty($ownerIds)) {
                $usersRes = rc_mig_supabase_request('GET', 'users', [
                    'select' => 'id,name,email',
                    'id' => 'in.(' . implode(',', array_keys($ownerIds)) . ')',
                ]);
                if (!empty($usersRes['ok']) && is_array($usersRes['data'])) {
                    foreach ($usersRes['data'] as $u) {
                        $owners[(int) ($u['id'] ?? 0)] = $u;
                    }
                }
            }

            foreach ($rows as &$row) {
                $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
                $owner = $owners[$oid] ?? [];
                $row['owner_name'] = (string) ($owner['name'] ?? 'Unknown');
                $row['owner_email'] = (string) ($owner['email'] ?? '');
            }
            unset($row);

            return $rows;
        }

        return [];
    }

    $sql = "SELECT p.id, p.title, p.location, p.price, u.name AS owner_name, u.email AS owner_email, p.created_at
            FROM properties p
            JOIN users u ON u.id = COALESCE(p.owner_id, p.landlord_id)
            WHERE p.status='pending'
            ORDER BY p.created_at DESC";
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function rc_mig_get_manage_properties_rows($conn): array {
    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,location,price,status,owner_id,landlord_id,created_at',
            'order' => 'created_at.desc',
        ]);
        if (!empty($res['ok']) && is_array($res['data'])) {
            $rows = $res['data'];
            $ownerIds = [];
            foreach ($rows as $row) {
                $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
                if ($oid > 0) {
                    $ownerIds[$oid] = true;
                }
            }

            $owners = [];
            if (!empty($ownerIds)) {
                $usersRes = rc_mig_supabase_request('GET', 'users', [
                    'select' => 'id,name,email',
                    'id' => 'in.(' . implode(',', array_keys($ownerIds)) . ')',
                ]);
                if (!empty($usersRes['ok']) && is_array($usersRes['data'])) {
                    foreach ($usersRes['data'] as $u) {
                        $owners[(int) ($u['id'] ?? 0)] = $u;
                    }
                }
            }

            foreach ($rows as &$row) {
                $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
                $owner = $owners[$oid] ?? [];
                $row['owner'] = (string) ($owner['name'] ?? 'Unknown');
                $row['landlord_name'] = (string) ($owner['name'] ?? 'Unknown');
                $row['landlord_email'] = (string) ($owner['email'] ?? '');
            }
            unset($row);

            return $rows;
        }

        return [];
    }

    $sql = "SELECT p.id, p.title, p.location, p.price, p.status, p.created_at,
                   u.name AS owner, u.name AS landlord_name, u.email AS landlord_email
            FROM properties p
            JOIN users u ON u.id = COALESCE(p.owner_id, p.landlord_id)
            ORDER BY p.created_at DESC";
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function rc_mig_get_super_admin_property_stats($conn): array {
    $stats = [
        'total_properties' => 0,
        'pending_count' => 0,
        'approved_count' => 0,
        'taken_count' => 0,
        'inactive_count' => 0,
        'total_landlords' => 0,
    ];

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'status,landlord_id,owner_id',
        ]);
        if (!empty($res['ok']) && is_array($res['data'])) {
            $landlords = [];
            foreach ($res['data'] as $row) {
                $stats['total_properties']++;
                $status = (string) ($row['status'] ?? '');
                if ($status === 'pending') {
                    $stats['pending_count']++;
                } elseif ($status === 'approved') {
                    $stats['approved_count']++;
                } elseif ($status === 'taken') {
                    $stats['taken_count']++;
                } elseif ($status === 'inactive') {
                    $stats['inactive_count']++;
                }

                $lid = (int) (($row['landlord_id'] ?? 0) ?: ($row['owner_id'] ?? 0));
                if ($lid > 0) {
                    $landlords[$lid] = true;
                }
            }
            $stats['total_landlords'] = count($landlords);
            return $stats;
        }

        return $stats;
    }

    $stats_sql = "SELECT
        COUNT(*) as total_properties,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status='taken' THEN 1 ELSE 0 END) as taken_count,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive_count,
        COUNT(DISTINCT landlord_id) as total_landlords
    FROM properties";
    $stmt = $conn->prepare($stats_sql);
    if (!$stmt) {
        return $stats;
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    foreach ($stats as $k => $v) {
        $stats[$k] = (int) ($row[$k] ?? 0);
    }
    return $stats;
}

function rc_mig_get_super_admin_properties_page($conn, string $statusFilter, string $search, int $page, int $limit, string $contactFilter = 'all'): array {
    $statusFilter = trim($statusFilter) === '' ? 'all' : trim($statusFilter);
    $search = trim($search);
    $contactFilter = trim($contactFilter) === '' ? 'all' : trim($contactFilter);
    $page = max(1, $page);
    $limit = max(1, $limit);

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('GET', 'properties', [
            'select' => 'id,title,price,contact,bedrooms,bathrooms,description,status,created_at,owner_id,landlord_id',
            'order' => 'created_at.desc',
        ]);
        $rows = (!empty($res['ok']) && is_array($res['data'])) ? $res['data'] : [];

        $ownerIds = [];
        foreach ($rows as $row) {
            $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
            if ($oid > 0) {
                $ownerIds[$oid] = true;
            }
        }

        $owners = [];
        if (!empty($ownerIds)) {
            $usersRes = rc_mig_supabase_request('GET', 'users', [
                'select' => 'id,name',
                'id' => 'in.(' . implode(',', array_keys($ownerIds)) . ')',
            ]);
            if (!empty($usersRes['ok']) && is_array($usersRes['data'])) {
                foreach ($usersRes['data'] as $u) {
                    $owners[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? 'Unknown');
                }
            }
        }

        foreach ($rows as &$row) {
            $oid = (int) (($row['owner_id'] ?? 0) ?: ($row['landlord_id'] ?? 0));
            $row['landlord_name'] = $owners[$oid] ?? 'Unknown';
        }
        unset($row);

        $filtered = [];
        foreach ($rows as $row) {
            if ($statusFilter !== 'all' && (string) ($row['status'] ?? '') !== $statusFilter) {
                continue;
            }

            $contact = trim((string) ($row['contact'] ?? ''));
            if ($contactFilter === 'with_number' && $contact === '') {
                continue;
            }
            if ($contactFilter === 'missing_number' && $contact !== '') {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower((string) (($row['title'] ?? '') . ' ' . ($row['landlord_name'] ?? '') . ' ' . ($row['contact'] ?? '')));
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        usort($filtered, static function (array $a, array $b): int {
            $order = ['pending' => 0, 'approved' => 1, 'taken' => 2, 'inactive' => 3];
            $sa = $order[(string) ($a['status'] ?? '')] ?? 99;
            $sb = $order[(string) ($b['status'] ?? '')] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
        });

        $totalRecords = count($filtered);
        $totalPages = (int) max(1, ceil($totalRecords / $limit));
        $offset = ($page - 1) * $limit;
        $pageRows = array_slice($filtered, $offset, $limit);

        return [
            'rows' => $pageRows,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
        ];
    }

    $offset = ($page - 1) * $limit;
    $sql = 'FROM properties p JOIN users l ON p.landlord_id = l.id WHERE 1=1';
    $params = [];
    $types = '';

    if ($statusFilter !== 'all') {
        $sql .= ' AND p.status=?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    if ($contactFilter === 'with_number') {
        $sql .= " AND p.contact IS NOT NULL AND TRIM(p.contact) <> ''";
    } elseif ($contactFilter === 'missing_number') {
        $sql .= " AND (p.contact IS NULL OR TRIM(p.contact) = '')";
    }

    if ($search !== '') {
        $sql .= ' AND (p.title LIKE ? OR l.name LIKE ? OR p.contact LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $countSql = 'SELECT COUNT(*) ' . $sql;
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        return ['rows' => [], 'total_records' => 0, 'total_pages' => 1];
    }
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();

    $dataSql = 'SELECT p.id, p.title, p.price, p.contact, p.bedrooms, p.bathrooms, p.description, p.status, p.created_at, l.name AS landlord_name '
        . $sql
        . " ORDER BY FIELD(p.status,'pending','approved','taken','inactive'), p.created_at DESC LIMIT ?, ?";
    $stmt = $conn->prepare($dataSql);
    if (!$stmt) {
        return ['rows' => [], 'total_records' => 0, 'total_pages' => 1];
    }
    if (!empty($params)) {
        $all = array_merge($params, [$offset, $limit]);
        $stmt->bind_param($types . 'ii', ...$all);
    } else {
        $stmt->bind_param('ii', $offset, $limit);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'rows' => $rows,
        'total_records' => (int) $totalRecords,
        'total_pages' => (int) max(1, ceil(((int) $totalRecords) / $limit)),
    ];
}

function rc_mig_set_property_booking_status($conn, int $propertyId, string $bookingStatus): bool {
    if ($propertyId <= 0 || trim($bookingStatus) === '') {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        $res = rc_mig_supabase_request('PATCH', 'properties', [
            'id' => 'eq.' . $propertyId,
        ], [
            'booking_status' => $bookingStatus,
            'updated_at' => gmdate('c'),
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    $stmt = $conn->prepare('UPDATE properties SET booking_status = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $bookingStatus, $propertyId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rc_mig_delete_property_for_landlord($conn, int $propertyId, int $landlordId): bool {
    if ($propertyId <= 0 || $landlordId <= 0) {
        return false;
    }

    if (rc_mig_supabase_should_try()) {
        rc_mig_supabase_request('DELETE', 'property_images', [
            'property_id' => 'eq.' . $propertyId,
        ]);
        $res = rc_mig_supabase_request('DELETE', 'properties', [
            'id' => 'eq.' . $propertyId,
            'or' => '(landlord_id.eq.' . $landlordId . ',owner_id.eq.' . $landlordId . ')',
        ]);
        return (bool) ($res['ok'] ?? false);
    }

    $imgStmt = $conn->prepare('DELETE FROM property_images WHERE property_id = ?');
    if ($imgStmt) {
        $imgStmt->bind_param('i', $propertyId);
        $imgStmt->execute();
        $imgStmt->close();
    }

    $stmt = $conn->prepare('DELETE FROM properties WHERE id = ? AND (landlord_id = ? OR owner_id = ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $propertyId, $landlordId, $landlordId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

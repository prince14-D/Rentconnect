<?php
include 'app_init.php';

$cfg = rc_supabase_config();
echo "Supabase URL: " . htmlspecialchars($cfg['url'] ?? '') . "\n";

$probe = rc_mig_supabase_request('GET', 'users', [
    'select' => 'id',
    'limit' => '1',
]);

if (!empty($probe['ok'])) {
    echo "Supabase connection: OK\n";
} else {
    echo "Supabase connection: FAILED\n";
    echo "Details: " . htmlspecialchars((string) ($probe['error'] ?? $probe['raw'] ?? 'unknown')) . "\n";
}
?>

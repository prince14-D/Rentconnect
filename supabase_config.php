<?php

/**
 * Central Supabase project configuration.
 *
 * Uses environment variables for deployment-safe configuration.
 */
function rc_supabase_config(): array {
    $url = trim((string) (getenv('SUPABASE_URL') ?: ''));
    $publishableKey = trim((string) (getenv('SUPABASE_PUBLISHABLE_KEY') ?: ''));
    $serviceRoleKey = trim((string) (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));

    // Server-side write operations should prefer the service-role key.
    $restKey = $serviceRoleKey !== '' ? $serviceRoleKey : $publishableKey;

    return [
        'url' => $url,
        'publishableKey' => $publishableKey,
        'serviceRoleKey' => $serviceRoleKey,
        'restKey' => $restKey,
    ];
}

function rc_supabase_rest_enabled(): bool {
    return getenv('SUPABASE_USE_REST') === '1';
}

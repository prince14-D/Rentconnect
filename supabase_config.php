<?php

/**
 * Central Supabase project configuration.
 *
 * Uses environment variables for deployment-safe configuration.
 */
function rc_supabase_config(): array {
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

    $url = trim((string) $envValue('SUPABASE_URL'));
    $publishableKey = trim((string) $envValue('SUPABASE_PUBLISHABLE_KEY'));
    $serviceRoleKey = trim((string) $envValue('SUPABASE_SERVICE_ROLE_KEY'));

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

    return $envValue('SUPABASE_USE_REST') === '1';
}

<?php

// Minimal .env loader for local/dev usage.
if (!function_exists('rc_load_env_file')) {
    function rc_load_env_file(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if ($name === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

if (!function_exists('rc_env_value')) {
    function rc_env_value(string $name, string $default = ''): string {
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
    }
}

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    rc_load_env_file($envPath);
} else {
    rc_load_env_file(__DIR__ . '/.env.example');
}

require_once __DIR__ . '/legacy_db_disabled.php';
require_once __DIR__ . '/supabase_config.php';
require_once __DIR__ . '/supabase_rest.php';
require_once __DIR__ . '/supabase_migration.php';
require_once __DIR__ . '/firebase_config.php';

// Legacy compatibility: code paths still expecting $conn will receive a disabled adapter.
$conn = new RcLegacyDbDisabled();

if (!function_exists('rc_require_supabase_ready')) {
    function rc_require_supabase_ready(): void {
        if (!rc_supabase_rest_enabled()) {
            $msg = 'Supabase REST is disabled. Set SUPABASE_USE_REST=1.';
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }
            exit($msg);
        }

        $cfg = rc_supabase_config();
        $url = trim((string) ($cfg['url'] ?? ''));
        $restKey = trim((string) ($cfg['restKey'] ?? ''));
        if ($url === '' || $restKey === '') {
            $msg = 'Supabase configuration is incomplete. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY (or SUPABASE_PUBLISHABLE_KEY).';
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }
            exit($msg);
        }
    }
}

rc_require_supabase_ready();

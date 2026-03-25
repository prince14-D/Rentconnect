<?php

/**
 * Central Supabase project configuration.
 *
 * Uses environment variables when available, and falls back to the
 * current project values provided for this workspace.
 */
function rc_supabase_config(): array {
    return [
        'url' => getenv('SUPABASE_URL') ?: 'https://xakhdbrwruwykgzxatcs.supabase.co',
        'publishableKey' => getenv('SUPABASE_PUBLISHABLE_KEY') ?: 'sb_publishable_-SqbAs2NLADpCl1Ghh2f4A_KDstsWvg',
    ];
}

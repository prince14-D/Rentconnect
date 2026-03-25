<?php

class RcLegacyDbResult {
    public int $num_rows = 0;

    public function fetch_assoc(): ?array {
        return null;
    }

    public function fetch_all(int $mode = 0): array {
        return [];
    }

    public function fetch_row(): ?array {
        return null;
    }
}

class RcLegacyDbStatement {
    public string $error = 'Legacy SQL is disabled. Use Supabase helper functions.';
    public int $insert_id = 0;
    public int $num_rows = 0;
    private RcLegacyDbResult $result;

    public function __construct() {
        $this->result = new RcLegacyDbResult();
    }

    public function bind_param(string $types, &...$vars): bool {
        return true;
    }

    public function bind_result(&...$vars): bool {
        return true;
    }

    public function send_long_data(int $param_num, string $data): bool {
        return false;
    }

    public function execute(): bool {
        return false;
    }

    public function store_result(): bool {
        return true;
    }

    public function get_result(): RcLegacyDbResult {
        return $this->result;
    }

    public function fetch(): bool {
        return false;
    }

    public function close(): bool {
        return true;
    }
}

class RcLegacyDbDisabled {
    public int $insert_id = 0;
    public string $error = 'Legacy SQL is disabled. Use Supabase helper functions.';
    public string $connect_error = '';

    public function prepare(string $query): RcLegacyDbStatement {
        return new RcLegacyDbStatement();
    }

    public function query(string $query): RcLegacyDbResult {
        return new RcLegacyDbResult();
    }

    public function begin_transaction(): bool {
        return false;
    }

    public function commit(): bool {
        return false;
    }

    public function rollback(): bool {
        return false;
    }

    public function real_escape_string(string $string): string {
        return $string;
    }
}

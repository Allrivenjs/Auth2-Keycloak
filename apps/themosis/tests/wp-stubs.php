<?php

/**
 * Minimal WordPress function stubs for unit testing outside of a WordPress
 * environment.  Only the functions exercised by the tests are stubbed here.
 */

if (!class_exists(\WP_Error::class)) {
    class WP_Error
    {
        private string $code;
        private string $message;
        /** @var array<string, mixed> */
        private array $data;

        /**
         * @param array<string, mixed> $data
         */
        public function __construct(string $code, string $message, array $data = [])
        {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /** @return array<string, mixed> */
        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

if (!class_exists(\WP_User::class)) {
    class WP_User
    {
        public int    $ID           = 0;
        public string $display_name = '';
        public string $user_email   = '';
        public string $first_name   = '';
        public string $last_name    = '';
        /** @var string[] */
        public array  $roles        = [];
    }
}

if (!class_exists(\WP_REST_Response::class)) {
    class WP_REST_Response
    {
        public function __construct(
            private readonly mixed $data,
            private readonly int   $status = 200,
        ) {}

        public function get_data(): mixed { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool { return false; }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): WP_User { return new WP_User(); }
}
if (!function_exists('get_user_by')) {
    function get_user_by(string $field, mixed $value): WP_User|false { return false; }
}
if (!function_exists('wp_create_user')) {
    function wp_create_user(string $username, string $password, string $email): int|WP_Error { return 1; }
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12): string { return 'generated-password'; }
}
if (!function_exists('wp_update_user')) {
    /** @param array<string, mixed> $data */
    function wp_update_user(array $data): int|WP_Error { return $data['ID']; }
}
if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $id): WP_User { return new WP_User(); }
}
if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie(int $user_id, bool $remember = false): void {}
}
if (!function_exists('wp_roles')) {
    function wp_roles(): object {
        return new class { public array $roles = ['administrator' => [], 'editor' => [], 'subscriber' => [], 'user' => []]; };
    }
}
if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $args = 1): true { return true; }
}
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): true { return true; }
}
if (!function_exists('register_rest_route')) {
    /** @param array<string, mixed> $args */
    function register_rest_route(string $namespace, string $route, array $args): bool { return true; }
}
if (!function_exists('status_header')) {
    function status_header(int $code): void {}
}
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool { return $thing instanceof WP_Error; }
}
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed { return $_ENV[$key] ?? getenv($key) ?: $default; }
}

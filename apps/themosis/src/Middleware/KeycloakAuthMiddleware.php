<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Middleware;

use Auth2\Keycloak\Auth\KeycloakTokenValidator;
use Auth2\Keycloak\Auth\WordPressSessionSync;

/**
 * KeycloakAuthMiddleware
 *
 * WordPress REST API middleware that:
 *  1. Extracts the Bearer token from the Authorization header.
 *  2. Validates it against Keycloak's JWKS endpoint.
 *  3. Syncs the Keycloak identity to a WordPress user session.
 *
 * Hooks used
 * ----------
 * - `rest_authentication_errors`  – authenticate the request before WP REST
 *                                   processes it.
 * - `rest_pre_serve_request`      – add CORS headers to every REST response.
 * - `init`                        – register the custom REST namespace routes.
 */
class KeycloakAuthMiddleware
{
    public function __construct(
        private readonly KeycloakTokenValidator $validator,
        private readonly WordPressSessionSync   $sessionSync,
        private readonly string                 $allowedOrigin = '',
    ) {}

    /**
     * Register all WordPress hooks. Call this once from the plugin bootstrap.
     */
    public function register(): void
    {
        add_filter('rest_authentication_errors', [$this, 'authenticate']);
        add_filter('rest_pre_serve_request',     [$this, 'addCorsHeaders']);
        add_action('init',                       [$this, 'registerRoutes']);
    }

    /**
     * Authenticate the incoming REST request using a Keycloak Bearer token.
     *
     * Returning null means "I did not authenticate this request; let WP fall
     * through to its own auth mechanisms".  Returning a WP_Error marks the
     * request as rejected.
     *
     * @param  \WP_Error|true|null $result  Existing authentication result.
     * @return \WP_Error|true|null
     */
    public function authenticate(\WP_Error|bool|null $result): \WP_Error|bool|null
    {
        // Another middleware already handled auth — do not interfere
        if ($result !== null) {
            return $result;
        }

        $token = $this->extractBearerToken();

        if ($token === null) {
            // No Bearer token → fall through to WordPress cookie auth
            return null;
        }

        try {
            $claims = $this->validator->validate($token);
            $this->sessionSync->sync($claims);
            return true;
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('keycloak_invalid_token', $e->getMessage(), ['status' => 400]);
        } catch (\UnexpectedValueException $e) {
            return new \WP_Error('keycloak_token_expired', 'Token inválido o expirado.', ['status' => 401]);
        } catch (\RuntimeException $e) {
            return new \WP_Error('keycloak_auth_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Add CORS headers so that the Next.js app can call the Themosis REST API
     * from the browser.
     *
     * @param  bool $served  Whether the response has already been served.
     * @return bool
     */
    public function addCorsHeaders(bool $served): bool
    {
        $origin = $this->allowedOrigin !== '' ? $this->allowedOrigin : '*';
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Allow-Credentials: true');

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(204);
            exit;
        }

        return $served;
    }

    /**
     * Register custom REST API routes under the `auth2/v1` namespace.
     */
    public function registerRoutes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('auth2/v1', '/me', [
                'methods'             => 'GET',
                'callback'            => [$this, 'meEndpoint'],
                'permission_callback' => fn() => is_user_logged_in(),
            ]);
        });
    }

    /**
     * GET /wp-json/auth2/v1/me
     *
     * Returns the current WordPress user's identity — the same user that was
     * synced from the Keycloak token — so the Next.js profile page can confirm
     * that the session is shared.
     *
     * @return \WP_REST_Response
     */
    public function meEndpoint(): \WP_REST_Response
    {
        $user = wp_get_current_user();

        return new \WP_REST_Response([
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
        ], 200);
    }

    /**
     * Extract the raw JWT from the Authorization header.
     *
     * @return string|null  JWT string, or null if no Bearer token is present.
     */
    private function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? (function_exists('apache_request_headers')
                ? (apache_request_headers()['Authorization'] ?? null)
                : null);

        if ($header === null) {
            return null;
        }

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}

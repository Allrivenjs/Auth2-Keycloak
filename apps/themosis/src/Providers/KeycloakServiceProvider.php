<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Providers;

use Auth2\Keycloak\Auth\KeycloakTokenValidator;
use Auth2\Keycloak\Auth\WordPressSessionSync;
use Auth2\Keycloak\Middleware\KeycloakAuthMiddleware;
use GuzzleHttp\Client;

/**
 * KeycloakServiceProvider
 *
 * Themosis service provider that wires up the Keycloak authentication
 * components and registers WordPress hooks.
 *
 * Register in config/app.php:
 *
 *   'providers' => [
 *       Auth2\Keycloak\Providers\KeycloakServiceProvider::class,
 *   ],
 */
class KeycloakServiceProvider
{
    public function register(): void
    {
        $keycloakUrl   = (string) (env('KEYCLOAK_URL')    ?? '');
        $realm         = (string) (env('KEYCLOAK_REALM')  ?? 'master');
        $allowedOrigin = (string) (env('ALLOWED_ORIGIN')  ?? '');

        if ($keycloakUrl === '') {
            trigger_error(
                '[Auth2-Keycloak] KEYCLOAK_URL is not set. Keycloak authentication will not work.',
                E_USER_WARNING,
            );
            return;
        }

        $validator   = new KeycloakTokenValidator($keycloakUrl, $realm, new Client());
        $sessionSync = new WordPressSessionSync();
        $middleware  = new KeycloakAuthMiddleware($validator, $sessionSync, $allowedOrigin);

        $middleware->register();
    }
}

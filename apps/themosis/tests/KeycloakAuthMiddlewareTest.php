<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Tests;

use Auth2\Keycloak\Middleware\KeycloakAuthMiddleware;
use Auth2\Keycloak\Auth\KeycloakTokenValidator;
use Auth2\Keycloak\Auth\WordPressSessionSync;
use PHPUnit\Framework\TestCase;
use Mockery;
use stdClass;

/**
 * Unit tests for KeycloakAuthMiddleware.
 *
 * WordPress functions (is_user_logged_in, wp_get_current_user, etc.) do not
 * exist in unit-test context, so they are stubbed via function stubs defined
 * at the bottom of this file.
 */
class KeycloakAuthMiddlewareTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function makeMiddleware(
        KeycloakTokenValidator $validator,
        WordPressSessionSync   $sync,
        string                 $origin = 'http://localhost:3000',
    ): KeycloakAuthMiddleware {
        return new KeycloakAuthMiddleware($validator, $sync, $origin);
    }

    public function testReturnsNullWhenNoBearerTokenPresent(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $validator = Mockery::mock(KeycloakTokenValidator::class);
        $sync      = Mockery::mock(WordPressSessionSync::class);
        $validator->shouldNotReceive('validate');

        $middleware = $this->makeMiddleware($validator, $sync);

        $this->assertNull($middleware->authenticate(null));
    }

    public function testReturnsTrueOnValidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-jwt';

        $claims            = new stdClass();
        $claims->sub       = 'user-uuid';
        $claims->email     = 'demo@example.com';

        $validator = Mockery::mock(KeycloakTokenValidator::class);
        $validator->shouldReceive('validate')->once()->with('valid-jwt')->andReturn($claims);

        $wpUser = Mockery::mock(\WP_User::class);
        $sync   = Mockery::mock(WordPressSessionSync::class);
        $sync->shouldReceive('sync')->once()->with($claims)->andReturn($wpUser);

        $middleware = $this->makeMiddleware($validator, $sync);

        $this->assertTrue($middleware->authenticate(null));

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testReturnsWpErrorOnExpiredToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer expired-jwt';

        $validator = Mockery::mock(KeycloakTokenValidator::class);
        $validator->shouldReceive('validate')
            ->once()
            ->andThrow(new \UnexpectedValueException('Expired token'));

        $sync = Mockery::mock(WordPressSessionSync::class);
        $sync->shouldNotReceive('sync');

        $middleware = $this->makeMiddleware($validator, $sync);
        $result     = $middleware->authenticate(null);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('keycloak_token_expired', $result->get_error_code());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testPassesThroughExistingAuthResult(): void
    {
        $existingError = new \WP_Error('other_error', 'Already set');

        $validator = Mockery::mock(KeycloakTokenValidator::class);
        $sync      = Mockery::mock(WordPressSessionSync::class);
        $validator->shouldNotReceive('validate');

        $middleware = $this->makeMiddleware($validator, $sync);
        $result     = $middleware->authenticate($existingError);

        $this->assertSame($existingError, $result);
    }
}

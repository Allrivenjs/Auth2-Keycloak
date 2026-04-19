<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Tests;

use Auth2\Keycloak\Auth\KeycloakTokenValidator;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KeycloakTokenValidator.
 *
 * We generate a real RSA key pair so we can create and verify actual JWTs
 * without needing a live Keycloak server.
 */
class KeycloakTokenValidatorTest extends TestCase
{
    private static string $privateKey;
    private static string $publicKey;
    private static string $kid = 'test-key-id';

    public static function setUpBeforeClass(): void
    {
        // Generate an RSA key pair for testing
        $res = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKey = '';
        openssl_pkey_export($res, $privateKey);
        self::$privateKey = $privateKey;
        $details          = openssl_pkey_get_details($res);
        self::$publicKey  = $details['key'];
    }

    /** Build a minimal JWKS response body from our test public key */
    private function buildJwks(): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public(self::$publicKey));
        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        return json_encode([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => self::$kid,
                'n'   => $n,
                'e'   => $e,
            ]],
        ]);
    }

    /** Create a Guzzle client that returns the given response */
    private function makeHttpClient(string $body, int $status = 200): Client
    {
        $mock    = new MockHandler([new Response($status, [], $body)]);
        $handler = HandlerStack::create($mock);
        return new Client(['handler' => $handler]);
    }

    private function makeValidator(?Client $client = null): KeycloakTokenValidator
    {
        return new KeycloakTokenValidator(
            'http://keycloak:8080',
            'auth2',
            $client ?? $this->makeHttpClient($this->buildJwks()),
        );
    }

    private function issueToken(array $payload = [], int $expiresIn = 300): string
    {
        $defaults = [
            'sub'                => 'user-uuid-123',
            'email'              => 'demo@example.com',
            'preferred_username' => 'demo',
            'iss'                => 'http://keycloak:8080/realms/auth2',
            'aud'                => 'nextjs-client',
            'iat'                => time(),
            'exp'                => time() + $expiresIn,
        ];

        return JWT::encode(
            array_merge($defaults, $payload),
            self::$privateKey,
            'RS256',
            self::$kid,
        );
    }

    public function testValidTokenReturnsClaims(): void
    {
        $validator = $this->makeValidator();
        $token     = $this->issueToken(['email' => 'demo@example.com']);

        $claims = $validator->validate($token);

        $this->assertSame('demo@example.com', $claims->email);
        $this->assertSame('user-uuid-123', $claims->sub);
    }

    public function testEmptyTokenThrowsInvalidArgument(): void
    {
        $validator = $this->makeValidator();

        $this->expectException(\InvalidArgumentException::class);
        $validator->validate('');
    }

    public function testExpiredTokenThrowsUnexpectedValue(): void
    {
        $validator = $this->makeValidator();
        $token     = $this->issueToken([], -1); // already expired

        $this->expectException(\UnexpectedValueException::class);
        $validator->validate($token);
    }

    public function testJwksFetchFailureThrowsRuntimeException(): void
    {
        $mock    = new MockHandler([new Response(503, [], 'Service Unavailable')]);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        // Force Guzzle to throw on bad responses
        $mock->reset();
        $mock->append(new \GuzzleHttp\Exception\ConnectException(
            'Connection refused',
            new \GuzzleHttp\Psr7\Request('GET', 'http://keycloak:8080/realms/auth2/protocol/openid-connect/certs'),
        ));

        $validator = new KeycloakTokenValidator('http://keycloak:8080', 'auth2', $client);

        $this->expectException(\RuntimeException::class);
        $validator->validate($this->issueToken());
    }

    public function testJwksResponseIsCached(): void
    {
        // Only one HTTP response in the mock — the second validate() call must
        // use the cached keys or it will throw (no more responses queued).
        $mock    = new MockHandler([new Response(200, [], $this->buildJwks())]);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $validator = new KeycloakTokenValidator('http://keycloak:8080', 'auth2', $client);
        $token     = $this->issueToken();

        $validator->validate($token);
        $validator->validate($token); // Must not throw (would fail if HTTP was called again)

        $this->assertTrue(true); // reached here means caching works
    }
}

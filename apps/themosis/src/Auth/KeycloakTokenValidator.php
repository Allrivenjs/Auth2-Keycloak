<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

/**
 * KeycloakTokenValidator
 *
 * Validates a Keycloak-issued JWT access token by:
 *  1. Fetching the realm's JWKS (JSON Web Key Set) from the well-known endpoint.
 *  2. Verifying the token signature against the matching public key.
 *  3. Returning the decoded claims payload on success.
 *
 * The JWKS response is cached in-memory for the lifetime of the PHP process to
 * avoid a network round-trip on every request.
 */
class KeycloakTokenValidator
{
    /** @var array<string, Key>|null In-process JWKS key cache */
    private ?array $cachedKeys = null;

    public function __construct(
        private readonly string $keycloakUrl,
        private readonly string $realm,
        private readonly Client $httpClient = new Client(),
    ) {}

    /**
     * Validate the given Bearer token and return the decoded claims.
     *
     * @param  string   $token  Raw JWT string (without "Bearer " prefix).
     * @return stdClass         Decoded JWT payload.
     *
     * @throws \InvalidArgumentException  When the token is empty.
     * @throws \UnexpectedValueException  When the token signature is invalid or expired.
     * @throws \RuntimeException          When the JWKS cannot be fetched.
     */
    public function validate(string $token): stdClass
    {
        if ($token === '') {
            throw new \InvalidArgumentException('Token must not be empty.');
        }

        $keys = $this->getKeys();

        // JWT::decode throws on invalid/expired tokens — let it propagate.
        return JWT::decode($token, $keys);
    }

    /**
     * Fetch and cache the realm's JWKS.
     *
     * @return array<string, Key>
     * @throws \RuntimeException
     */
    private function getKeys(): array
    {
        if ($this->cachedKeys !== null) {
            return $this->cachedKeys;
        }

        $jwksUrl = rtrim($this->keycloakUrl, '/')
            . "/realms/{$this->realm}/protocol/openid-connect/certs";

        try {
            $response = $this->httpClient->get($jwksUrl, ['timeout' => 5]);
            $jwks     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Could not fetch JWKS from Keycloak: {$e->getMessage()}",
                0,
                $e,
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid JWKS response from Keycloak.', 0, $e);
        }

        $this->cachedKeys = JWK::parseKeySet($jwks);

        return $this->cachedKeys;
    }
}

# Architecture — Auth2-Keycloak

## Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│  User's Browser                                                      │
│                                                                      │
│  ┌─────────────────────────┐     ┌──────────────────────────────┐   │
│  │  Next.js (port 3000)    │     │  Themosis / WP (port 8000)   │   │
│  │  - next-auth + Keycloak │     │  - REST API /wp-json/…       │   │
│  │  - JWT in server session│     │  - KeycloakAuthMiddleware     │   │
│  └────────────┬────────────┘     └──────────────┬───────────────┘   │
└───────────────┼──────────────────────────────────┼───────────────────┘
                │  OAuth2 / OIDC                   │  Bearer JWT
                │  Authorization Code Flow          │  (validated via JWKS)
                ▼                                  │
┌──────────────────────────┐                       │
│  Keycloak (port 8080)    │ ◄─────────────────────┘
│  Realm: auth2            │   GET /realms/auth2/protocol/
│  - nextjs-client         │        openid-connect/certs
│  - themosis-client       │
│  - Roles: user, admin    │
└──────────────────────────┘
```

## Authentication Flow

### 1. User logs in via Next.js

1. User visits the Next.js app and clicks **"Iniciar sesión con Keycloak"**.
2. `next-auth` redirects the browser to Keycloak's authorization endpoint using
   the **Authorization Code Flow**.
3. The user authenticates on Keycloak's hosted login page.
4. Keycloak issues an **access token** (JWT, 5 min TTL) and a **refresh token**.
5. `next-auth` stores both tokens in an encrypted server-side session cookie.

### 2. Next.js calls the Themosis REST API

1. When the Next.js profile page loads, it calls the Themosis API at  
   `GET /wp-json/auth2/v1/me`.
2. It attaches the current access token as an `Authorization: Bearer <jwt>` header.
3. Alternatively, the call can go through the server-side proxy at  
   `/api/themosis-proxy?path=/wp-json/auth2/v1/me` to avoid exposing the
   token to the browser.

### 3. Themosis validates the token

1. `KeycloakAuthMiddleware` intercepts the request via the WordPress
   `rest_authentication_errors` filter.
2. It extracts the Bearer token from the `Authorization` header.
3. `KeycloakTokenValidator` fetches the realm's JWKS once (cached in memory)
   and verifies the JWT signature + expiry using `firebase/php-jwt`.
4. On success, `WordPressSessionSync` looks up (or creates) a WordPress user
   matching the token's `email` claim and calls `wp_set_current_user()`.
5. WordPress REST API processes the request as that authenticated user.

### 4. Token refresh

When the access token expires (`next-auth` detects `Date.now() > accessTokenExpires`),
it automatically calls Keycloak's token endpoint with the stored refresh token.
If the refresh token has also expired the session is marked with
`error: "RefreshAccessTokenError"` and the middleware redirects the user to
log in again.

### 5. Sign-out

Clicking sign-out in Next.js:
1. Calls `signOut()` from `next-auth`.
2. The `signOut` event handler sends a logout request to Keycloak's
   `/protocol/openid-connect/logout` endpoint using the stored `id_token_hint`.
3. Keycloak terminates the SSO session, so subsequent requests to any client
   in the same realm will require re-authentication.

## Key Design Decisions

| Decision | Rationale |
|---|---|
| **JWT session strategy in next-auth** | Avoids the need for a shared session store between Next.js and Keycloak. |
| **JWKS validation in PHP** | Stateless — Themosis does not need to call Keycloak on every request after the initial JWKS fetch. |
| **In-memory JWKS cache** | Trades off key-rotation latency for performance. For production, add a TTL-based cache (e.g. Redis, APCu). |
| **WordPress user sync** | Preserves compatibility with existing WordPress plugins and themes that rely on `wp_get_current_user()`. |
| **Server-side Themosis proxy** | Keeps the Bearer token out of browser storage / network logs when called from Next.js server components. |

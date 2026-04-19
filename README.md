# Auth2-Keycloak

Multiplatform authentication system that maintains a shared login session
between a **Themosis / WordPress** backend and a **Next.js** frontend using
[Keycloak](https://www.keycloak.org/) as the central identity provider.

## Architecture

```
[Browser]
   │
   ├─ Next.js (port 3000) ──OAuth2 OIDC──► Keycloak (port 8080)
   │       │                                      │
   │       │ Bearer JWT                   JWKS endpoint
   │       ▼                                      │
   └─ Themosis / WordPress (port 8000) ───────────┘
            Validates token, syncs WP user session
```

See [`docs/architecture.md`](docs/architecture.md) for the detailed flow.

## Quick Start

### Prerequisites

- Docker ≥ 24 with Docker Compose v2
- Node.js ≥ 20 (for local Next.js development)
- PHP ≥ 8.1 + Composer (for local Themosis development)

### 1. Start all services

```bash
docker compose up
```

This starts:
| Service | URL |
|---|---|
| Keycloak | http://localhost:8080 (admin / admin) |
| Next.js | http://localhost:3000 |
| Themosis / WordPress | http://localhost:8000 |

The `auth2` realm is automatically imported from
[`keycloak/realm-export.json`](keycloak/realm-export.json) with two clients
(`nextjs-client` and `themosis-client`) and a demo user:

| Field | Value |
|---|---|
| Username | `demo` |
| Password | `demo123` |
| Roles | `user` |

### 2. Try it out

1. Open http://localhost:3000.
2. Click **"Iniciar sesión con Keycloak"**.
3. Log in with `demo` / `demo123`.
4. Navigate to http://localhost:3000/profile.
5. The profile page shows your Keycloak claims **and** the matching WordPress
   user returned by the Themosis REST API — demonstrating that both platforms
   share the same session.

## Project Structure

```
.
├── docker-compose.yml          # Keycloak + Next.js + Themosis services
├── keycloak/
│   └── realm-export.json       # Pre-configured Keycloak realm (auto-imported)
├── apps/
│   ├── nextjs/                 # Next.js 14 frontend (next-auth + Keycloak)
│   │   ├── src/
│   │   │   ├── app/            # App Router pages & API routes
│   │   │   ├── components/     # AuthStatus, Providers
│   │   │   ├── lib/            # themosis-api.ts helper
│   │   │   └── middleware.ts   # Route protection
│   │   └── __tests__/
│   └── themosis/               # PHP / Themosis integration package
│       ├── src/
│       │   ├── Auth/           # KeycloakTokenValidator, WordPressSessionSync
│       │   ├── Middleware/     # KeycloakAuthMiddleware (WP REST hooks)
│       │   └── Providers/      # KeycloakServiceProvider
│       └── tests/
└── docs/
    └── architecture.md         # Detailed architecture & flow diagrams
```

## Local Development

### Next.js

```bash
cd apps/nextjs
cp .env.example .env.local
npm install
npm run dev
```

### Themosis PHP package

```bash
cd apps/themosis
composer install
composer test
```

### Register the service provider in Themosis

In your Themosis application's `config/app.php`:

```php
'providers' => [
    Auth2\Keycloak\Providers\KeycloakServiceProvider::class,
],
```

Set the environment variables (see `apps/themosis/.env.example`).

## Running Tests

### Next.js

```bash
cd apps/nextjs && npm test
```

### PHP

```bash
cd apps/themosis && composer test
```

## Environment Variables

### Next.js (`apps/nextjs/.env.local`)

| Variable | Description |
|---|---|
| `NEXTAUTH_URL` | Public URL of the Next.js app |
| `NEXTAUTH_SECRET` | Secret used to encrypt the session cookie |
| `KEYCLOAK_CLIENT_ID` | Keycloak client ID (`nextjs-client`) |
| `KEYCLOAK_CLIENT_SECRET` | Keycloak client secret |
| `KEYCLOAK_ISSUER` | Keycloak realm issuer URL |
| `NEXT_PUBLIC_KEYCLOAK_URL` | Browser-accessible Keycloak URL |
| `NEXT_PUBLIC_KEYCLOAK_REALM` | Keycloak realm name |

### Themosis (`apps/themosis/.env`)

| Variable | Description |
|---|---|
| `KEYCLOAK_URL` | Internal Keycloak URL |
| `KEYCLOAK_REALM` | Keycloak realm name |
| `KEYCLOAK_CLIENT_ID` | Keycloak client ID (`themosis-client`) |
| `KEYCLOAK_CLIENT_SECRET` | Keycloak client secret |
| `ALLOWED_ORIGIN` | CORS origin for the Next.js app |

import { NextAuthOptions } from "next-auth";
import KeycloakProvider from "next-auth/providers/keycloak";
import { JWT } from "next-auth/jwt";

/**
 * Decode the roles claim from a Keycloak JWT without verifying the signature.
 * This is safe here because the token has just been obtained from Keycloak's
 * token endpoint over HTTPS — we trust the source.
 */
function extractRoles(accessToken: string): string[] {
  try {
    const payload = JSON.parse(
      Buffer.from(accessToken.split(".")[1], "base64url").toString("utf8")
    ) as Record<string, unknown>;
    return Array.isArray(payload.roles) ? (payload.roles as string[]) : [];
  } catch {
    return [];
  }
}

/**
 * Refreshes an expired Keycloak access token using the stored refresh token.
 * Returns the updated token on success, or the original token marked as errored
 * so the client can force a new login.
 */
async function refreshAccessToken(token: JWT): Promise<JWT> {
  try {
    const url = `${process.env.KEYCLOAK_ISSUER}/protocol/openid-connect/token`;

    const response = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        client_id: process.env.KEYCLOAK_CLIENT_ID!,
        client_secret: process.env.KEYCLOAK_CLIENT_SECRET!,
        grant_type: "refresh_token",
        refresh_token: token.refreshToken as string,
      }),
    });

    const refreshed = await response.json();

    if (!response.ok) {
      throw refreshed;
    }

    return {
      ...token,
      accessToken: refreshed.access_token,
      accessTokenExpires: Date.now() + refreshed.expires_in * 1000,
      refreshToken: refreshed.refresh_token ?? token.refreshToken,
      // Refresh roles so that Keycloak role changes take effect without
      // requiring the user to sign out and sign back in.
      roles: extractRoles(refreshed.access_token),
      error: undefined,
    };
  } catch {
    return { ...token, error: "RefreshAccessTokenError" };
  }
}

export const authOptions: NextAuthOptions = {
  providers: [
    KeycloakProvider({
      clientId: process.env.KEYCLOAK_CLIENT_ID!,
      clientSecret: process.env.KEYCLOAK_CLIENT_SECRET!,
      issuer: process.env.KEYCLOAK_ISSUER!,
      authorization: {
        url: `${process.env.KEYCLOAK_PUBLIC_ISSUER}/protocol/openid-connect/auth`,
        params: { scope: "openid email profile" },
      },
    }),
  ],

  session: {
    strategy: "jwt",
    // Keep the session alive as long as Keycloak's SSO session max lifespan.
    maxAge: 10 * 60 * 60, // 10 hours — matches realm ssoSessionMaxLifespan
  },

  callbacks: {
    /**
     * Persist the Keycloak access token, refresh token and expiry in the JWT
     * so they are available server-side and can be passed to downstream APIs
     * (e.g. the Themosis backend) as a Bearer token.
     */
    async jwt({ token, account, profile }) {
      // Initial sign-in: store tokens from Keycloak
      if (account) {
        token.accessToken = account.access_token;
        token.refreshToken = account.refresh_token;
        token.accessTokenExpires =
          Date.now() + (account.expires_in as number) * 1000;
        token.idToken = account.id_token;
        // Persist Keycloak roles from the profile
        token.roles = ((profile as Record<string, unknown>)?.roles as string[]) ?? [];
        return token;
      }

      // Token still valid — return as-is
      if (Date.now() < (token.accessTokenExpires as number)) {
        return token;
      }

      // Token expired — attempt refresh
      return refreshAccessToken(token);
    },

    /**
     * Expose the access token and roles to the client-side session so that
     * components can attach it as a Bearer token when calling the Themosis API.
     */
    async session({ session, token }) {
      session.accessToken = token.accessToken as string;
      session.error = token.error as string | undefined;
      if (session.user) {
        (session.user as Record<string, unknown>).roles = token.roles;
      }
      return session;
    },
  },

  events: {
    /**
     * On sign-out, also end the Keycloak session so that the user is fully
     * logged out from all platforms sharing this realm.
     */
    async signOut({ token }) {
      if (token.idToken) {
        const logoutUrl =
          `${process.env.KEYCLOAK_ISSUER}/protocol/openid-connect/logout` +
          `?id_token_hint=${token.idToken}` +
          `&post_logout_redirect_uri=${encodeURIComponent(process.env.NEXTAUTH_URL!)}`;

        await fetch(logoutUrl).catch(() => {
          // Best-effort logout; do not break the sign-out flow on error
        });
      }
    },
  },

  pages: {
    signIn: "/auth/signin",
    error: "/auth/error",
  },
};

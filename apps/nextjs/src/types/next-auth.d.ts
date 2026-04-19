/**
 * Augment next-auth types to include our custom fields.
 */
import "next-auth";
import "next-auth/jwt";

declare module "next-auth" {
  interface Session {
    /** Keycloak access token — attach as Bearer to calls to the Themosis API */
    accessToken?: string;
    /** Set to "RefreshAccessTokenError" when the refresh token has also expired */
    error?: string;
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    accessToken?: string;
    refreshToken?: string;
    idToken?: string;
    accessTokenExpires?: number;
    roles?: string[];
    error?: string;
  }
}

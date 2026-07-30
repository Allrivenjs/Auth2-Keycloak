import { withAuth } from "next-auth/middleware";
import { NextResponse } from "next/server";

/**
 * Middleware that protects all routes under /profile and /dashboard.
 *
 * If a visitor is not authenticated they are redirected to the sign-in page.
 * If their session token contains a RefreshAccessTokenError the session is
 * invalidated so they are forced to log in again through Keycloak.
 */
export default withAuth(
  function middleware(req) {
    const token = req.nextauth.token;

    // Force a fresh login when the refresh token has also expired
    if (token?.error === "RefreshAccessTokenError") {
      const signInUrl = new URL("/api/auth/signin", req.url);
      signInUrl.searchParams.set("callbackUrl", req.url);
      return NextResponse.redirect(signInUrl);
    }

    return NextResponse.next();
  },
  {
    callbacks: {
      authorized: ({ token }) => !!token,
    },
  }
);

export const config = {
  matcher: ["/profile/:path*", "/dashboard/:path*"],
};

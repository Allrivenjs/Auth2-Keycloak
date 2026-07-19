import { getServerSession } from "next-auth";
import { NextRequest, NextResponse } from "next/server";
import { authOptions } from "@/lib/authOptions";

const THEMOSIS_BASE_URL =
  process.env.THEMOSIS_API_URL ?? "http://localhost:8000";

/**
 * A lightweight server-side proxy that forwards requests to the Themosis API
 * with the current user's Keycloak access token attached.
 *
 * Usage from the browser:
 *   GET /api/themosis-proxy?path=/wp-json/auth2/v1/me
 *
 * This avoids exposing the Bearer token to client-side JavaScript while still
 * allowing server components / API routes to call Themosis on behalf of the
 * authenticated user.
 */
export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);

  if (!session?.accessToken) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const path = req.nextUrl.searchParams.get("path");
  if (!path) {
    return NextResponse.json({ error: "Missing 'path' query param" }, { status: 400 });
  }

  // Restrict to wp-json paths to prevent open proxy abuse
  if (!path.startsWith("/wp-json/")) {
    return NextResponse.json({ error: "Forbidden path" }, { status: 403 });
  }

  const upstream = await fetch(`${THEMOSIS_BASE_URL}${path}`, {
    headers: {
      Authorization: `Bearer ${session.accessToken}`,
      Accept: "application/json",
    },
  });

  const body = await upstream.text();
  return new NextResponse(body, {
    status: upstream.status,
    headers: { "Content-Type": upstream.headers.get("Content-Type") ?? "application/json" },
  });
}

import { Session } from "next-auth";

const THEMOSIS_BASE_URL =
  process.env.NEXT_PUBLIC_THEMOSIS_API_URL ?? "http://localhost:8000";

/**
 * Thin wrapper around fetch that automatically attaches the Keycloak access
 * token as a Bearer header when calling the Themosis REST API.
 *
 * @example
 * const data = await themosisApi(session).get("/wp-json/auth2/v1/me");
 */
export function themosisApi(session: Session | null) {
  const headers: HeadersInit = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };

  if (session?.accessToken) {
    headers["Authorization"] = `Bearer ${session.accessToken}`;
  }

  return {
    get: (path: string) =>
      fetch(`${THEMOSIS_BASE_URL}${path}`, { headers }),

    post: (path: string, body: unknown) =>
      fetch(`${THEMOSIS_BASE_URL}${path}`, {
        method: "POST",
        headers,
        body: JSON.stringify(body),
      }),

    put: (path: string, body: unknown) =>
      fetch(`${THEMOSIS_BASE_URL}${path}`, {
        method: "PUT",
        headers,
        body: JSON.stringify(body),
      }),

    delete: (path: string) =>
      fetch(`${THEMOSIS_BASE_URL}${path}`, { method: "DELETE", headers }),
  };
}

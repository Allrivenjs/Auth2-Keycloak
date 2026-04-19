"use client";

import { useSession, signIn } from "next-auth/react";
import { useEffect, useState } from "react";
import { themosisApi } from "@/lib/themosis-api";

interface WpUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
}

/**
 * Protected profile page.
 *
 * - Redirects to sign-in if there is no active session.
 * - Calls the Themosis REST endpoint GET /wp-json/auth2/v1/me with the
 *   Keycloak access token to demonstrate cross-platform session sharing.
 */
export default function ProfilePage() {
  const { data: session, status } = useSession();
  const [wpUser, setWpUser] = useState<WpUser | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (status === "unauthenticated") {
      signIn("keycloak");
    }
  }, [status]);

  useEffect(() => {
    if (!session?.accessToken) return;

    setLoading(true);
    themosisApi(session)
      .get("/wp-json/auth2/v1/me")
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then((data: WpUser) => setWpUser(data))
      .catch((err: Error) => setError(err.message))
      .finally(() => setLoading(false));
  }, [session]);

  if (status === "loading") return <p>Cargando…</p>;
  if (!session) return null;

  return (
    <main style={{ fontFamily: "sans-serif", maxWidth: 600, margin: "4rem auto", padding: "0 1rem" }}>
      <h1>Perfil</h1>

      <section>
        <h2>Sesión Next.js (Keycloak)</h2>
        <p>
          <strong>Email:</strong> {session.user?.email}
        </p>
        <p>
          <strong>Nombre:</strong> {session.user?.name}
        </p>
        <p>
          <strong>Roles:</strong>{" "}
          {((session.user as Record<string, unknown>)?.roles as string[] | undefined)?.join(", ") ?? "—"}
        </p>
      </section>

      <section style={{ marginTop: "2rem" }}>
        <h2>Sesión Themosis (WordPress)</h2>
        {loading && <p>Consultando Themosis…</p>}
        {error && <p style={{ color: "red" }}>Error al consultar Themosis: {error}</p>}
        {wpUser && (
          <>
            <p>
              <strong>ID WordPress:</strong> {wpUser.id}
            </p>
            <p>
              <strong>Nombre:</strong> {wpUser.name}
            </p>
            <p>
              <strong>Email:</strong> {wpUser.email}
            </p>
            <p>
              <strong>Roles WP:</strong> {wpUser.roles.join(", ")}
            </p>
          </>
        )}
      </section>
    </main>
  );
}

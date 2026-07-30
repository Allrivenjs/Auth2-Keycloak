"use client";

import { useSession, signIn, signOut } from "next-auth/react";

/**
 * Displays the current authentication state and provides sign-in / sign-out
 * buttons.  If the session has a RefreshAccessTokenError the component nudges
 * the user to log in again.
 */
export default function AuthStatus() {
  const { data: session, status } = useSession();

  if (status === "loading") {
    return <p className="auth-status">Cargando sesión…</p>;
  }

  if (session?.error === "RefreshAccessTokenError") {
    return (
      <div className="auth-status auth-status--error">
        <p>Tu sesión ha expirado.</p>
        <button onClick={() => signIn("keycloak")}>Volver a iniciar sesión</button>
      </div>
    );
  }

  if (session) {
    return (
      <div className="auth-status auth-status--signed-in">
        <p>
          Sesión activa: <strong>{session.user?.email}</strong>
        </p>
        <button onClick={() => signOut({ callbackUrl: "/" })}>
          Cerrar sesión
        </button>
      </div>
    );
  }

  return (
    <div className="auth-status auth-status--signed-out">
      <p>No has iniciado sesión.</p>
      <button onClick={() => signIn("keycloak")}>Iniciar sesión con Keycloak</button>
    </div>
  );
}

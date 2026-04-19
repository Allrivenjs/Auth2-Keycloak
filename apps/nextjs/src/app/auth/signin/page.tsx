"use client";

import { signIn } from "next-auth/react";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";

function SignInContent() {
  const params = useSearchParams();
  const callbackUrl = params.get("callbackUrl") ?? "/";

  return (
    <main style={{ fontFamily: "sans-serif", maxWidth: 400, margin: "8rem auto", padding: "0 1rem", textAlign: "center" }}>
      <h1>Iniciar sesión</h1>
      <p>Utiliza tu cuenta de Keycloak para acceder.</p>
      <button
        style={{ marginTop: "1.5rem", padding: "0.75rem 2rem", fontSize: "1rem", cursor: "pointer" }}
        onClick={() => signIn("keycloak", { callbackUrl })}
      >
        Continuar con Keycloak
      </button>
    </main>
  );
}

export default function SignInPage() {
  return (
    <Suspense>
      <SignInContent />
    </Suspense>
  );
}

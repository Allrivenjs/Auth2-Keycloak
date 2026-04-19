"use client";

import { useSearchParams } from "next/navigation";
import { Suspense } from "react";

const errorMessages: Record<string, string> = {
  Configuration: "Error de configuración del servidor. Contacta al administrador.",
  AccessDenied: "Acceso denegado.",
  Verification: "El enlace de verificación expiró o ya fue usado.",
  Default: "Ocurrió un error al iniciar sesión. Intenta nuevamente.",
};

function ErrorContent() {
  const params = useSearchParams();
  const error = params.get("error") ?? "Default";
  const message = errorMessages[error] ?? errorMessages.Default;

  return (
    <main style={{ fontFamily: "sans-serif", maxWidth: 400, margin: "8rem auto", padding: "0 1rem", textAlign: "center" }}>
      <h1>Error de autenticación</h1>
      <p style={{ color: "red" }}>{message}</p>
      <a href="/auth/signin">Volver a intentar</a>
    </main>
  );
}

export default function ErrorPage() {
  return (
    <Suspense>
      <ErrorContent />
    </Suspense>
  );
}

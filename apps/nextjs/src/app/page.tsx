import AuthStatus from "@/components/AuthStatus";

/**
 * Home page — shows the authentication status and links to the profile page.
 */
export default function HomePage() {
  return (
    <main style={{ fontFamily: "sans-serif", maxWidth: 600, margin: "4rem auto", padding: "0 1rem" }}>
      <h1>Auth2-Keycloak</h1>
      <p>Sistema de autenticación multiplataforma entre Themosis y Next.js.</p>
      <AuthStatus />
      <nav style={{ marginTop: "2rem" }}>
        <a href="/profile">Ver perfil →</a>
      </nav>
    </main>
  );
}

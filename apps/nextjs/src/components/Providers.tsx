"use client";

import { SessionProvider } from "next-auth/react";
import type { Session } from "next-auth";
import type { ReactNode } from "react";

interface ProvidersProps {
  children: ReactNode;
  session?: Session | null;
}

/**
 * Wraps the application in NextAuth's SessionProvider so that any client
 * component can call useSession() to access the current session.
 */
export default function Providers({ children, session }: ProvidersProps) {
  return <SessionProvider session={session}>{children}</SessionProvider>;
}

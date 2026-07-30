import NextAuth from "next-auth";
import { authOptions } from "@/lib/authOptions";

/**
 * Named GET/POST exports are required by the Next.js App Router.
 */
const handler = NextAuth(authOptions);
export { handler as GET, handler as POST };

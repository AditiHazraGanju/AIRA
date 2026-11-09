// "use client";

// import { useAuth } from "@/context/AuthContext";
// import { useRouter, usePathname } from "next/navigation";
// import { useEffect } from "react";

// /**
//  * Protects a page from unauthenticated access.
//  * Redirects to /login if not authenticated — but ONLY if not already on
//  * an auth route, to avoid redirect loops with AppLayout's flow check.
//  */
// export function useProtected() {
//   const { isAuthenticated } = useAuth();
//   const router = useRouter();
//   const pathname = usePathname();

//   const authRoutes = [
//     "/login",
//     "/otp-verification",
//     "/otp-verified",
//     "/registration",
//     "/registration-successful",
//     "/user-registration",
//     "/forgot-password",
//     "/reset-password",
//     "/onboarding",
//   ];

//   const isOnAuthRoute = authRoutes.some((route) =>
//     pathname?.toLowerCase().includes(route.toLowerCase())
//   );

//   useEffect(() => {
//     if (!isAuthenticated && !isOnAuthRoute) {
//       router.push("/login");
//     }
//   }, [isAuthenticated, isOnAuthRoute, router]);

//   return isAuthenticated;
// }


"use client";

import { useAuth } from "@/context/AuthContext";
import { useRouter, usePathname } from "next/navigation";
import { useEffect } from "react";

/**
 * Protects a page from unauthenticated access.
 * Redirects to /login if not authenticated while preserving
 * the current path and query parameters.
 */
export function useProtected() {
  const { isAuthenticated } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  const authRoutes = [
    "/login",
    "/otp-verification",
    "/otp-verified",
    "/registration",
    "/registration-successful",
    "/user-registration",
    "/forgot-password",
    "/reset-password",
    "/onboarding",
  ];

  const isOnAuthRoute = authRoutes.some((route) =>
    pathname?.toLowerCase().includes(route.toLowerCase())
  );

  useEffect(() => {
    if (!isAuthenticated && !isOnAuthRoute) {
      const currentPath = `${window.location.pathname}${window.location.search}`;

      router.push(
        `/login?redirect=${encodeURIComponent(currentPath)}`
      );
    }
  }, [isAuthenticated, isOnAuthRoute, router]);

  return isAuthenticated;
}
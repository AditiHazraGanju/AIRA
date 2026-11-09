"use client";

import { useEffect, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import OTPVerified from "@/components/auth/OTPVerified";
import { useAuth } from "@/context/AuthContext";
import { getSafeRedirect } from "@/utils/url-helper";

// 1. Extract the logic into a sub-component
function OTPVerifiedContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setIsAuthenticated } = useAuth();

  useEffect(() => {
    const needsRegistration = searchParams.get("needsRegistration") === "true";
    const rawRedirect = searchParams.get("redirect");
    const redirect = getSafeRedirect(rawRedirect);

    const timer = setTimeout(() => {
      setIsAuthenticated(true);

      if (needsRegistration) {
        const registrationUrl = redirect
          ? `/registration?redirect=${encodeURIComponent(redirect)}`
          : "/registration";
        router.push(registrationUrl);
        return;
      }

      if (redirect) {
        router.push(redirect);
        return;
      }

      router.push("/");
    }, 3000);

    return () => clearTimeout(timer);
  }, [router, setIsAuthenticated, searchParams]);

  return <OTPVerified />;
}

// 2. Wrap the sub-component in Suspense in the main Page export
export default function OTPVerifiedPage() {
  return (
    <Suspense fallback={<OTPVerified />}>
      <OTPVerifiedContent />
    </Suspense>
  );
}

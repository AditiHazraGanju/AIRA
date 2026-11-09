"use client";

import { useState, useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Header } from "./Header";
import { useAuth } from "@/context/AuthContext";

interface AppLayoutProps {
  children: React.ReactNode;
}

export function AppLayout({ children }: AppLayoutProps) {
  const pathname = usePathname();
  const router = useRouter();
  const { isAuthenticated, isAuthLoading } = useAuth();
  
  const authRoutes = [
    "/login",
    "/otp-verification",
    "/otp-verified",
    "/registration",
  ];

  const isAuthPage = authRoutes.some((r) =>
    pathname?.toLowerCase().includes(r.toLowerCase())
  );

  useEffect(() => {
    if (isAuthLoading) return;

    if (!isAuthenticated && !isAuthPage) {
      if (sessionStorage.getItem("intentional_logout") === "true") {
        sessionStorage.removeItem("intentional_logout");
        router.replace("/login");
      } else {
        const currentPath = `${pathname}${window.location.search}`;
        router.replace(`/login?redirect=${encodeURIComponent(currentPath)}`);
      }
    }
  }, [isAuthenticated, isAuthLoading, isAuthPage, pathname, router]);

  useEffect(() => {
    if (typeof window !== "undefined" && "scrollRestoration" in window.history) {
      window.history.scrollRestoration = "manual";
    }
  }, []);

  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: "instant" });
  }, [pathname]);

  const shouldShowHeader = !isAuthPage;

  return (
    <>
      <div className="flex flex-col min-h-screen opacity-100 transition-opacity duration-700">
        <div className={`${shouldShowHeader ? "block" : "hidden"} md:block`}>
          <Header />
        </div>

        <main className={`flex-grow ${shouldShowHeader ? "pt-[76px]" : "pt-0"} md:pt-16`}>
          {isAuthLoading && !isAuthPage ? null : children}
        </main>
      </div>
    </>
  );
}

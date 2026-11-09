"use client";

import React, { createContext, useContext, useState, useCallback, useEffect } from "react";

interface AuthContextType {
  phoneNumber: string;
  setPhoneNumber: (phone: string) => void;
  otpSent: boolean;
  setOtpSent: (sent: boolean) => void;
  isAuthenticated: boolean;
  setIsAuthenticated: (auth: boolean) => void;
  isAuthLoading: boolean;
  reset: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [phoneNumber, setPhoneNumber] = useState("");
  const [otpSent, setOtpSent] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  // True until we've checked localStorage — prevents flash-redirect on refresh.
  const [isAuthLoading, setIsAuthLoading] = useState(true);

  // Hydrate auth state from localStorage on first mount.
  useEffect(() => {
    const syncAuth = () => {
      const token = localStorage.getItem("auth_token");
      setIsAuthenticated(!!token);
    };

    syncAuth();
    setIsAuthLoading(false);

    // Keep in sync when clearUserStorage() fires (e.g. logout in another hook).
    window.addEventListener("auth_storage_updated", syncAuth);
    return () => window.removeEventListener("auth_storage_updated", syncAuth);
  }, []);

  const reset = useCallback(() => {
    setPhoneNumber("");
    setOtpSent(false);
    setIsAuthenticated(false);
  }, []); 

  return (
    <AuthContext.Provider
      value={{
        phoneNumber,
        setPhoneNumber,
        otpSent,
        setOtpSent,
        isAuthenticated,
        setIsAuthenticated,
        isAuthLoading,
        reset,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}

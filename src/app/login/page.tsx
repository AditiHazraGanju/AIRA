"use client";

import { useState, useRef, useEffect, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Image from "next/image";
import { TranslateIcon } from "@/components/ui/Icons";
import { LanguageModal } from "@/components/ui/LanguageModal";
import { useAuth } from "@/context/AuthContext";
import { useLanguage } from "@/context/LanguageContext";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { SEND_OTP_API, getSafeRedirect } from "@/utils/url-helper";
import toast from "react-hot-toast";

function LoginPageContent() {
  const [phoneNumber, setPhoneNumber] = useState("");
  const [isLanguageOpen, setIsLanguageOpen] = useState(false);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get("redirect");
  const redirect = getSafeRedirect(rawRedirect);

  const { setPhoneNumber: setContextPhoneNumber, setOtpSent } = useAuth();
  const { language, setLanguage, t } = useLanguage();
  const desktopDropdownRef = useRef<HTMLDivElement>(null);
  const mobileDropdownRef = useRef<HTMLDivElement>(null);

  // Close desktop dropdown on outside click
  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      const target = e.target as Node;
      if (
        (desktopDropdownRef.current && desktopDropdownRef.current.contains(target)) ||
        (mobileDropdownRef.current && mobileDropdownRef.current.contains(target))
      ) {
        return;
      }
      setIsLanguageOpen(false);
    }
    if (isLanguageOpen) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isLanguageOpen]);

  const handleGetOTP = async () => {
    setError("");

    const phoneRegex = /^[0-9]{10}$/;
    const cleanPhone = phoneNumber.replace(/\D/g, "");

    if (!cleanPhone || !phoneRegex.test(cleanPhone)) {
      setError("Please enter a valid 10-digit phone number");
      return;
    }

    setIsLoading(true);

    try {
      const payload = { mobile_no: cleanPhone, role_slug: "ai-chatbot", channel: "sms" };
      const response = await apiHandler.post(apiClient1, SEND_OTP_API, payload);

      if (response?.success) {
        toast.success("OTP sent successfully!");
      } else {
        setError(response?.message || "Failed to send OTP. Please try again.");
        return;
      }

      setContextPhoneNumber(cleanPhone);
      setOtpSent(true);

      const otpUrl = redirect
        ? `/otp-verification?redirect=${encodeURIComponent(redirect)}`
        : "/otp-verification";

      router.push(otpUrl);
    } catch (err) {
      setError("Failed to send OTP. Please try again.");
      console.error("Error sending OTP:", err);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="h-[100dvh] md:h-auto md:min-h-[calc(100vh-4rem)] flex overflow-hidden">
      {/* Left Side - Illustration (Desktop Only) */}
      <div className="hidden md:flex lg:w-1/2 bg-tertiary items-center justify-center relative overflow-hidden">
        <div className="relative w-full h-full flex items-end justify-center">
          <Image
            src="/images/woman.png"
            alt="Welcome illustration"
            width={1600}
            height={2200}
            className="object-cover w-full h-full"
            priority
          />
        </div>
      </div>

      {/* Right Side - Login Form */}
      <div className="w-full lg:w-1/2 bg-secondary flex flex-col lg:items-center lg:justify-center relative">

        {/* Desktop Language Selector - Top Right */}
        <div className="absolute top-8 right-8 hidden md:block" ref={desktopDropdownRef}>
          <div className="relative">
            <button
              onClick={() => setIsLanguageOpen(!isLanguageOpen)}
              className="flex items-center rounded-lg overflow-hidden"
            >
              <div className="bg-primary px-3 py-2.5 flex items-center">
                <TranslateIcon />
              </div>
              <div className="bg-tertiary transition-colors flex items-center gap-2 px-3 py-3">
                <span className="text-primary font-medium text-sm">{language}</span>
                <svg
                  className={`w-4 h-4 text-primary transition-transform ${isLanguageOpen ? "rotate-180" : ""}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </button>
            <LanguageModal
              isOpen={isLanguageOpen}
              language={language}
              onSelect={setLanguage}
              onClose={() => setIsLanguageOpen(false)}
              isMobile={false}
            />
          </div>
        </div>

        {/* Mobile Language Button */}
        <div className="md:hidden absolute top-4 right-4 z-50" ref={mobileDropdownRef}>
          <button
            onClick={() => setIsLanguageOpen(!isLanguageOpen)}
            className="w-12 h-12 rounded-xl bg-[#28321A] flex items-center justify-center hover:bg-[#8aad4a] transition-colors"
          >
            <TranslateIcon />
          </button>
          <LanguageModal
            isOpen={isLanguageOpen}
            language={language}
            onSelect={setLanguage}
            onClose={() => setIsLanguageOpen(false)}
            isMobile={true}
          />
        </div>

        {/* Login Form */}
        <div className="w-full max-w-md px-6 py-8 lg:py-12 pt-24 lg:pt-12 flex flex-col h-full lg:h-auto">
          <div className="space-y-8 flex flex-col flex-1 justify-center">
            {/* Heading */}
            <div className="space-y-3">
              <h1 className="text-2xl md:text-3xl font-bold text-primary">
                {t("login_welcome")}
              </h1>
              <p className="text-primary text-lg">
                {t("login_otp")}
              </p>
            </div>

            {/* Phone Input */}
            <div className="space-y-6">
              <input
                type="tel"
                value={phoneNumber}
                onChange={(e) => setPhoneNumber(e.target.value)}
                placeholder="+91 98765 43210"
                disabled={isLoading}
                maxLength={10}
                className="w-full px-6 py-4 bg-transparent border-2 border-primary rounded-2xl text-primary text-lg placeholder-gray-500 focus:outline-none focus:border-primary transition-colors disabled:opacity-50"
              />
              {error && <p className="text-red-500 text-sm">{error}</p>}
            </div>

            {/* Get OTP Button */}
            <div className="flex-1 flex flex-col justify-end">
              <button
                onClick={handleGetOTP}
                disabled={isLoading || phoneNumber.replace(/\D/g, "").length !== 10}
                className="w-full py-4 bg-primary text-tertiary font-semibold text-lg rounded-2xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? t("login_sending_btn") : t("login_get_otp_btn")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-tertiary" />}>
      <LoginPageContent />
    </Suspense>
  );
}

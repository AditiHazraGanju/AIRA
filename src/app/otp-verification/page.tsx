"use client";

import { useState, useRef, useEffect, KeyboardEvent, Suspense } from "react";
import Image from "next/image";
import { useRouter, useSearchParams } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import { useLanguage } from "@/context/LanguageContext";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { SEND_OTP_API, CHATBOT_VERIFY_OTP_API, getSafeRedirect } from "@/utils/url-helper";
import { setUserName } from "@/utils/user-storage";
import toast from "react-hot-toast";

// Types

interface ChatbotVerifyOtpUserData {
  sh_user_id: number;
  name: string | null;
  saathi_beneficiary_id?: number;
  saathi_ce_id?: number;
}

interface ChatbotVerifyOtpData {
  user_type: "beneficiary" | "ce" | "beneficiary_and_ce" | "user" | "guest";
  is_registered: boolean;
  user_data: ChatbotVerifyOtpUserData | null;
  token: string | null;
}

function OTPVerificationContent() {
  const [otp, setOtp] = useState<string[]>(new Array(6).fill(""));
  const [timer, setTimer] = useState(597); // 9:57 in seconds
  const [error, setError] = useState("");
  const [isVerifying, setIsVerifying] = useState(false);
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get("redirect");
  const redirect = getSafeRedirect(rawRedirect);

  const { phoneNumber, setPhoneNumber: setContextPhoneNumber } = useAuth();
  const { t } = useLanguage();

  // Format phone number for display
  const formatPhoneNumber = (phone: string) => {
    if (!phone) return "XXXXX XXXXX";
    return phone.replace(/(\d{5})(\d{5})/, "$1 $2");
  };

  // Auto-focus first input on mount
  useEffect(() => {
    inputRefs.current[0]?.focus();
  }, []);

  // Timer countdown
  useEffect(() => {
    if (timer > 0) {
      const interval = setInterval(() => {
        setTimer((prev) => prev - 1);
      }, 1000);
      return () => clearInterval(interval);
    }
  }, [timer]);

  // Format timer as MM:SS
  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
  };

  // Handle OTP input change
  const handleChange = (index: number, value: string) => {
    if (value && !/^\d+$/.test(value)) return;
    if (error) setError("");

    const newOtp = [...otp];
    newOtp[index] = value.slice(-1);
    setOtp(newOtp);

    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  // Handle backspace
  const handleKeyDown = (index: number, e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace" && !otp[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  // Handle paste
  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData("text").slice(0, 6);
    if (!/^\d+$/.test(pastedData)) return;

    const newOtp = [...otp];
    pastedData.split("").forEach((char, index) => {
      if (index < 6) newOtp[index] = char;
    });
    setOtp(newOtp);

    const nextIndex = Math.min(pastedData.length, 5);
    inputRefs.current[nextIndex]?.focus();
  };

  // Verify OTP using the chatbot API

  const handleVerify = async () => {
    const otpString = otp.join("");

    if (otpString.length !== 6) {
      setError("Please enter all 6 digits");
      return;
    }

    setIsVerifying(true);

    try {
      const payload = {
        mobile_no: phoneNumber,
        otp: otpString,
      };

      const response = await apiHandler.post<ChatbotVerifyOtpData>(
        apiClient1,
        CHATBOT_VERIFY_OTP_API,
        payload,
      );

      if (!response.success) {
        setError(response.message || "Failed to verify OTP.");
        return;
      }

      toast.success("OTP verified successfully!");

      const { user_type, is_registered, user_data, token } = response.data;

      if (is_registered && user_data && token) {
        // User exists — store auth data
        localStorage.setItem("auth_token", token);
        localStorage.setItem("sh_user_id", String(user_data.sh_user_id));
        localStorage.setItem("user_type", user_type);

        if (user_data.name) {
          setUserName(user_data.name);
        }

        if (user_data.saathi_beneficiary_id) {
          localStorage.setItem("saathi_beneficiary_id", String(user_data.saathi_beneficiary_id));
        }

        if (user_data.saathi_ce_id) {
          localStorage.setItem("saathi_ce_id", String(user_data.saathi_ce_id));
        }

        // Navigate to otp-verified screen which will then redirect appropriately
        const params = new URLSearchParams({ needsRegistration: "false" });
        if (redirect) {
          params.set("redirect", redirect);
        }
        router.push(`/otp-verified?${params.toString()}`);
      } else {
        // Guest user — needs registration (name only)
        // Store phone number for the registration page
        localStorage.setItem("chatbot_guest_phone", phoneNumber);

        const params = new URLSearchParams({ needsRegistration: "true" });
        if (redirect) {
          params.set("redirect", redirect);
        }
        router.push(`/otp-verified?${params.toString()}`);
      }
    } catch {
      setError("Invalid OTP. Please try again.");
    } finally {
      setIsVerifying(false);
    }
  };

  // Handlers

  const handleChangeNumber = () => {
    setContextPhoneNumber("");
    const loginUrl = redirect
      ? `/login?redirect=${encodeURIComponent(redirect)}`
      : "/login";
    router.push(loginUrl);
  };

  const handleResend = async () => {
    try {
      const payload = {
        mobile_no: phoneNumber,
        role_slug: "ai-chatbot",
        channel: "sms"
      };

      const response = await apiHandler.post(apiClient1, SEND_OTP_API, payload);

      if (response.success) {
        toast.success("OTP sent successfully!");
      } else {
        setError(response.message || "Failed to send OTP. Please try again.");
        return;
      }

      setTimer(597);
      setOtp(new Array(6).fill(""));
      setError("");
      inputRefs.current[0]?.focus();
    } catch {
      setError("Failed to resend OTP. Please try again.");
    }
  };

  // Render

  return (
    <div className="min-h-screen md:min-h-[calc(100vh-4rem)] flex flex-col md:flex-row">
      {/* Right Side - OTP Form */}
      <div className="w-full md:w-1/2 bg-secondary flex flex-col relative md:order-2 h-[70vh] md:h-auto">
        <div className="flex-1 flex flex-col justify-center px-6 py-6 md:p-12 lg:px-16 lg:py-16">
          <div className="flex justify-center">
            <div className="w-fit space-y-3 md:space-y-8">
              {/* Heading */}
              <div className="space-y-2 md:space-y-4">
                <h1 className="text-2xl md:text-3xl lg:text-4xl font-bold text-primary">
                  {t("otp_verification")}
                </h1>
                <div className="space-y-2">
                  <p className="text-primary text-sm md:text-base lg:text-lg">
                    {t("otp_verification_msg")}{" "}
                    {formatPhoneNumber(phoneNumber)}
                  </p>
                  <button
                    onClick={handleChangeNumber}
                    className="text-[#4F60FD] hover:text-[#4A8AEE] font-medium transition-colors text-sm md:text-base"
                  >
                    {t("otp_change_number")}
                  </button>
                </div>
              </div>

              {/* OTP Input Boxes */}
              <div className="flex justify-center">
                <div className="flex gap-3 justify-center">
                  {otp.map((digit, index) => (
                    <input
                      key={index}
                      ref={(el) => {
                        inputRefs.current[index] = el;
                      }}
                      type="text"
                      inputMode="numeric"
                      maxLength={1}
                      value={digit}
                      onChange={(e) => handleChange(index, e.target.value)}
                      onKeyDown={(e) => handleKeyDown(index, e)}
                      onPaste={handlePaste}
                      className={`w-12 h-16 sm:w-14 sm:h-16 md:w-16 md:h-20 lg:w-20 lg:h-24 text-center text-2xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold rounded-xl md:rounded-2xl border-2 transition-all
                        ${
                          digit
                            ? "bg-tertiary text-primary border-secondary"
                            : "bg-[#E1E4FF] text-white border-tertiary"
                        }
                        ${error ? "border-red-500 shake" : ""}
                        focus:outline-none`}
                    />
                  ))}
                </div>
              </div>

              {/* Error */}
              {error && (
                <p className="text-red-500 text-sm md:text-base">{error}</p>
              )}

              {/* Verify Button */}
              <button
                onClick={handleVerify}
                disabled={otp.join("").length !== 6 || isVerifying}
                className="w-full py-3 md:py-4 bg-primary text-tertiary font-semibold text-base md:text-lg rounded-xl md:rounded-2xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isVerifying ? t("otp_verifying_btn") : t("otp_verify_btn")}
              </button>

              {/* Timer */}
              <p
                className={`text-center text-sm md:text-base ${timer < 60 ? "text-red-400 pulse" : "text-[#8E8E8E]"}`}
              >
                {t("otp_expire")} {formatTime(timer)}
              </p>

              {/* Resend */}
              <div className="text-sm md:text-base">
                <span className="text-primary">
                  {t("otp_not_received_code")}{" "}
                </span>
                <button
                  onClick={handleResend}
                  disabled={timer > 0}
                  className={`font-medium transition-colors ${
                    timer > 0
                      ? "text-[#4F60FD] cursor-not-allowed"
                      : "text-[#4F60FD] hover:text-[#4A8AEE]"
                  }`}
                >
                  {t("otp_resend")}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Left Side - Security Message & Illustration */}
      <div className="w-full md:w-1/2 bg-tertiary flex items-center justify-center relative md:order-1 h-[30vh] md:h-auto overflow-hidden">
        <div className="w-full h-full flex flex-col justify-center">
          {/* Desktop Layout */}
          <div className="hidden md:flex flex-col w-full h-full mx-auto px-8 py-8">
            <div className="space-y-6 lg:space-y-8 pt-8 pb-8">
              <h3 className="text-lg lg:text-xl font-semibold text-center text-primary">
                {t("otp_verification")}
              </h3>
              <h2 className="text-2xl lg:text-4xl font-bold text-primary leading-tight text-center">
                {t("otp_info")}
              </h2>
            </div>
            <div className="relative flex-1 w-full flex items-end justify-center -mb-8">
              <Image
                src="/images/otp.png"
                alt="OTP Security"
                width={1200}
                height={1200}
                className="object-contain object-bottom w-full h-auto max-w-full"
                priority
              />
            </div>
          </div>

          {/* Mobile Layout */}
          <div className="md:hidden flex flex-col h-full bg-[#343F1B]">
            <h2 className="text-sm sm:text-base font-bold text-secondary leading-tight text-center px-4 py-4">
              {t("otp_info")}
            </h2>
            <div className="relative flex-1 w-full">
              <Image
                src="/images/otp.png"
                alt="OTP Security"
                fill
                className="object-contain object-bottom"
                priority
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function OTPVerificationPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-tertiary" />}>
      <OTPVerificationContent />
    </Suspense>
  );
}

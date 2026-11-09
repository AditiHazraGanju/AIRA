"use client";

import Image from "next/image";
import { useLanguage } from "@/context/LanguageContext";

export default function OTPVerified() {
  const { t } = useLanguage();
  return (
    <div className="h-[100dvh] md:h-[calc(100vh-4rem)] flex flex-col bg-[#9AA4FE] overflow-hidden">
      {/* Content Container */}
      <div className="flex-1 flex flex-col min-h-0">
        {/* Header/Title Section */}
        <div className="flex items-center justify-center pt-24 md:pt-10 lg:pt-12 pb-8 shrink-0">
          <h1 className="text-3xl md:text-5xl lg:text-6xl font-bold text-primary text-center px-6">
            {t("otp_verified")}
          </h1>
        </div>

        {/* Illustration Section */}
        <div className="flex-1 relative w-full min-h-0 px-4">
          <div className="relative w-full h-full max-w-4xl mx-auto">
            <Image
              src="/images/otp-verified.png"
              alt="OTP Verified - Celebration"
              fill /* Using 'fill' replaces width/height and maps the image perfectly to the parent div */
              className="object-contain object-bottom"
              priority
            />
          </div>
        </div>
      </div>
    </div>
  );
}

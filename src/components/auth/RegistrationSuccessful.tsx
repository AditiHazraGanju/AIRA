"use client";

import Image from "next/image";
import { Trans } from "react-i18next";
import { useLanguage } from "@/context/LanguageContext";

export default function RegistrationSuccess() {
  const { t } = useLanguage();
  return (
    <div className="h-[100dvh] md:h-[calc(100vh-4rem)] flex flex-col bg-[#D3E3AB] overflow-hidden">
      
      {/* Content Container */}
      <div className="flex-1 flex flex-col min-h-0">
        
        {/* Header/Title Section */}
        <div className="flex flex-col items-center justify-center pt-12 md:pt-6 lg:pt-8 pb-4 md:pb-8 px-6 shrink-0">
          <h1 className="text-3xl md:text-5xl lg:text-6xl font-bold text-[#3F3F1B] text-center mb-4 md:mb-6">
            {t("user_congratulations")}
          </h1>
          <p className="text-lg md:text-xl lg:text-2xl text-[#3F3F1B] text-center font-medium">
            <Trans
              i18nKey="user_success_rich"
              components={{
                lineBreak: <br />,
                family: <span className="font-medium" />,
              }}
            />
          </p>
        </div>

        {/* Illustration Section */}
        <div className="flex-1 relative w-full min-h-0 px-4">
          
          {/* Unified Bounding Box for both Mobile and Desktop */}
          <div className="relative w-full h-full max-w-5xl mx-auto">
            <Image
              src="/images/registration-success.png"
              alt="Saathealth Family - Welcome"
              fill
              className="object-contain object-bottom"
              priority
            />
          </div>
          
        </div>
      </div>
    </div>
  );
}
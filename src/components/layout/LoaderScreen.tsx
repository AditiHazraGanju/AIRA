"use client";

import { useEffect, useState } from "react";
import Image from "next/image";

interface LoaderScreenProps {
  onComplete?: () => void;
  duration?: number;
}

export function LoaderScreen({ onComplete, duration = 2000 }: LoaderScreenProps) {
  const [isVisible, setIsVisible] = useState(true);
  const [fadeOut, setFadeOut] = useState(false);

  useEffect(() => {
    const fadeTimer = setTimeout(() => {
      setFadeOut(true);
    }, duration - 500);

    const hideTimer = setTimeout(() => {
      setIsVisible(false);
      onComplete?.();
    }, duration);

    return () => {
      clearTimeout(fadeTimer);
      clearTimeout(hideTimer);
    };
  }, [duration, onComplete]);

  if (!isVisible) return null;

  return (
    <div
      className={`fixed inset-0 z-[9999] bg-tertiary flex flex-col items-center justify-center transition-opacity duration-500 ${
        fadeOut ? "opacity-0" : "opacity-100"
      }`}
    >
      {/* Logo */}
      <div className="mb-8">
        <Image
          src="/images/SH-logo.png"
          alt="Saathealth"
          width={200}
          height={80}
          className="object-contain"
          priority
        />
      </div>

      {/* Loader Animation */}
      <div className="relative">
        {/* Spinning Circle Loader */}
        <div className="w-16 h-16 border-4 border-[#3d4a2e] border-t-[#b8d96e] rounded-full animate-spin"></div>
      </div>

      {/* Optional Loading Text */}
      <p className="mt-6 text-gray-400 text-sm animate-pulse">Loading...</p>
    </div>
  );
}
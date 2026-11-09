"use client";

import { useEffect } from "react";

export const LANGUAGES = ["ENGLISH", "हिंदी", "मराठी", "বাংলা"];

interface LanguageModalProps {
  isOpen: boolean;
  language: string;
  onSelect: (lang: string) => void;
  onClose: () => void;
  isMobile: boolean;
}

export function LanguageModal({
  isOpen,
  language,
  onSelect,
  onClose,
  isMobile,
}: LanguageModalProps) {
  // Close on Escape key
  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const card = (
    <div className="relative">
      {/* Caret arrow pointing up */}
      <div className="absolute -top-[10px] right-4 w-0 h-0 border-l-[10px] border-l-transparent border-r-[10px] border-r-transparent border-b-[10px] border-b-[#EEF2E3]" />

      {/* Dropdown card */}
      <div className="w-56 bg-tertiary rounded-lg shadow-2xl overflow-hidden py-2">
        {LANGUAGES.map((lang) => {
          const isSelected = language === lang;
          return (
            <button
              key={lang}
              onClick={() => {
                onSelect(lang);
                onClose();
              }}
              className={`w-full text-left px-4 py-3 flex items-center justify-between transition-colors ${
                isSelected ? "" : "hover:bg-secondary px-7"
              }`}
            >
              {isSelected ? (
                <span className="bg-secondary text-primary px-3 py-1.5 rounded-md text-base font-semibold w-full flex items-center justify-between">
                  {lang}
                  <svg
                    className="w-4 h-4 ml-2 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2.5}
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                </span>
              ) : (
                <span className="text-base font-medium text-primary">
                  {lang}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );

  // Mobile: full-screen blur backdrop + fixed positioning
  if (isMobile) {
    return (
      <>
        <div
          className="fixed inset-0 z-40 backdrop-blur-sm bg-black/10"
          onClick={onClose}
        />
        <div className="fixed top-[68px] right-4 z-50">{card}</div>
      </>
    );
  }

  // Desktop: simple absolute dropdown, no blur
  return (
    <div className="absolute top-[calc(100%+8px)] right-0 z-50">{card}</div>
  );
}
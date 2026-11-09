"use client";
import React, { useState, useRef, useEffect, useSyncExternalStore } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { TranslateIcon } from "../ui/Icons";
import { LanguageModal } from "../ui/LanguageModal";
import { useLanguage } from "@/context/LanguageContext";
import { getUserName } from "@/utils/user-storage";

// The name is saved at login, registration and profile load; setUserName
// fires "user_name_updated", and "storage" covers other tabs.
function subscribeToUserName(onChange: () => void) {
  window.addEventListener("user_name_updated", onChange);
  window.addEventListener("storage", onChange);
  return () => {
    window.removeEventListener("user_name_updated", onChange);
    window.removeEventListener("storage", onChange);
  };
}

export function Header() {
  const router = useRouter();
  const userName = useSyncExternalStore(
    subscribeToUserName,
    () => getUserName().trim() || "Guest",
    () => "Guest",
  );
  const { language, setLanguage } = useLanguage();
  const [isLanguageOpen, setIsLanguageOpen] = useState(false);
  
  const desktopDropdownRef = useRef<HTMLDivElement>(null);
  const mobileDropdownRef = useRef<HTMLDivElement>(null);

  const handleProfileNavigate = () => {
    router.push("/profile");
  };

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

  return (
    <header className="bg-primary fixed top-0 left-0 w-full z-50">
      <div className="flex md:hidden items-center justify-between py-4 px-4 bg-gradient-to-r from-[#D0D4FC] to-[#E6E8FC]">
        <div className="w-10 h-10 rounded-xl bg-white flex items-center justify-center overflow-hidden">
          <Image
            src="/images/Logo.png"
            alt="Saathealth"
            width={28}
            height={28}
            className="object-contain"
            unoptimized
          />
        </div>

        <div className="flex gap-3 items-center">
          <div className="relative" ref={mobileDropdownRef}>
            <button
              onClick={() => setIsLanguageOpen(!isLanguageOpen)}
              className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white hover:bg-secondary hover:text-primary transition-colors"
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

          <button
            onClick={handleProfileNavigate}
            aria-label="Profile"
            className="w-10 h-10 rounded-full bg-[#D7DBFF] border border-accent text-primary font-semibold flex items-center justify-center"
          >
            {userName.charAt(0).toUpperCase()}
          </button>
        </div>
      </div>
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="hidden md:flex items-center justify-between h-16">
          <Link href="/" className="flex items-center">
            <div className="relative w-48 h-16">
              <Image
                src="/images/SH-logo.png"
                alt="Saathealth Logo"
                fill
                priority
                className="object-contain"
              />
            </div>
          </Link>
          <div className="flex items-center">
            <div className="relative p-2" ref={desktopDropdownRef}>
              <button
                onClick={() => setIsLanguageOpen(!isLanguageOpen)}
                className="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center text-white hover:bg-secondary hover:text-primary transition-colors"
              >
                <TranslateIcon />
              </button>
              <LanguageModal
                isOpen={isLanguageOpen}
                language={language}
                onSelect={setLanguage}
                onClose={() => setIsLanguageOpen(false)}
                isMobile={false}
              />
            </div>
            
            <div
              className="flex items-center p-2 space-x-2 cursor-pointer"
              onClick={handleProfileNavigate}
            >
              <div className="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden bg-secondary">
                <Image
                  src="/images/person.png"
                  alt="User Profile"
                  width={40}
                  height={40}
                  className="object-cover"
                />
              </div>
              <span className="text-white font-medium hidden lg:block">
                {userName}
              </span>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
}

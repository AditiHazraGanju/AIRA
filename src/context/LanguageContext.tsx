"use client";

import {
  createContext,
  useContext,
  useState,
  useCallback,
  ReactNode,
} from "react";
import { useTranslation } from "react-i18next";
import "@/i18n/config";

// Map from the display label shown in LanguageModal → i18next language code
const LANG_CODE_MAP: Record<string, string> = {
  ENGLISH: "eng",
  हिंदी: "hin",
  मराठी: "mar",
  বাংলা: "ben",
};

interface LanguageContextValue {
  /** Display label, e.g. "ENGLISH" or "हिंदी" */
  language: string;
  /** Call this when the user picks a new language from the modal */
  setLanguage: (lang: string) => void;
  /** Translate a key from translation.json */
  t: (key: string) => string;
}

const LanguageContext = createContext<LanguageContextValue | null>(null);

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguageState] = useState("ENGLISH");
  const { t, i18n } = useTranslation();

  const setLanguage = useCallback(
    (lang: string) => {
      setLanguageState(lang);
      const code = LANG_CODE_MAP[lang] ?? "eng";
      i18n.changeLanguage(code);
    },
    [i18n],
  );

  return (
    <LanguageContext.Provider value={{ language, setLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage(): LanguageContextValue {
  const ctx = useContext(LanguageContext);
  if (!ctx) {
    throw new Error("useLanguage must be used inside <LanguageProvider>");
  }
  return ctx;
}

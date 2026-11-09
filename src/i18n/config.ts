import i18n from "i18next";
import { initReactI18next } from "react-i18next";

import engTranslation from "@/locales/eng/translation.json";
import hinTranslation from "@/locales/hin/translation.json";
import marTranslation from "@/locales/mar/translation.json";
import benTranslation from "@/locales/ben/translation.json";

if (!i18n.isInitialized) {
  i18n.use(initReactI18next).init({
    resources: {
      eng: { translation: engTranslation },
      hin: { translation: hinTranslation },
      mar: { translation: marTranslation },
      ben: { translation: benTranslation },
    },
    lng: "eng",
    fallbackLng: "eng",
    interpolation: {
      escapeValue: false,
    },
  });
}

export default i18n;

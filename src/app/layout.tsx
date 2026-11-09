import type { Metadata } from "next";
import { Titillium_Web } from "next/font/google";
import "./globals.css";
import { AppLayout } from "@/components/layout/AppLayout";
import { AuthProvider } from "@/context/AuthContext";
import { LanguageProvider } from "@/context/LanguageContext";
import { Toaster } from "react-hot-toast";

const titilliumWeb = Titillium_Web({
  subsets: ["latin"],
  weight: ["200", "300", "400", "600", "700", "900"],
  variable: "--font-titillium",
});

export const metadata: Metadata = {
  title: "Saathealth AI Chatbot",
  description: "AI conversational agent",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={titilliumWeb.variable}>
      <body className="antialiased">
        <LanguageProvider>
          <AuthProvider>
            <AppLayout>
              <Toaster />
              {children}
            </AppLayout>
          </AuthProvider>
        </LanguageProvider>
      </body>
    </html>
  );
}

"use client";

import { useState, useEffect, Suspense } from "react";
import axios from "axios";
import { ChevronLeft, Upload, Camera, XCircle, FileText } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { CHATBOT_REGISTER_GUEST_API, GET_STATE_API, getSafeRedirect } from "@/utils/url-helper";
import { setUserName } from "@/utils/user-storage";
import { useAuth } from "@/context/AuthContext";
import { useLanguage } from "@/context/LanguageContext";
import toast from "react-hot-toast";

// Types

interface ChatbotRegisterData {
  user_type: string;
  is_registered: boolean;
  user_data: {
    sh_user_id: number;
    name: string;
  };
  token: string;
}

interface State {
  state_id: number;
  state_name: string;
  state_code: string;
}

// Constants

const MONTHS = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];

const INCOME_RANGES = [
  { label: "0-2000 Rs/month", value: "0-2000" },
  { label: "2001-5000 Rs/month", value: "2001-5000" },
  { label: "5001-10000 Rs/month", value: "5001-10000" },
  { label: "10001-15000 Rs/month", value: "10001-15000" },
  { label: "15001-25000 Rs/month", value: "15001-25000" },
  { label: "25001-50000 Rs/month", value: "25001-50000" },
  { label: "50001-75000 Rs/month", value: "50001-75000" },
  { label: "75001-100000+ Rs/month", value: "75001-100000+" },
  { label: "1 Lakh+ Rs/month", value: "100000+" },
];

const MAX_FILE_BYTES = 5 * 1024 * 1024; // matches the API's 5 MB limit

// Catches typos only; the API does the real check
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const INPUT_CLASS =
  "w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors";

const LABEL_CLASS = "block text-primary font-medium mb-2";

const SELECT_CHEVRON_STYLE = {
  backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23010B65'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
  backgroundRepeat: "no-repeat" as const,
  backgroundPosition: "right 0.75rem center" as const,
  backgroundSize: "1.25rem" as const,
};

// Component

function UserRegistrationContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get("redirect");
  const redirect = getSafeRedirect(rawRedirect);

  const { setIsAuthenticated } = useAuth();
  const { t } = useLanguage();

  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [states, setStates] = useState<State[]>([]);

  const [formData, setFormData] = useState({
    name: "",
    email: "",
    day: "",
    month: "",
    year: "",
    gender: "",
    pincode: "",
    income: "",
    id_number: "",
    file: null as File | null,
    address: "",
    landmark: "",
    state_id: "" as number | "",
    city: "",
  });

  const setField = <K extends keyof typeof formData>(key: K, value: (typeof formData)[K]) =>
    setFormData((prev) => ({ ...prev, [key]: value }));

  // Get the phone number stored during OTP verification
  const phoneNumber =
    typeof window !== "undefined"
      ? localStorage.getItem("chatbot_guest_phone") ?? ""
      : "";

  // Fetch states on mount

  useEffect(() => {
    const fetchStates = async () => {
      try {
        const response = await apiHandler.get<State[]>(apiClient1, GET_STATE_API);
        if (response.success) {
          setStates(response.data);
        }
      } catch (err) {
        console.error("Failed to fetch states:", err);
      }
    };
    fetchStates();
  }, []);

  // Helpers

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ""; // allow picking the same file again after removing it
    if (!file) return;
    if (file.size > MAX_FILE_BYTES) {
      toast.error("File must not be larger than 5 MB.");
      return;
    }
    setField("file", file);
  };

  // DOB in the Y-m-d format the API expects
  const buildDob = (): string => {
    const month = (MONTHS.indexOf(formData.month) + 1).toString().padStart(2, "0");
    return `${formData.year}-${month}-${formData.day.padStart(2, "0")}`;
  };

  const isValidDob = (): boolean => {
    const day = Number(formData.day);
    const month = MONTHS.indexOf(formData.month);
    const year = Number(formData.year);
    const date = new Date(year, month, day);
    return (
      formData.year.length === 4 &&
      date.getFullYear() === year &&
      date.getMonth() === month &&
      date.getDate() === day &&
      date < new Date()
    );
  };

  // First problem with the form, or "" if it's complete
  const validate = (): string => {
    if (!formData.name.trim()) return "Please enter your name.";
    if (formData.email.trim() && !EMAIL_PATTERN.test(formData.email.trim())) {
      return "Please enter a valid email address.";
    }
    if (!formData.day || !formData.month || !formData.year) return "Please enter your full date of birth.";
    if (!isValidDob()) return "Please enter a valid date of birth.";
    if (!formData.gender) return "Please select your gender.";
    if (formData.pincode.length !== 6) return "Please enter a 6-digit pincode.";
    if (!formData.income) return "Please select your income.";
    if (!formData.address.trim()) return "Please enter your address.";
    if (!formData.state_id) return "Please select your state.";
    if (!formData.city.trim()) return "Please enter your city.";
    return "";
  };

  // Submit

  const handleSubmit = async () => {
    setError("");

    const problem = validate();
    if (problem) {
      setError(problem);
      toast.error(problem);
      return;
    }

    if (!phoneNumber) {
      setError("Phone number not found. Please login again.");
      toast.error("Phone number not found. Please login again.");
      router.push("/login");
      return;
    }

    try {
      setSubmitting(true);

      // FormData because the ID proof file is included
      const payload = new FormData();
      payload.append("mobile_no", phoneNumber);
      payload.append("name", formData.name.trim());
      payload.append("dob", buildDob());
      payload.append("gender", formData.gender);
      payload.append("pincode", formData.pincode);
      payload.append("income", formData.income);
      payload.append("state_id", String(formData.state_id));
      payload.append("city", formData.city.trim());
      payload.append("address", formData.address.trim());
      if (formData.email.trim()) payload.append("email", formData.email.trim());
      if (formData.id_number) payload.append("id_number", formData.id_number);
      if (formData.landmark.trim()) payload.append("landmark", formData.landmark.trim());
      if (formData.file) payload.append("file", formData.file);

      const response = await apiHandler.postForm<ChatbotRegisterData>(
        apiClient1,
        CHATBOT_REGISTER_GUEST_API,
        payload,
      );

      if (response.success) {
        toast.success("Registration successful!");

        const { user_data, token } = response.data;

        // Persist auth data
        localStorage.setItem("auth_token", token);
        localStorage.setItem("sh_user_id", String(user_data.sh_user_id));
        localStorage.setItem("user_type", response.data.user_type);
        setUserName(user_data.name);

        // Clean up guest phone
        localStorage.removeItem("chatbot_guest_phone");

        // Mark as authenticated
        setIsAuthenticated(true);

        // Always navigate to home after guest registration
        router.push("/");
      } else {
        setError(response.message || "Failed to register. Please try again.");
      }
    } catch (err: unknown) {
      // Show the first validation error from the API, if any
      const apiErrors = axios.isAxiosError(err)
        ? (err.response?.data as { errors?: Record<string, string[]> })?.errors
        : undefined;
      const firstError = apiErrors ? Object.values(apiErrors)[0] : undefined;
      setError(
        firstError
          ? Array.isArray(firstError) ? firstError[0] : String(firstError)
          : "Something went wrong. Please try again.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  // Render

  return (
    <div className="min-h-screen bg-secondary">
      {/* Header */}
      <div className="sticky top-0 z-10 bg-secondary pt-4 pb-4 px-6">
        <div className="relative flex items-center justify-center">
          <button
            onClick={() => router.back()}
            className="absolute left-0 top-1/2 -translate-y-1/2 w-12 h-12 rounded-xl bg-tertiary cursor-pointer flex items-center justify-center transition-colors"
          >
            <ChevronLeft className="w-6 h-6 text-primary" />
          </button>
          <div className="md:bg-primary rounded-2xl py-4 md:px-56 lg:px-60">
            <h1 className="text-2xl md:text-3xl font-bold text-primary md:text-tertiary">
              {t("user_registration")}
            </h1>
          </div>
        </div>
      </div>

      {/* Form */}
      <div className="px-6 py-8 pb-24">
        <div className="max-w-2xl mx-auto space-y-6">
          {/* Welcome Message */}
          <div className="space-y-2">
            <h2 className="text-xl md:text-2xl font-semibold text-primary">
              Welcome! Let&apos;s get you started
            </h2>
            <p className="text-primary text-sm md:text-base">
              Fill in your details to complete your registration.
            </p>
          </div>

          {/* Error Banner */}
          {error && (
            <div className="w-full px-4 py-3 bg-red-500/20 border border-red-400 rounded-xl text-red-600 text-sm">
              {error}
            </div>
          )}

          {/* Name */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_name")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.name}
              onChange={(e) => setField("name", e.target.value)}
              placeholder={t("user_name_placeholder")}
              autoFocus
              maxLength={255}
              className={INPUT_CLASS}
            />
          </div>

          {/* Email (optional) */}
          <div>
            <label className={LABEL_CLASS}>{t("user_email")}</label>
            <input
              type="email"
              inputMode="email"
              autoComplete="email"
              value={formData.email}
              onChange={(e) => setField("email", e.target.value)}
              placeholder={t("user_email_placeholder")}
              maxLength={255}
              className={INPUT_CLASS}
            />
          </div>

          {/* Date of Birth */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_dob")} <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-4 gap-3">
              <input
                type="text"
                inputMode="numeric"
                value={formData.day}
                onChange={(e) => setField("day", e.target.value.replace(/\D/g, "").slice(0, 2))}
                placeholder="DD"
                maxLength={2}
                className={`${INPUT_CLASS} text-center`}
              />
              <select
                value={formData.month}
                onChange={(e) => setField("month", e.target.value)}
                className={`${INPUT_CLASS} col-span-2 appearance-none cursor-pointer`}
                style={SELECT_CHEVRON_STYLE}
              >
                <option value="" disabled>
                  Month
                </option>
                {MONTHS.map((month) => (
                  <option key={month} value={month} className="bg-secondary">
                    {month}
                  </option>
                ))}
              </select>
              <input
                type="text"
                inputMode="numeric"
                value={formData.year}
                onChange={(e) => setField("year", e.target.value.replace(/\D/g, "").slice(0, 4))}
                placeholder="YYYY"
                maxLength={4}
                className={`${INPUT_CLASS} text-center`}
              />
            </div>
          </div>

          {/* Gender */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_gender")} <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-3 gap-3">
              {(["male", "female", "other"] as const).map((g) => (
                <button
                  key={g}
                  type="button"
                  onClick={() => setField("gender", g)}
                  className={`py-6 md:py-8 px-4 rounded-xl font-semibold transition-colors cursor-pointer ${
                    formData.gender === g
                      ? "bg-primary text-secondary border-2 border-primary"
                      : "bg-transparent border-2 border-[#0216CA] text-primary hover:bg-tertiary"
                  }`}
                >
                  {g.toUpperCase()}
                </button>
              ))}
            </div>
          </div>

          {/* Pincode */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_pincode")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              inputMode="numeric"
              value={formData.pincode}
              onChange={(e) => setField("pincode", e.target.value.replace(/\D/g, "").slice(0, 6))}
              placeholder={t("user_pincode_placeholder")}
              maxLength={6}
              className={INPUT_CLASS}
            />
          </div>

          {/* Income */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_income")} <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.income}
              onChange={(e) => setField("income", e.target.value)}
              className={`${INPUT_CLASS} appearance-none cursor-pointer`}
              style={SELECT_CHEVRON_STYLE}
            >
              <option value="" disabled>
                {t("user_income_placeholder")}
              </option>
              {INCOME_RANGES.map((range) => (
                <option key={range.value} value={range.value} className="bg-secondary">
                  {range.label}
                </option>
              ))}
            </select>
          </div>

          {/* ID Number (optional) */}
          <div>
            <label className={LABEL_CLASS}>{t("user_id_number")}</label>
            <input
              type="text"
              inputMode="numeric"
              value={formData.id_number}
              onChange={(e) => setField("id_number", e.target.value.replace(/\D/g, "").slice(0, 12))}
              placeholder={t("user_id_number_placeholder")}
              maxLength={12}
              className={INPUT_CLASS}
            />
          </div>

          {/* Upload File (optional) */}
          <div>
            <div className="md:flex md:items-center md:justify-between md:gap-4">
              <label className="block text-primary font-medium mb-2 md:mb-0 md:whitespace-nowrap">
                {t("user_upload_file")}
                <span className="ml-1 md:ml-0 text-primary opacity-80 font-normal text-xs md:text-sm md:block md:mt-0.5">
                  (JPEG, PNG, JPG, PDF)
                </span>
              </label>
              <div className="w-full md:flex-1">
                {formData.file ? (
                  <div className="w-full bg-tertiary rounded-xl p-4 flex items-center justify-between">
                    <div className="flex items-center gap-3 overflow-hidden">
                      <FileText className="w-6 h-6 text-primary flex-shrink-0" />
                      <span className="text-primary font-semibold truncate">
                        {formData.file.name || "Captured_Photo.jpg"}
                      </span>
                    </div>
                    <button
                      type="button"
                      onClick={() => setField("file", null)}
                      className="ml-3 text-primary hover:opacity-70 transition-opacity cursor-pointer"
                      aria-label="Remove file"
                    >
                      <XCircle className="w-6 h-6" />
                    </button>
                  </div>
                ) : (
                  <div className="grid grid-cols-2 gap-3 md:block">
                    {/* Upload File */}
                    <label className="cursor-pointer md:block md:w-full">
                      <input
                        type="file"
                        onChange={handleFileUpload}
                        className="hidden"
                        accept="image/jpeg,image/png,image/jpg,.pdf"
                      />
                      <div className="h-full w-full py-6 md:py-3 px-4 bg-tertiary hover:bg-accent rounded-xl text-primary font-semibold transition-colors flex flex-col md:flex-row items-center justify-center gap-2">
                        <Upload className="w-6 h-6 md:w-5 md:h-5" />
                        <span className="whitespace-nowrap text-sm md:text-base">
                          {t("user_upload_file")}
                        </span>
                      </div>
                    </label>
                    {/* Take Photo (mobile only) */}
                    <label className="cursor-pointer md:hidden">
                      <input
                        type="file"
                        onChange={handleFileUpload}
                        className="hidden"
                        accept="image/*"
                        capture="environment"
                      />
                      <div className="h-full w-full py-6 px-4 bg-tertiary active:bg-accent rounded-xl text-primary font-semibold transition-colors flex flex-col items-center justify-center gap-2">
                        <Camera className="w-6 h-6" />
                        <span className="whitespace-nowrap text-sm">
                          {t("user_take_photo")}
                        </span>
                      </div>
                    </label>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Address */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_address")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.address}
              onChange={(e) => setField("address", e.target.value)}
              placeholder={t("user_address_placeholder")}
              maxLength={500}
              className={INPUT_CLASS}
            />
          </div>

          {/* Landmark (optional) */}
          <div>
            <label className={LABEL_CLASS}>{t("user_landmark")}</label>
            <input
              type="text"
              value={formData.landmark}
              onChange={(e) => setField("landmark", e.target.value)}
              placeholder={t("user_landmark_placeholder")}
              maxLength={255}
              className={INPUT_CLASS}
            />
          </div>

          {/* State — fetched from API */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_state")} <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.state_id}
              onChange={(e) => setField("state_id", Number(e.target.value))}
              className={`${INPUT_CLASS} appearance-none cursor-pointer`}
              style={SELECT_CHEVRON_STYLE}
            >
              <option value="" disabled>
                {states.length === 0 ? t("user_state_loading") : t("user_state_placeholder")}
              </option>
              {states.map((state) => (
                <option key={state.state_id} value={state.state_id} className="bg-secondary">
                  {state.state_name}
                </option>
              ))}
            </select>
          </div>

          {/* City */}
          <div>
            <label className={LABEL_CLASS}>
              {t("user_city")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.city}
              onChange={(e) => setField("city", e.target.value)}
              placeholder={t("user_city_placeholder")}
              maxLength={255}
              className={INPUT_CLASS}
            />
          </div>

          {/* Submit Button */}
          <div className="pt-4">
            <button
              onClick={handleSubmit}
              disabled={submitting}
              className="w-full py-4 bg-primary text-tertiary font-semibold text-lg rounded-2xl transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting ? t("user_submitting_btn") : t("user_submit_btn")}
              {!submitting && (
                <ChevronLeft className="w-5 h-5 rotate-180 hidden md:flex" />
              )}
            </button>
          </div>

          {/* Login Link */}
          <div className="text-center pt-4">
            <span className="text-primary">{t("user_account")} </span>
            <button
              onClick={() => {
                const loginUrl = redirect
                  ? `/login?redirect=${encodeURIComponent(redirect)}`
                  : "/login";
                router.push(loginUrl);
              }}
              className="text-[#5B9BFF] hover:text-[#4A8AEE] font-medium transition-colors"
            >
              {t("user_login")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function UserRegistration() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-secondary" />}>
      <UserRegistrationContent />
    </Suspense>
  );
}

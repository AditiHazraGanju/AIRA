"use client";

import { useState, useEffect, Suspense } from "react";
import axios from "axios";
import { ChevronLeft, Upload, Camera, XCircle, FileText } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { REGISTRATION_API, GET_STATE_API, getSafeRedirect } from "@/utils/url-helper";
import { setUserName } from "@/utils/user-storage";
import { useLanguage } from "@/context/LanguageContext";
import toast from "react-hot-toast";

// ── Types ──────────────────────────────────────────────────────────────────────

interface State {
  state_id: number;
  state_name: string;
  state_code: string;
}

// ── Component ──────────────────────────────────────────────────────────────────

function UserRegistrationContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get("redirect");
  const redirect = getSafeRedirect(rawRedirect);

  const { t } = useLanguage();

  const [error, setError] = useState("");
  const [states, setStates] = useState<State[]>([]);
  const [submitting, setSubmitting] = useState(false);

  const [formData, setFormData] = useState({
    name: "",
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
    state_id: "" as number | "", // stores state_id (numeric) for API
    state_name: "", // stores display name for the select
    city: "",
  });

  // ── Fetch states from API on mount ──────────────────────────────────────────

  useEffect(() => {
    fetchStates();
  }, []);

  const fetchStates = async () => {
    try {
      const response = await apiHandler.get<State[]>(apiClient1, GET_STATE_API);
      if (response.success) {
        setStates(response.data);
      }
    } catch (error) {
      console.error("Failed to fetch states:", error);
    }
  };

  // ── Constants ────────────────────────────────────────────────────────────────

  const months = [
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

const incomeRanges = [
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

  // ── Helpers ──────────────────────────────────────────────────────────────────

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFormData({ ...formData, file: e.target.files[0] });
    }
  };

  const handleStateChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedId = Number(e.target.value);
    const selectedState = states.find((s) => s.state_id === selectedId);
    setFormData({
      ...formData,
      state_id: selectedId,
      state_name: selectedState?.state_name ?? "",
    });
  };

  // Build DOB string in Y-m-d format expected by API
  const buildDob = (): string => {
    const monthIndex = (months.indexOf(formData.month) + 1)
      .toString()
      .padStart(2, "0");
    const day = formData.day.padStart(2, "0");
    return `${formData.year}-${monthIndex}-${day}`;
  };

  // ── Submit ───────────────────────────────────────────────────────────────────

  const handleSubmit = async () => {
    setError("");

    // Basic client-side checks before hitting the API
    if (!formData.name) {
      setError("Please enter your name.");
      toast.error("Please enter your name.");
      return;
    }
    if (!formData.day || !formData.month || !formData.year) {
      setError("Please enter your full date of birth.");
      toast.error("Please enter your full date of birth.");
      return;
    }
    if (!formData.gender) {
      setError("Please select your gender.");
      toast.error("Please select your gender.");
      return;
    }
    if (!formData.pincode) {
      setError("Please enter your pincode.");
      toast.error("Please enter your pincode.");
      return;
    }
    if (!formData.income) {
      setError("Please select your income.");
      toast.error("Please select your income.");
      return;
    }
    if (!formData.address) {
      setError("Please enter your address.");
      toast.error("Please enter your address.");
      return;
    }
    if (!formData.state_id) {
      setError("Please select your state.");
      toast.error("Please select your state.");
      return;
    }
    if (!formData.city) {
      setError("Please enter your city.");
      toast.error("Please enter your city.");
      return;
    }

    try {
      setSubmitting(true);

      // Retrieve saathi_beneficiary_id from localStorage (set during OTP verification)
      const saathiBeneficiaryId =
        localStorage.getItem("saathi_beneficiary_id") ?? "";

      // Build the URL by replacing the {sh_user_id} placeholder
      const shUserId = localStorage.getItem("sh_user_id") ?? "";
      const url = REGISTRATION_API.replace("{sh_user_id}", shUserId);

      // Build FormData — required because file upload is included
      const payload = new FormData();
      payload.append("role_slug", "saathi-beneficiary-user");
      payload.append("saathi_beneficiary_id", saathiBeneficiaryId);
      payload.append("name", formData.name);
      payload.append("dob", buildDob());
      payload.append("gender", formData.gender);
      payload.append("pincode", formData.pincode);
      payload.append("income", formData.income);
      payload.append("id_number", formData.id_number);
      payload.append("state_id", String(formData.state_id));
      payload.append("city", formData.city);
      payload.append("address", formData.address);
      payload.append("landmark", formData.landmark);

      // Only append file if one was selected
      if (formData.file) {
        payload.append("file", formData.file);
      }

      const response = await apiHandler.postForm(apiClient1, url, payload);

      if (response.success) {
        toast.success("Registration successful!");
        setUserName(formData.name); // Persists name and notifies Header

        const successUrl = redirect
          ? `/registration-successful?redirect=${encodeURIComponent(redirect)}`
          : "/registration-successful";

        router.push(successUrl);
      } else {
        setError(response.message || "Failed to register. Please try again.");
      }
    } catch (err: unknown) {
      const apiErrors =
        axios.isAxiosError(err)
          ? (err.response?.data as { errors?: Record<string, string[]> })?.errors
          : undefined;
      if (apiErrors) {
        // Show first validation error from API
        const firstError = Object.values(apiErrors)[0];
        setError(
          Array.isArray(firstError) ? firstError[0] : String(firstError),
        );
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  // ── Render ───────────────────────────────────────────────────────────────────

  const selectChevronStyle = {
    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23B8D67B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
    backgroundRepeat: "no-repeat" as const,
    backgroundPosition: "right 0.75rem center" as const,
    backgroundSize: "1.25rem" as const,
  };

  return (
    <div className="min-h-screen bg-tertiary">
      {/* ── Header ── */}
      <div className="sticky top-0 z-10 bg-tertiary pt-4 pb-4 px-6">
        <div className="relative flex items-center justify-center">
          <button
            onClick={() => router.back()}
            className="absolute left-0 top-1/2 -translate-y-1/2 w-12 h-12 rounded-xl bg-[#343F1B] hover:bg-secondary flex items-center justify-center transition-colors"
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

      {/* ── Form ── */}
      <div className="px-6 py-8 pb-24">
        <div className="max-w-2xl mx-auto space-y-6">
          {/* Error Banner */}
          {error && (
            <div className="w-full px-4 py-3 bg-red-500/20 border border-red-400 rounded-xl text-red-400 text-sm">
              {error}
            </div>
          )}

          {/* Name */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_name")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.name}
              onChange={(e) =>
                setFormData({ ...formData, name: e.target.value })
              }
              placeholder={t("user_name_placeholder")}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* Date of Birth */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_dob")} <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-4 gap-3">
              <input
                type="text"
                value={formData.day}
                onChange={(e) => {
                  const val = e.target.value.replace(/\D/g, "").slice(0, 2);
                  setFormData({ ...formData, day: val });
                }}
                placeholder="DD"
                maxLength={2}
                className="px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors text-center"
              />
              <select
                value={formData.month}
                onChange={(e) =>
                  setFormData({ ...formData, month: e.target.value })
                }
                className="col-span-2 px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer"
                style={selectChevronStyle}
              >
                <option value="" disabled>
                  Month
                </option>
                {months.map((month) => (
                  <option key={month} value={month} className="bg-[#1a2410]">
                    {month}
                  </option>
                ))}
              </select>
              <input
                type="text"
                value={formData.year}
                onChange={(e) => {
                  const val = e.target.value.replace(/\D/g, "").slice(0, 4);
                  setFormData({ ...formData, year: val });
                }}
                placeholder="YYYY"
                maxLength={4}
                className="px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors text-center"
              />
            </div>
          </div>

          {/* Gender */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_gender")} <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-3 gap-3">
              {(["male", "female", "other"] as const).map((g) => (
                <button
                  key={g}
                  onClick={() => setFormData({ ...formData, gender: g })}
                  className={`py-8 md:py-10 px-4 rounded-xl font-semibold transition-colors ${
                    formData.gender === g
                      ? "bg-[#BBD57D] text-tertiary"
                      : "bg-transparent border-2 border-[#EFF6E1] text-primary hover:border-[#EFF6E1]"
                  }`}
                >
                  {g.toUpperCase()}
                </button>
              ))}
            </div>
          </div>

          {/* Pincode */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_pincode")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.pincode}
              onChange={(e) => {
                const val = e.target.value.replace(/\D/g, "").slice(0, 6);
                setFormData({ ...formData, pincode: val });
              }}
              placeholder={t("user_pincode_placeholder")}
              maxLength={6}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* Income */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_income")} <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.income}
              onChange={(e) =>
                setFormData({ ...formData, income: e.target.value })
              }
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer"
              style={selectChevronStyle}
            >
              <option value="" disabled>
                {t("user_income_placeholder")}
              </option>
              {incomeRanges.map((range) => (
                <option key={range.value} value={range.value} className="bg-[#1a2410]">
                  {range.label}
                </option>
              ))}
            </select>
          </div>

          {/* ID Number (Aadhar) — placed directly above Upload File */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_id_number")}{" "}
              {/* <span className="text-[#EFF6E1] font-normal text-sm">
                (Aadhar)
              </span> */}
            </label>
            <input
              type="text"
              value={formData.id_number}
              onChange={(e) => {
                // Allow only numeric input, max 12 digits for Aadhar
                const val = e.target.value.replace(/\D/g, "").slice(0, 12);
                setFormData({ ...formData, id_number: val });
              }}
              placeholder={t("user_id_number_placeholder")}
              maxLength={12}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* Upload File */}
          <div>
            <div className="md:flex md:items-center md:justify-between md:gap-4">
              <label className="block text-secondary font-medium mb-2 md:mb-0 md:whitespace-nowrap flex items-center gap-1 md:block">
                {t("user_upload_file")}
                <span className="text-secondary opacity-95 font-normal text-xs md:text-sm md:block md:mt-0.5">
                  (JPEG, PNG, JPG, PDF)
                </span>
              </label>
              <div className="w-full md:flex-1">
                {formData.file ? (
                  <div className="w-full bg-secondary rounded-xl p-4 flex items-center justify-between animate-fade-in">
                    <div className="flex items-center gap-3 overflow-hidden">
                      <FileText className="w-6 h-6 text-tertiary flex-shrink-0" />
                      <span className="text-tertiary font-semibold truncate">
                        {formData.file.name || "Captured_Photo.jpg"}
                      </span>
                    </div>
                    <button
                      onClick={(e) => {
                        e.preventDefault();
                        setFormData({ ...formData, file: null });
                      }}
                      className="ml-3 text-tertiary hover:text-primary transition-colors"
                    >
                      <XCircle className="w-6 h-6" />
                    </button>
                  </div>
                ) : (
                  <div className="grid grid-cols-2 gap-3 md:block">
                    {/* Upload File */}
                    <label className="cursor-pointer md:block md:w-full group">
                      <input
                        type="file"
                        onChange={handleFileUpload}
                        className="hidden"
                        accept="image/jpeg,image/png,image/jpg,.pdf"
                      />
                      <div className="h-full w-full py-6 md:py-3 px-4 bg-[#343F1B] active:bg-secondary md:bg-secondary md:hover:bg-primary rounded-xl text-secondary active:text-tertiary md:text-tertiary font-semibold transition-colors flex flex-col md:flex-row items-center justify-center gap-2">
                        <Upload className="w-6 h-6 md:w-5 md:h-5" />
                        <span className="whitespace-nowrap text-sm md:text-base">
                          {t("user_upload_file")}
                        </span>
                      </div>
                    </label>
                    {/* Take Photo (mobile only) */}
                    <label className="cursor-pointer md:hidden group">
                      <input
                        type="file"
                        onChange={handleFileUpload}
                        className="hidden"
                        accept="image/*"
                        capture="environment"
                      />
                      <div className="h-full w-full py-6 px-4 bg-[#343F1B] active:bg-secondary rounded-xl text-secondary active:text-tertiary font-semibold transition-colors flex flex-col items-center justify-center gap-2">
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
            <label className="block text-secondary font-medium mb-2">
              {t("user_address")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.address}
              onChange={(e) =>
                setFormData({ ...formData, address: e.target.value })
              }
              placeholder={t("user_address_placeholder")}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* Landmark */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_landmark")}
            </label>
            <input
              type="text"
              value={formData.landmark}
              onChange={(e) =>
                setFormData({ ...formData, landmark: e.target.value })
              }
              placeholder={t("user_landmark_placeholder")}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* State — fetched from API */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_state")} <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.state_id}
              onChange={handleStateChange}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer"
              style={selectChevronStyle}
            >
              <option value="" disabled>
                {states.length === 0
                  ? t("user_state_loading")
                  : t("user_state_placeholder")}
              </option>
              {states.map((state) => (
                <option
                  key={state.state_id}
                  value={state.state_id}
                  className="bg-[#1a2410]"
                >
                  {state.state_name}
                </option>
              ))}
            </select>
          </div>

          {/* City */}
          <div>
            <label className="block text-secondary font-medium mb-2">
              {t("user_city")} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={formData.city}
              onChange={(e) =>
                setFormData({ ...formData, city: e.target.value })
              }
              placeholder={t("user_city_placeholder")}
              className="w-full px-4 py-3 bg-transparent border-2 border-[#EFF6E1] rounded-xl text-primary placeholder-[#EFF6E1] focus:outline-none focus:border-primary transition-colors"
            />
          </div>

          {/* Submit */}
          <div className="pt-4">
            <button
              onClick={handleSubmit}
              disabled={submitting}
              className="w-full py-4 bg-primary hover:bg-secondary text-tertiary font-semibold text-lg rounded-2xl transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting ? t("user_submitting_btn") : t("user_submit_btn")}
              {!submitting && (
                <ChevronLeft className="w-5 h-5 rotate-180 hidden md:flex" />
              )}
            </button>
          </div>

          {/* Login Link */}
          <div className="text-center pt-4">
            <span className="text-secondary">{t("user_account")} </span>
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
    <Suspense fallback={<div className="min-h-screen bg-tertiary" />}>
      <UserRegistrationContent />
    </Suspense>
  );
}
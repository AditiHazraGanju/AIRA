"use client";

import { useState, useRef, useEffect, useCallback } from "react";
import axios from "axios";
import {
  ChevronLeft,
  Upload,
  FileText,
  XCircle,
  CircleCheck,
  Pencil,
  Check,
  Calendar as CalendarIcon,
  ChevronDown,
  ChevronRight,
  History,
  Loader2,
} from "lucide-react";
import { useRouter } from "next/navigation";
import { format, parse } from "date-fns";
import { DayPicker } from "react-day-picker";
import type { DropdownProps } from "react-day-picker";
import "react-day-picker/dist/style.css";
import toast from "react-hot-toast";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { CHATBOT_PROFILE_API, GET_STATE_API } from "@/utils/url-helper";
import { setUserName, clearUserStorage } from "@/utils/user-storage";
import { useLanguage } from "@/context/LanguageContext";


// ── Types ──────────────────────────────────────────────────────────────────────

interface State {
  state_id: number;
  state_name: string;
  state_code: string;
}

// The logged-in user's row in the central users table (GET/POST /auth/chatbot/profile)
interface ChatbotProfile {
  sh_user_id: number;
  name: string | null;
  mobile: string | null;
  email: string | null;
  dob: string | null; // Y-m-d
  gender: string | null;
  pincode: string | null;
  monthly_income_range: string | null;
  id_number: string | null;
  id_proof_file: string | null;
  address: string | null;
  landmark: string | null;
  state_id: number | null;
  state_name: string | null;
  city: string | null;
}

type ProfileForm = {
  name: string;
  phone: string;
  email: string;
  dob: Date | null;
  gender: string;
  pin: string;
  income: string;
  id_number: string;
  address: string;
  landmark: string;
  state_id: number | "";
  city: string;
  // A newly picked File, or {name} for the ID proof already on file
  file: File | { name: string } | null;
};

const EMPTY_FORM: ProfileForm = {
  name: "",
  phone: "",
  email: "",
  dob: null,
  gender: "",
  pin: "",
  income: "",
  id_number: "",
  address: "",
  landmark: "",
  state_id: "",
  city: "",
  file: null,
};

function toForm(p: ChatbotProfile): ProfileForm {
  return {
    name: p.name ?? "",
    phone: p.mobile ?? "",
    email: p.email ?? "",
    // Parse as a local date: new Date("1990-05-17") is UTC midnight and can show the day before
    dob: p.dob ? parse(p.dob, "yyyy-MM-dd", new Date()) : null,
    gender: p.gender?.toLowerCase() ?? "",
    pin: p.pincode ?? "",
    income: p.monthly_income_range ?? "",
    id_number: p.id_number ?? "",
    address: p.address ?? "",
    landmark: p.landmark ?? "",
    state_id: p.state_id ?? "",
    city: p.city ?? "",
    file: p.id_proof_file ? { name: p.id_proof_file } : null,
  };
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MAX_FILE_BYTES = 5 * 1024 * 1024; // matches the API's 5 MB limit

// ── Constants ─────────────────────────────────────────────────────────────────

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

const GENDER_OPTIONS = [
  { label: "MALE", value: "male" },
  { label: "FEMALE", value: "female" },
  { label: "OTHER", value: "other" }
];

/* ── Custom scrollable dropdown for react-day-picker v9 ── */
function CalendarDropdown({ value, onChange, options }: DropdownProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLUListElement>(null);

  const selected = options?.find((o) => o.value === value);

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  // Scroll selected item into view when opening
  useEffect(() => {
    if (open && listRef.current) {
      const activeEl = listRef.current.querySelector("[data-active='true']") as HTMLElement | null;
      activeEl?.scrollIntoView({ block: "nearest" });
    }
  }, [open]);

  return (
    <div ref={ref} className="rdp-custom-dropdown">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="rdp-custom-btn"
      >
        <span>{selected?.label ?? value}</span>
        <ChevronDown
          size={12}
          style={{
            transition: "transform 0.15s",
            transform: open ? "rotate(180deg)" : "rotate(0deg)",
          }}
        />
      </button>

      {open && (
        <ul ref={listRef} className="rdp-custom-list">
          {options?.map((opt) => (
            <li
              key={opt.value}
              data-active={opt.value === value}
              className="rdp-custom-option"
              onClick={() => {
                onChange?.({
                  target: { value: String(opt.value) },
                } as React.ChangeEvent<HTMLSelectElement>);
                setOpen(false);
              }}
            >
              {opt.label}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function ProfilePage() {
  const router = useRouter();
  const { t } = useLanguage();

  // ── Data Loading States ───────────────────────────────────────────────────
  const [profile, setProfile] = useState<ChatbotProfile | null>(null);
  const [isFetching, setIsFetching] = useState(true);
  const [states, setStates] = useState<State[]>([]);

  // ── UI States ─────────────────────────────────────────────────────────────
  const [isEditing, setIsEditing] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [showCalendar, setShowCalendar] = useState(false);

  // ── Form Data ─────────────────────────────────────────────────────────────
  const [formData, setFormData] = useState<ProfileForm>(EMPTY_FORM);

  const showProfile = useCallback((p: ChatbotProfile) => {
    setProfile(p);
    setFormData(toForm(p));
    if (p.name) setUserName(p.name); // keeps the Header in step
  }, []);

  // ── Initialization ────────────────────────────────────────────────────────

  // 1. Fetch Indian States on mount
  useEffect(() => {
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
    fetchStates();
  }, []);

  // 2. Fetch the logged-in user's profile
  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const response = await apiHandler.get<ChatbotProfile>(apiClient1, CHATBOT_PROFILE_API);
        if (response.success) {
          showProfile(response.data);
        } else {
          toast.error("Failed to load profile.");
        }
      } catch {
        toast.error("Failed to load profile.");
      } finally {
        setIsFetching(false);
      }
    };
    fetchProfile();
  }, [showProfile]);

  // ── Handlers ──────────────────────────────────────────────────────────────

  const handleSelectDate = (date: Date | undefined) => {
    if (date) {
      setFormData((prev) => ({ ...prev, dob: date }));
      setShowCalendar(false);
    }
  };

  // Extract the actual File object
  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ""; // allow picking the same file again after removing it
    if (!file) return;
    if (file.size > MAX_FILE_BYTES) {
      toast.error("File must not be larger than 5 MB.");
      return;
    }
    setFormData((prev) => ({ ...prev, file }));
  };

  const handleLogout = () => {
    console.log("Logging out...");
    sessionStorage.setItem("intentional_logout", "true");
    clearUserStorage();
    // AppLayout will handle the redirect to /login
  };

  // First problem with the form, or "" if it can be saved
  const validate = (): string => {
    if (formData.email.trim() && !EMAIL_PATTERN.test(formData.email.trim())) {
      return "Please enter a valid email address.";
    }
    if (!formData.dob) return "Please select your date of birth.";
    if (!formData.gender) return "Please select your gender.";
    if (!/^\d{6}$/.test(formData.pin)) return "Please enter a 6-digit pincode.";
    if (!formData.income) return "Please select your income.";
    if (!formData.address.trim()) return "Please enter your address.";
    if (!formData.state_id) return "Please select your state.";
    if (!formData.city.trim()) return "Please enter your city.";
    return "";
  };

  const handleSave = async () => {
    const problem = validate();
    if (problem) {
      toast.error(problem);
      return;
    }

    try {
      setIsSaving(true);

      // FormData because the ID proof file may be included
      const payload = new FormData();
      payload.append("name", formData.name);
      payload.append("email", formData.email.trim());
      payload.append("dob", format(formData.dob as Date, "yyyy-MM-dd"));
      payload.append("gender", formData.gender);
      payload.append("pincode", formData.pin);
      payload.append("income", formData.income);
      payload.append("id_number", formData.id_number);
      payload.append("state_id", String(formData.state_id));
      payload.append("city", formData.city.trim());
      payload.append("address", formData.address.trim());
      payload.append("landmark", formData.landmark.trim());

      if (formData.file instanceof File) {
        payload.append("file", formData.file);
      } else if (!formData.file && profile?.id_proof_file) {
        payload.append("remove_file", "1"); // the user removed the file on record
      }

      const response = await apiHandler.postForm<ChatbotProfile>(apiClient1, CHATBOT_PROFILE_API, payload);

      if (response.success) {
        toast.success("Profile updated successfully!");
        setIsEditing(false);
        showProfile(response.data);
      } else {
        toast.error(response.message || "Failed to update profile.");
      }
    } catch (err: unknown) {
      // Show the first validation error from the API, if any
      const apiErrors = axios.isAxiosError(err)
        ? (err.response?.data as { errors?: Record<string, string[]> })?.errors
        : undefined;
      const firstError = apiErrors ? Object.values(apiErrors)[0] : undefined;
      toast.error(
        firstError
          ? Array.isArray(firstError) ? firstError[0] : String(firstError)
          : "Failed to update profile.",
      );
    } finally {
      setIsSaving(false);
    }
  };

  const handleEditToggle = () => {
    if (isEditing) {
      handleSave();
    } else {
      setIsEditing(true);
    }
  };

  return (
    <div className="min-h-screen">

      <style jsx global>{`
        /* ── react-day-picker v9 theme ── */

        .custom-rdp {
          --rdp-accent-color: #a7c957;
          --rdp-accent-background-color: #a7c957;
          margin: 0;
          font-family: inherit;
        }

        /* Caption row */
        .custom-rdp .rdp-month_caption {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 8px;
          gap: 6px;
        }

        .custom-rdp .rdp-dropdowns {
          display: flex;
          align-items: center;
          gap: 6px;
          /* Prevent caption from clipping the custom dropdown list */
          overflow: visible;
        }

        /* ── Custom dropdown button ── */
        .rdp-custom-dropdown {
          position: relative;
        }

        .rdp-custom-btn {
          display: flex;
          align-items: center;
          gap: 5px;
          background-color: #243015;
          color: #a7c957;
          border: 1px solid #a7c957;
          border-radius: 8px;
          padding: 4px 10px;
          font-size: 0.82rem;
          font-weight: 700;
          cursor: pointer;
          white-space: nowrap;
          outline: none;
          transition: border-color 0.15s;
        }

        .rdp-custom-btn:focus,
        .rdp-custom-btn:hover {
          border-color: #dbe9ba;
        }

        /* ── Scrollable dropdown list ── */
        .rdp-custom-list {
          position: absolute;
          top: calc(100% + 4px);
          left: 0;
          z-index: 9999;
          background-color: #1a2410;
          border: 1px solid #a7c957;
          border-radius: 8px;
          list-style: none;
          margin: 0;
          padding: 4px 0;
          min-width: 100%;
          max-height: 180px;
          overflow-y: auto;
          box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);

          /* Thin scrollbar */
          scrollbar-width: thin;
          scrollbar-color: #a7c957 #1a2410;
        }

        .rdp-custom-list::-webkit-scrollbar {
          width: 4px;
        }
        .rdp-custom-list::-webkit-scrollbar-track {
          background: #1a2410;
          border-radius: 4px;
        }
        .rdp-custom-list::-webkit-scrollbar-thumb {
          background: #a7c957;
          border-radius: 4px;
        }

        /* ── List items ── */
        .rdp-custom-option {
          padding: 5px 14px;
          font-size: 0.82rem;
          font-weight: 600;
          color: #a7c957;
          cursor: pointer;
          transition: background 0.1s;
        }

        .rdp-custom-option:hover {
          background-color: #2e3d18;
        }

        .rdp-custom-option[data-active='true'] {
          background-color: #a7c957;
          color: #161a0b;
          font-weight: 700;
        }

        /* Caption label fallback */
        .custom-rdp .rdp-caption_label {
          color: #a7c957;
          font-weight: 700;
          font-size: 0.95rem;
        }

        /* ── Navigation buttons ── */
        .custom-rdp .rdp-nav {
          display: flex;
          align-items: center;
          gap: 4px;
        }

        .custom-rdp .rdp-button_previous,
        .custom-rdp .rdp-button_next {
          color: #a7c957 !important;
          background: transparent;
          border: none;
          border-radius: 6px;
          width: 28px;
          height: 28px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: background 0.15s;
        }

        .custom-rdp .rdp-button_previous:hover,
        .custom-rdp .rdp-button_next:hover {
          background: #243015;
        }

        .custom-rdp .rdp-chevron {
          fill: #a7c957;
        }

        /* ── Weekday headers ── */
        .custom-rdp .rdp-weekday {
          color: #a7c957 !important;
          font-weight: 700;
          font-size: 0.75rem;
          text-transform: uppercase;
          opacity: 1;
          padding-bottom: 4px;
        }

        /* ── Day cells ── */
        .custom-rdp .rdp-day_button {
          color: #a7c957;
          border-radius: 6px;
          width: 34px;
          height: 34px;
          font-size: 0.875rem;
          transition: background 0.15s, color 0.15s;
        }

        .custom-rdp .rdp-day_button:hover:not(:disabled) {
          background-color: #2e3d18;
          color: #dbe9ba;
        }

        .custom-rdp .rdp-selected .rdp-day_button {
          background-color: #a7c957 !important;
          color: #161a0b !important;
          font-weight: 700;
        }

        .custom-rdp .rdp-today .rdp-day_button {
          border: 1px solid #a7c957;
          font-weight: 700;
        }

        .custom-rdp .rdp-outside .rdp-day_button {
          opacity: 0.35;
        }

        .custom-rdp .rdp-disabled .rdp-day_button {
          opacity: 0.2;
          cursor: not-allowed;
        }
      `}</style>

      {/* Header */}
      <div className="pt-4 pb-4 px-6">
        <div className="relative flex items-center justify-center">
          <button
            onClick={() => router.back()}
            className="absolute left-0 top-1/2 -translate-y-1/2 w-12 h-12 rounded-xl bg-tertiary cursor-pointer flex items-center justify-center transition-colors"
          >
            <ChevronLeft className="w-6 h-6 text-primary" />
          </button>

          <div className="md:bg-accent rounded-2xl py-4 md:px-64 lg:px-80">
            <h1 className="text-2xl md:text-3xl font-bold text-primary md:text-primary">
              {t("profile")}
            </h1>
          </div>

          <button
            onClick={handleEditToggle}
            disabled={isSaving || isFetching}
            className="absolute right-0 top-1/2 -translate-y-1/2 px-4 py-2 md:px-6 md:py-3 rounded-xl border-2 border-primary text-primary hover:bg-primary hover:text-tertiary font-semibold transition-colors disabled:opacity-50"
          >
            <span className="flex items-center gap-1">
              {isSaving ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : isEditing ? (
                <Check className="w-4 h-4" />
              ) : (
                <Pencil className="w-4 h-4" />
              )}
              {isSaving ? t("user_submitting_btn") : isEditing ? t("profile_save") : t("profile_edit")}
            </span>
          </button>
        </div>
      </div>

      {/* Form */}
      <div className="px-6 py-8 pb-32 md:pb-16">
        <div className="max-w-2xl mx-auto space-y-6">

          {isFetching ? (
            <div className="flex justify-center py-20">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <>
              {/* Basic Details Section */}
              <div className="space-y-4">
                <h2 className="text-xl font-semibold text-primary">
                  {t("profile_basic_details")}
                </h2>

                {/* Name (Read Only as per API logic) */}
                <div>
                  <input
                    type="text"
                    value={formData.name}
                    readOnly
                    disabled
                    placeholder="Name"
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                  />
                </div>

                {/* Phone (Read Only as per API logic) */}
                <div className="flex gap-3">
                  <input
                    type="text"
                    value={formData.phone}
                    readOnly
                    disabled
                    placeholder={t("profile_phone")}
                    className="flex-1 px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                  />
                  <button disabled className="px-6 py-3 bg-accent rounded-xl text-primary font-semibold hover:bg-primary hover:text-white transition-colors whitespace-nowrap flex items-center gap-2 opacity-60 cursor-not-allowed">
                    <CircleCheck />
                    {t("profile_verified")}
                  </button>
                </div>

                {/* Email */}
                <div className="flex gap-3">
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    disabled={!isEditing}
                    placeholder={t("user_email")}
                    maxLength={255}
                    className="flex-1 px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                  <button className="px-6 py-3 bg-accent rounded-xl text-primary font-semibold hover:bg-primary hover:text-white transition-colors whitespace-nowrap flex items-center gap-2">
                    <CircleCheck />
                    {t("profile_verify")}
                  </button>
                </div>
              </div>

              {/* Demographic Details Section */}
              <div className="space-y-4 pt-4">
                <h2 className="text-xl font-semibold text-primary">
                  {t("profile_demographic_details")}
                </h2>

                {/* Date of Birth with react-day-picker */}
                <div className="relative">
                  <div className="flex gap-2">
                    <button
                      type="button"
                      disabled={!isEditing}
                      onClick={() => setShowCalendar(!showCalendar)}
                      className="flex-1 flex items-center justify-between px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary disabled:opacity-80"
                    >
                      <span className={formData.dob ? "" : "text-accent"}>
                        {formData.dob ? format(formData.dob, "do MMMM yyyy") : t("user_dob")}
                      </span>
                      <CalendarIcon className="w-5 h-5 text-secondary" />
                    </button>
                  </div>

                  {showCalendar && isEditing && (
                    <div className="absolute z-50 mt-2 p-4 bg-[#1a2410] border-2 border-primary rounded-2xl shadow-xl left-1/2 -translate-x-1/2 md:left-0 md:translate-x-0" style={{ overflow: "visible" }}>
                      <DayPicker
                        mode="single"
                        selected={formData.dob ?? undefined}
                        onSelect={handleSelectDate}
                        captionLayout="dropdown"
                        defaultMonth={formData.dob ?? new Date(1990, 0)}
                        startMonth={new Date(1920, 0)}
                        endMonth={new Date()}
                        disabled={{ after: new Date() }}
                        className="custom-rdp"
                        components={{ Dropdown: CalendarDropdown }}
                      />
                      <button
                        onClick={() => setShowCalendar(false)}
                        className="w-full mt-2 py-2 text-sm text-secondary hover:text-primary transition-colors"
                      >
                        Close
                      </button>
                    </div>
                  )}
                </div>

                {/* Gender */}
                <div>
                  <select
                    value={formData.gender}
                    onChange={(e) =>
                      setFormData({ ...formData, gender: e.target.value })
                    }
                    disabled={!isEditing}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer disabled:opacity-80"
                    style={{
                      backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239AA4FE'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                      backgroundRepeat: "no-repeat",
                      backgroundPosition: "right 0.75rem center",
                      backgroundSize: "1.25rem",
                    }}
                  >
                    <option value="" disabled className="bg-secondary">{t("user_gender")}</option>
                    {GENDER_OPTIONS.map((gender) => (
                      <option key={gender.value} value={gender.value} className="bg-secondary">
                        {gender.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Pincode */}
                <div>
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={6}
                    value={formData.pin}
                    onChange={(e) =>
                      setFormData({ ...formData, pin: e.target.value.replace(/\D/g, "").slice(0, 6) })
                    }
                    disabled={!isEditing}
                    placeholder={t("profile_pin")}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                </div>

                {/* Income */}
                <div>
                  <select
                    value={formData.income}
                    onChange={(e) =>
                      setFormData({ ...formData, income: e.target.value })
                    }
                    disabled={!isEditing}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer disabled:opacity-80"
                    style={{
                      backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239AA4FE'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                      backgroundRepeat: "no-repeat",
                      backgroundPosition: "right 0.75rem center",
                      backgroundSize: "1.25rem",
                    }}
                  >
                    <option value="" disabled className="bg-secondary">{t("user_income_placeholder")}</option>
                    {INCOME_RANGES.map((range) => (
                      <option key={range.value} value={range.value} className="bg-secondary">
                        {range.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* ID Number */}
                <div>
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={12}
                    value={formData.id_number}
                    onChange={(e) =>
                      setFormData({ ...formData, id_number: e.target.value.replace(/\D/g, "").slice(0, 12) })
                    }
                    disabled={!isEditing}
                    placeholder={t("user_id_number")}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                </div>

                {/* Address */}
                <div>
                  <input
                    type="text"
                    value={formData.address}
                    onChange={(e) =>
                      setFormData({ ...formData, address: e.target.value })
                    }
                    disabled={!isEditing}
                    placeholder={t("profile_address")}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                </div>

                {/* Landmark */}
                <div>
                  <input
                    type="text"
                    value={formData.landmark}
                    onChange={(e) =>
                      setFormData({ ...formData, landmark: e.target.value })
                    }
                    disabled={!isEditing}
                    placeholder={t("profile_landmark")}
                    className="w-full px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                </div>

                {/* State and City - Side by side on larger screens */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {/* State (Mapped to API Data) */}
                  <select
                    value={formData.state_id}
                    onChange={(e) =>
                      setFormData({ ...formData, state_id: Number(e.target.value) })
                    }
                    disabled={!isEditing}
                    className="px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary focus:outline-none focus:border-primary transition-colors appearance-none cursor-pointer disabled:opacity-80"
                    style={{
                      backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239AA4FE'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                      backgroundRepeat: "no-repeat",
                      backgroundPosition: "right 0.75rem center",
                      backgroundSize: "1.25rem",
                    }}
                  >
                    <option value="" disabled className="bg-secondary text-primary">{t("user_state_placeholder")}</option>
                    {states.map((state) => (
                      <option key={state.state_id} value={state.state_id} className="bg-secondary text-primary">
                        {state.state_name}
                      </option>
                    ))}
                  </select>

                  {/* City */}
                  <input
                    type="text"
                    value={formData.city}
                    onChange={(e) =>
                      setFormData({ ...formData, city: e.target.value })
                    }
                    disabled={!isEditing}
                    placeholder={t("profile_city")}
                    className="px-4 py-3 bg-transparent border-2 border-[#0216CA] rounded-xl text-primary placeholder-accent focus:outline-none focus:border-primary transition-colors disabled:opacity-80"
                  />
                </div>
              </div>

              {/* Upload File Section */}
              <div className="pt-4">
                <div className="flex items-center gap-4">
                  {/* Left: Title */}
                  <h2 className="text-xl font-semibold text-primary whitespace-nowrap">
                    {t("profile_file")}
                  </h2>

                  {/* Right: Takes remaining space */}
                  <div className="flex-1 min-w-0">
                    {formData.file ? (
                      /* --- FILE UPLOADED STATE --- */
                      <div className="w-full bg-transparent border-2 border-[#0216CA] rounded-xl p-4 flex items-center justify-between">
                        <div className="flex items-center gap-3 overflow-hidden">
                          <FileText className="w-6 h-6 text-primary flex-shrink-0" />
                          <span className="text-primary font-semibold truncate">
                            {formData.file.name}
                          </span>
                        </div>

                        {isEditing && (
                          <button
                            onClick={(e) => {
                              e.preventDefault();
                              setFormData({ ...formData, file: null });
                            }}
                            className="ml-3 text-accent hover:text-[#0216CA] transition-colors"
                          >
                            <XCircle className="w-6 h-6" />
                          </button>
                        )}
                      </div>
                    ) : (
                      /* --- UPLOAD BUTTON STATE --- */
                      isEditing && (
                        <label className="cursor-pointer block w-full">
                          <input
                            type="file"
                            onChange={handleFileUpload}
                            className="hidden"
                            accept="image/jpeg,image/png,image/jpg,.pdf"
                          />
                          <div className="w-full py-3 px-4 bg-accent hover:bg-primary hover:text-white rounded-xl text-primary font-semibold transition-colors flex items-center justify-center gap-2">
                            <Upload className="w-5 h-5" />
                            <span>{t("user_upload_file")}</span>
                          </div>
                        </label>
                      )
                    )}
                  </div>
                </div>
              </div>

              {/* Past conversations with Tara */}
              <div className="pt-4">
                <button
                  type="button"
                  onClick={() => router.push("/session-history")}
                  className="w-full flex items-center justify-between gap-3 px-4 py-4 border-2 border-[#0216CA] rounded-xl text-primary font-semibold hover:bg-tertiary transition-colors"
                >
                  <span className="flex items-center gap-3">
                    <History className="w-6 h-6" />
                    {t("profile_session_history")}
                  </span>
                  <ChevronRight className="w-5 h-5" />
                </button>
              </div>

              {/* Logout Button */}
              <div className="pt-6">
                <p className="md:hidden text-neutral-50 text-center pb-3">
                  © Saathealth 2026
                </p>
                <button
                  onClick={handleLogout}
                  className="w-full py-4 bg-[#F60A0A] hover:bg-[#DC2626] text-white text-lg rounded-2xl transition-colors"
                >
                  {t("profile_log_out")}
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
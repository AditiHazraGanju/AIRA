/**
 * Centralised helpers for persisting user data to localStorage.
 * Always use these instead of raw localStorage calls so that the Header
 * and other mounted components are notified via the "user_name_updated" event.
 */

export interface StoredUser {
  sh_user_id: number;
  saathi_beneficiary_id: number;
  related_to_beneficiary_id: number | null;
  name: string | null;
  mobile: string | null;
}

// ── User name ─────────────────────────────────────────────────────────────────

/** Writes the display name and fires an event so mounted components update. */
export function setUserName(name: string): void {
  localStorage.setItem("user_name", name);
  window.dispatchEvent(new Event("user_name_updated"));
}

export function getUserName(): string {
  return localStorage.getItem("user_name") ?? "";
}

// ── Full user list (from OTP verify response) ─────────────────────────────────

/**
 * Saves the entire user_data array returned by the verify-OTP API.
 * Used to populate the member-selection dropdown on the profile page.
 */
export function saveUserList(users: StoredUser[]): void {
  localStorage.setItem("user_list", JSON.stringify(users));
}

export function getUserList(): StoredUser[] {
  try {
    const raw = localStorage.getItem("user_list");
    return raw ? (JSON.parse(raw) as StoredUser[]) : [];
  } catch {
    return [];
  }
}

// ── Active beneficiary IDs ────────────────────────────────────────────────────

export function setActiveBeneficiary(shUserId: number, beneficiaryId: number): void {
  localStorage.setItem("sh_user_id", String(shUserId));
  localStorage.setItem("saathi_beneficiary_id", String(beneficiaryId));
  window.dispatchEvent(new Event("active_beneficiary_updated"));
}

export function getActiveBeneficiaryId(): number | null {
  const val = localStorage.getItem("saathi_beneficiary_id");
  return val ? Number(val) : null;
}

// ── Clear all on logout ───────────────────────────────────────────────────────

export function clearUserStorage(): void {
  localStorage.removeItem("user_name");
  localStorage.removeItem("user_list");
  localStorage.removeItem("sh_user_id");
  localStorage.removeItem("saathi_beneficiary_id");
  localStorage.removeItem("auth_token");
  // Notify mounted components (e.g. Header) so they clear their local state immediately.
  window.dispatchEvent(new Event("user_name_updated"));
  // Notify AuthContext so isAuthenticated is cleared immediately on logout.
  window.dispatchEvent(new Event("auth_storage_updated"));
}
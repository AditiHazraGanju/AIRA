export const SEND_OTP_API = "/auth/send-otp";

export const VERIFY_OTP_API = "/auth/verify-otp";

export const REGISTRATION_API = "/auth/user/update/{sh_user_id}";

export const GET_STATE_API = "/auth/states";

export const GET_COMING_SOON_API = "/coming-soon/page-details/{page_name}";

export const SAVE_COMING_SOON_API = "/coming-soon/save-response";

export const GET_HERO_SLIDES_API = "/notification/hero-slides";

export const SAVE_NOTIFICATION_RESPONSE_API = "/notification/save-response";

export const BENEFICIARY_PROFILE_API = "/app/beneficiary-profile/{beneficiary_id}";

export const UPDATE_DEMOGRAPHICS_API = "/app/beneficiary-profile/{beneficiary_id}/demographics";

export const GET_ECARD_ORDERS_API = "/order/completed/ecard/{beneficiary_id}";

export const GET_PURCHASE_HISTORY_ORDERS_API = "/order/completed/purchase-history/{beneficiary_id}";

export const ALL_MEDIA = "/media-publication/get-media-publication";

/**
 * Validates and returns a safe internal relative redirect path.
 * Prevents open-redirect security vulnerabilities by rejecting external URLs,
 * protocol-relative URLs (//), and javascript: URIs.
 */
export function getSafeRedirect(redirect: string | null | undefined): string | null {
    if (!redirect) return null;

    // Must be a relative path starting with /
    if (!redirect.startsWith("/")) return null;

    // Prevent protocol-relative URLs like //evil.com
    if (redirect.startsWith("//")) return null;

    // Prevent javascript: or data: URIs
    if (redirect.includes(":") && !redirect.startsWith("/?")) return null;

    return redirect;
}
// AI Chatbot auth endpoints
export const CHATBOT_VERIFY_OTP_API = "/auth/chatbot/verify-otp";
export const CHATBOT_REGISTER_GUEST_API = "/auth/chatbot/register";
export const CHATBOT_PROFILE_API = "/auth/chatbot/profile"; // GET to read, POST (multipart) to update

export const GET_START_PITCH_API = "/healthtalk/start-pitch";

// Saved conversations (written by the voice relay, read here)
export const GET_SESSIONS_API = "/healthtalk/sessions";
export const GET_SESSION_API = "/healthtalk/sessions/{session_id}";
export const RATE_SESSION_API = "/healthtalk/sessions/{session_id}/rating";
export const SESSION_QA_FEEDBACK_API = "/healthtalk/sessions/{session_id}/qa/{qa_id}/feedback";


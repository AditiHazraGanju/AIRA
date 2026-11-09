import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, Method } from "axios";
import { BASE_URL } from "../const/conf";
import Logger from "./logger";

// Create Axios instance
const apiClient1: AxiosInstance = axios.create({
  baseURL: BASE_URL,
});

// Mirrors the shape returned by ResponseService.php
export interface ApiSuccessResponse<T = unknown> {
  success: true;
  status: number;
  message: string;
  data: T;
}

export interface ApiErrorResponse {
  success: false;
  status: number;
  message: string;
  errors: Record<string, string[]>;
}

export type ApiResponse<T = unknown> = ApiSuccessResponse<T> | ApiErrorResponse;

// Reuse AxiosRequestConfig directly — no need for an empty wrapper interface
type RequestConfig = AxiosRequestConfig;

type HTTPMethod = Method;

// Core request handler
const handleRequest = async <T = unknown>(
  client: AxiosInstance,
  method: HTTPMethod,
  url: string,
  data?: unknown,
  config: RequestConfig = {}
): Promise<ApiResponse<T>> => {
  const token = localStorage.getItem("auth_token") || localStorage.getItem("access_token");

  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }

  try {
    const response: AxiosResponse<ApiResponse<T>> = await client({
      method,
      url,
      data,
      ...config,
    });

    return response.data;
  } catch (error: unknown) {
    if (
      axios.isAxiosError(error) &&
      error.response?.status === 401
    ) {
      console.log("401 Unauthorized");
      Logger.warning(
        "401 Unauthorized",
        "api-handler",
        "handleRequest",
        { url, method }
      );
    } else {
      const status = axios.isAxiosError(error) ? (error.response?.status ?? 500) : 500;
      const apiData = axios.isAxiosError(error) ? (error.response?.data as ApiErrorResponse | undefined) : undefined;
      const apiMessage = apiData?.message ?? (axios.isAxiosError(error) ? error.message : "API request error");
      console.error("API request error:", error);
      Logger.error(
        apiMessage,
        "api-handler",
        "handleRequest",
        { url, method, status, api_errors: apiData?.errors ?? {} },
        status
      );
    }
    throw error;
  }
};

// Multipart form upload handler
const postForm = async <T = unknown>(
  client: AxiosInstance,
  url: string,
  formData: FormData,
  config: RequestConfig = {}
): Promise<ApiResponse<T>> => {
  const token = localStorage.getItem("auth_token") || localStorage.getItem("access_token");

  config.headers = {
    ...(config.headers ?? {}),
    Authorization: token ? `Bearer ${token}` : "",
    "Content-Type": "multipart/form-data",
  };

  try {
    const response: AxiosResponse<ApiResponse<T>> = await client.post(url, formData, config);
    return response.data;
  } catch (error: unknown) {
    const status = axios.isAxiosError(error) ? (error.response?.status ?? 500) : 500;
    console.error("Form upload error:", error);
    Logger.error(
      axios.isAxiosError(error) ? (error.message ?? "Form upload failed") : "Form upload error",
      "api-handler",
      "postForm",
      { url, status },
      status
    );
    throw error;
  }
};

// Blob download handler
const downloadBlob = async (
  client: AxiosInstance,
  url: string,
  filename?: string,
  config: RequestConfig = {}
): Promise<void> => {
  const token = localStorage.getItem("auth_token") || localStorage.getItem("access_token");

  config.headers = {
    ...(config.headers ?? {}),
    Authorization: token ? `Bearer ${token}` : "",
  };
  config.responseType = "blob";

  try {
    const response: AxiosResponse<Blob> = await client.get(url, config);

    // Get filename from Content-Disposition header or fall back to provided name
    let finalFilename = filename ?? "download.pdf";
    const contentDisposition = response.headers["content-disposition"] as string | undefined;
    if (contentDisposition) {
      const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
      if (match?.[1]) {
        finalFilename = match[1].replace(/['"]/g, "");
      }
    }

    // Trigger browser download
    const blob = new Blob([response.data], {
      type: response.headers["content-type"] as string,
    });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = finalFilename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error: unknown) {
    const status = axios.isAxiosError(error) ? (error.response?.status ?? 500) : 500;
    console.error("Blob download error:", error);
    Logger.error(
      axios.isAxiosError(error) ? (error.message ?? "Blob download failed") : "Blob download error",
      "api-handler",
      "downloadBlob",
      { url, filename, status },
      status
    );
    throw error;
  }
};

// Export API wrapper
const apiHandler = {
  get: <T = unknown>(client: AxiosInstance, url: string, config?: RequestConfig) =>
    handleRequest<T>(client, "get", url, undefined, config),

  post: <T = unknown>(client: AxiosInstance, url: string, data?: unknown, config?: RequestConfig) =>
    handleRequest<T>(client, "post", url, data, config),

  put: <T = unknown>(client: AxiosInstance, url: string, data?: unknown, config?: RequestConfig) =>
    handleRequest<T>(client, "put", url, data, config),

  delete: <T = unknown>(client: AxiosInstance, url: string, config?: RequestConfig) =>
    handleRequest<T>(client, "delete", url, undefined, config),

  postForm,
  downloadBlob,
};

export { apiClient1, apiHandler };
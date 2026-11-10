<?php

namespace App\Helpers;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiResponseHelper
{
    /**
     * Generate a standardized API response.
     *
     * @param array|object $data
     * @param bool $error
     * @param string $message
     * @param int $statusCode
     * @param array $metadata
     * @return JsonResponse
     */
    public static function setResponse($data, bool $error, string $message = "", int $statusCode = 200, array $metadata = []): JsonResponse
    {
        $response = [
            'error' => $error,
            'message' => $message !== "" ? $message : ($error ? "An error occurred" : "Data received successfully"),
            'data' => $data,
        ];

        // Include optional metadata if provided
        if (!empty($metadata)) {
            $response['metadata'] = $metadata;
        }

        return response()->json($response, $statusCode);
    }


    /**
     * Execute CURL request (Non-blocking for Logger)
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param mixed $data
     * @param bool $debug
     * @return string
     */
    public static function call_curl(string $url, string $method, array $headers = [], $data = null, bool $debug = false)
    {
        if (empty($url)) {
            return '';
        }

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        // METHOD
        $method = strtoupper($method);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        } elseif ($method === 'GET') {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } elseif ($method === 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        // HEADERS
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // RETURN RESPONSE
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // ✅ STRICT TIMEOUTS (max < 1 sec)
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 200); // 200ms connect
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 800);        // 800ms total max
        curl_setopt($curl, CURLOPT_NOSIGNAL, 1);

        // SSL
        if ($debug) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        }

        $response = curl_exec($curl);

        // ✅ FAIL SILENTLY (VERY IMPORTANT)
        if ($response === false) {
            curl_close($curl);
            return '';
        }

        curl_close($curl);

        return $response;
    }



    // public static function call_curl(string $url, string $method, array $headers = [], string $data = null, bool $debug = false)
    // {
    //     // Prepare initial log
    //     $logData = [
    //         'stage' => 'REQUEST_START',
    //         'url' => $url,
    //         'method' => $method,
    //         'headers' => $headers,
    //         'data' => $data,
    //         'timestamp' => date('Y-m-d H:i:s'),
    //     ];
    //     Helper::writeLog('debug', 'API REQUEST HELPER', 'TCM LOGGER', "Request start: " . json_encode($logData, JSON_UNESCAPED_SLASHES));
    //     Helper::writeLog('debug', 'API REQUEST HELPER', 'TCM LOGGER', "Request data: " . json_encode($data, JSON_UNESCAPED_SLASHES));

    //     $curl = curl_init();

    //     // Set the URL
    //     curl_setopt($curl, CURLOPT_URL, $url);

    //     // Set the request method
    //     switch (strtoupper($method)) {
    //         case 'POST':
    //             curl_setopt($curl, CURLOPT_POST, 1);
    //             if (!empty($data)) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    //             break;
    //         case 'GET':
    //             curl_setopt($curl, CURLOPT_HTTPGET, 1);
    //             break;
    //         case 'PUT':
    //             curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    //             if (!empty($data)) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    //             break;
    //         case 'DELETE':
    //             curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    //             break;
    //         default:
    //             throw new \InvalidArgumentException("Unsupported HTTP method: $method");
    //     }

    //     // Set headers
    //     if (!empty($headers)) {
    //         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //     }

    //     // Return the transfer as a string
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    //     // Debugging (optional)
    //     if ($debug) {
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    //     }

    //     // Execute cURL request
    //     $response = curl_exec($curl);
    //     $curlError = curl_error($curl);
    //     $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    //     // Prepare response log
    //     $responseLog = [
    //         'stage' => 'REQUEST_END',
    //         'url' => $url,
    //         'method' => $method,
    //         'headers_sent' => $headers,
    //         'request_data' => $data,
    //         'response_data' => $response,
    //         'http_status' => $httpCode,
    //         'curl_error' => $curlError ?: null,
    //         'timestamp' => date('Y-m-d H:i:s'),
    //     ];
    //     Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "Request end: " . json_encode($responseLog, JSON_UNESCAPED_SLASHES));

    //     // Close cURL session
    //     curl_close($curl);

    //     // Return response or throw error if cURL failed
    //     if ($response === false) {
    //         throw new \RuntimeException("cURL request failed: $curlError");
    //     }

    //     return $response;
    // }


    /**
     * App Logging api call
     * @param mixed $log_level
     * @param mixed $context
     * @param mixed $function_name
     * @param mixed $message
     * @param mixed $user_id
     * @param mixed $custom_data
     * @param mixed $session_id
     * @param mixed $generated_from
     * @return void
     */
    public static function TCMLogger(
        $log_level = 'INFO',
        $context = null,
        $function_name = null,
        $message = null,
        $user_id = null,
        $custom_data = [],
        $session_id = null,
        $generated_from = 'Backend',
        $error_code = 500
    ) {
        try {
            // getting logger basic info
            $apiBaseURL = env('TCMLOGGER_BASEURL');
            $debug = env('TCMLOGGER_DEBUG');
            $logEnv = env('TCMLOGGER_LOG_ENV');
            $client = env('TCMLOGGER_CLIENT');
            $domain = env('TCMLOGGER_DOMAIN');
            $app = env('TCMLOGGER_APP');
            $browser = request()->header('User-Agent');
            /**
             * now checking debug status and based on that creating log 
             * 1. If debug is true then log will be created with all type log
             * 2.  If debug is false then log will be not created 
             */
            if ($debug) {

                if ($browser) {
                    $browser = htmlspecialchars($browser, ENT_QUOTES, 'UTF-8');
                } else {
                    $browser = 'Unknown';
                }

                // $allowable_log_when_debug_false = ['INFO', 'ERROR', 'WARNING'];

                // preparing log data
                $logData = [
                    "client_name" => $client,
                    "domain_name" => $domain,
                    "application" => $app,
                    "user_id" => $user_id,
                    "session_id" => $session_id,
                    "browser_info" => $browser,
                    "generated_from" => $generated_from,
                    "log_level" => $log_level,
                    "function_name" => $function_name,
                    "context" => $context,
                    "message" => $message,
                    "custom_data" => $custom_data
                ];

                // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "Log data: " . json_encode($logData, JSON_UNESCAPED_SLASHES));

                // selectinug endpoint based on log level
                if ($log_level === 'ERROR') {
                    // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "Error Log level: $log_level");
                    $endpoint = '/error';
                    $logData['error_stack'] = "";
                    $logData['error_code'] = $error_code;
                } else {
                    // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "Log level: $log_level");
                    $endpoint = '/process';
                }

                // final url to log
                $url = $apiBaseURL . $endpoint;
                // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "url: $url");

                // api headers
                $headers = [
                    'Content-Type: application/json',
                    'User-Agent: Production Environment',
                    'X-Devlog-Token: ' . env('TCMLOGGER_TOKEN'),
                ];
                // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "Log headers: " . json_encode($headers, JSON_UNESCAPED_SLASHES));

                // calling curl to log
                $loggResponse = self::call_curl($url, 'POST', $headers, json_encode($logData, JSON_UNESCAPED_SLASHES));
                // Helper::writeLog('debug', 'API RESPONSE HELPER', 'TCM LOGGER', "loggResponse: $loggResponse");
            }
        } catch (Exception $e) {
            // exception handling
            Helper::writeLog('error', 'ApiResponseHelper', 'TCMLogger', "An error occurred: " . $e->getMessage());
        }
    }
}

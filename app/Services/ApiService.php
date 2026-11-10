<?php

namespace App\Services;

// Import Facades and Classes
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

// Import Helpers
use App\Helpers\LoggerHelper;

/**
 * ApiService class for making external API calls based on configuration.
 * Developed by Snow4.
 * Updated with file upload support and configurable timeout.
 */

class ApiService
{
    /**
     * Call an external API based on the configuration.
     *
     * @param string $name The name of the API as defined in config/saathi_api.php
     * @param array $params Optional parameters to send with the request
     * @param array|null $urlParams Optional associative array for endpoint placeholders (e.g., ['id' => 12])
     * @return \Illuminate\Http\Client\Response
     * @throws Exception
     */
    public static function call(string $name, array $params = [], ?array $urlParams = null)
    {

        LoggerHelper::logInfo(
            message: 'Request received to fire API: ' . $name,
            context: __CLASS__,
            fn: __FUNCTION__,
            custom_data: [
                'name' => $name,
                'body_parameters' => self::sanitizeParamsForLogging($params),
                'url_parameters' => $urlParams ?? [],
            ]
        );

        // Collect necessary details from config/saathi_api.php
        $config = config('saathi_api.' . $name);

        // Check if config exists
        if (!$config) {
            // if config not found, log error and throw exception
            LoggerHelper::logError(
                message: "API config for '{$name}' not found.",
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'name' => $name,
                ]
            );

            throw new Exception("API config for '{$name}' not found.");
        }

        // Aira API base URL and the shared admin key (config/services.php)
        $baseUrl = rtrim((string) config('services.aira_api.url'), '/');
        $apiKey = (string) config('services.aira_api.key');
        $endpoint = $config['endpoint'];

        // Replace endpoint placeholders with values from $urlParams
        if ($urlParams !== null) {
            foreach ($urlParams as $key => $value) {
                $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
            }
        }

        // Prepare request
        $url = $baseUrl . $endpoint;
        $method = strtolower($config['method']);

        // Server-to-server auth: the API's AdminApiKey middleware checks this
        // header against its own ADMIN_API_KEY.
        $headers = [
            'X-Admin-Key' => $apiKey,
            'Accept' => 'application/json',
        ];

        // ⬇️ NEW: Determine timeout based on endpoint
        $timeout = self::getTimeoutForEndpoint($name);

        // Check if params contain files
        $hasFiles = self::hasFiles($params);

        // Make the HTTP request based on the method with timeout
        try {
            switch ($method) {
                case 'get':
                    $response = Http::timeout($timeout)
                        ->connectTimeout(10)
                        ->withHeaders($headers)
                        ->get($url, $params);
                    break;
                case 'post':
                    if ($hasFiles) {
                        $response = self::sendMultipartRequest($url, $headers, $params, $timeout);
                    } else {
                        $response = Http::timeout($timeout)
                            ->connectTimeout(10)
                            ->withHeaders($headers)
                            ->post($url, $params);
                    }
                    break;
                case 'put':
                    if ($hasFiles) {
                        $response = self::sendMultipartRequest($url, $headers, $params, $timeout);
                    } else {
                        $response = Http::timeout($timeout)
                            ->connectTimeout(10)
                            ->withHeaders($headers)
                            ->put($url, $params);
                    }
                    break;
                case 'patch':
                    if ($hasFiles) {
                        $response = self::sendMultipartRequest($url, $headers, $params, $timeout);
                    } else {
                        $response = Http::timeout($timeout)
                            ->connectTimeout(10)
                            ->withHeaders($headers)
                            ->patch($url, $params);
                    }
                    break;
                case 'delete':
                    $response = Http::timeout($timeout)
                        ->connectTimeout(10)
                        ->withHeaders($headers)
                        ->delete($url, $params);
                    break;
                case 'head':
                    $response = Http::timeout($timeout)
                        ->connectTimeout(10)
                        ->withHeaders($headers)
                        ->head($url, $params);
                    break;
                case 'options':
                    $response = Http::timeout($timeout)
                        ->connectTimeout(10)
                        ->withHeaders($headers)
                        ->send('OPTIONS', $url, $params);
                    break;
                default:
                    throw new Exception("Unsupported method '{$config['method']}'");
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            LoggerHelper::logError(
                message: 'API connection timeout or network error: ' . $name,
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'name' => $name,
                    'url' => $url,
                    'timeout' => $timeout,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }

        LoggerHelper::logInfo(
            message: 'API call completed: ' . $name,
            context: __CLASS__,
            fn: __FUNCTION__,
            custom_data: [
                'name' => $name,
                'status' => $response->status(),
                'response_message' => $response->json('message') ?? 'N/A',
                'timeout_used' => $timeout,
            ]
        );
        return $response;
    }

    /**
     * Get timeout value based on endpoint
     * Returns higher timeout for data import operations
     *
     * @param string $name
     * @return int Timeout in seconds
     */
    private static function getTimeoutForEndpoint(string $name): int
    {
        // Special handling for data import endpoints
        $longRunningEndpoints = [
            'upload-data-import',
            'process-chunk-data-import',
        ];

        if (in_array($name, $longRunningEndpoints)) {
            return 300; // 5 minutes for import operations
        }

        // Check if endpoint name contains certain keywords
        if (str_contains($name, 'import') || str_contains($name, 'batch') || str_contains($name, 'bulk')) {
            return 300;
        }

        // Default timeout for regular API calls
        return 60; // Increased from 30 to 60 seconds
    }

    /**
     * Check if params contain any files (recursively)
     *
     * @param mixed $data
     * @return bool
     */
    private static function hasFiles($data): bool
    {
        if ($data instanceof UploadedFile) {
            return true;
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                if (self::hasFiles($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Send multipart request with files
     *
     * @param string $url
     * @param array $headers
     * @param array $params
     * @param int $timeout
     * @return \Illuminate\Http\Client\Response
     */
    private static function sendMultipartRequest(string $url, array $headers, array $params, int $timeout = 30)
    {
        // Remove Content-Type from headers as Laravel will set it automatically for multipart
        unset($headers['Content-Type']);

        $http = Http::timeout($timeout)
            ->connectTimeout(10)
            ->withHeaders($headers);

        // Flatten and separate files from regular data
        $flattenedData = self::flattenDataForMultipart($params);

        LoggerHelper::logInfo(
            message: 'Sending multipart request with files',
            context: __CLASS__,
            fn: __FUNCTION__,
            custom_data: [
                'url' => $url,
                'fields_count' => count($flattenedData),
                'file_fields' => array_keys(array_filter($flattenedData, function ($v) {
                    return $v instanceof UploadedFile;
                })),
                'timeout' => $timeout,
            ]
        );

        // Build the multipart array
        $multipart = [];

        foreach ($flattenedData as $key => $value) {
            if ($value instanceof UploadedFile) {
                // Add files
                $multipart[] = [
                    'name' => $key,
                    'contents' => fopen($value->getRealPath(), 'r'),
                    'filename' => $value->getClientOriginalName()
                ];
            } else {
                // Add regular fields
                $multipart[] = [
                    'name' => $key,
                    'contents' => (string)$value
                ];
            }
        }

        // Send the request with multipart data using attach
        return $http->asMultipart()->post($url, $multipart);
    }

    /**
     * Flatten nested array data for multipart form data
     * Converts: ['languages' => [0 => ['title' => 'Test']]]
     * To: ['languages[0][title]' => 'Test']
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    private static function flattenDataForMultipart(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix === '' ? $key : "{$prefix}[{$key}]";

            if ($value instanceof UploadedFile) {
                $result[$newKey] = $value;
            } elseif (is_array($value)) {
                $result = array_merge($result, self::flattenDataForMultipart($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Sanitize params for logging (remove file objects)
     *
     * @param mixed $data
     * @return mixed
     */
    private static function sanitizeParamsForLogging($data)
    {
        if ($data instanceof UploadedFile) {
            return '[FILE: ' . $data->getClientOriginalName() . ']';
        }

        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = self::sanitizeParamsForLogging($value);
            }
            return $sanitized;
        }

        return $data;
    }
}
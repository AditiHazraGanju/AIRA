<?php

namespace App\Helpers;

// Import facades
use Illuminate\Support\Facades\Session;

class LoggerHelper
{
    /**
     * Log message with info level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logInfo($message = '', $context = '', $fn = '', $custom_data = [], $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        Helper::writeLog('info', $context, $fn, $message);
        ApiResponseHelper::TCMLogger(
            'INFO',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from
        );
    }

    /**
     * Log message with error level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logError($message = '', $context = '', $fn = '', $custom_data = [], $error_code = null, $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('error', $context, $fn, $message);

        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        ApiResponseHelper::TCMLogger(
            'ERROR',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from,
            $error_code,
        );
    }

    /**
     * Log message with warning level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logWarning($message = '', $context = '', $fn = '', $custom_data = [], $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('warning', $context, $fn, $message);
        
        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        ApiResponseHelper::TCMLogger(
            'WARNING',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from
        );
    }

    /**
     * Log message with debug level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logDebug($message = '', $context = '', $fn = '', $custom_data = [], $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('debug', $context, $fn, $message);

        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';
        
        ApiResponseHelper::TCMLogger(
            'DEBUG',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from
        );
    }

    /**
     * Log message with critical level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logCritical($message = '', $context = '', $fn = '', $custom_data = [], $error_code = null, $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('critical', $context, $fn, $message);

        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        ApiResponseHelper::TCMLogger(
            'CRITICAL',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from,
            $error_code,
        );
    }

    /**
     * Log message with alert level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logAlert($message = '', $context = '', $fn = '', $custom_data = [], $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('alert', $context, $fn, $message);

        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        ApiResponseHelper::TCMLogger(
            'ALERT',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from
        );
    }

    /**
     * Log message with emergency level.
     *
     * @param string $message
     * @param string $context
     * @param string $fn
     * @return void
     */
    public static function logEmergency($message = '', $context = '', $fn = '', $custom_data = [], $error_code = null, $user_id = null, $session_id = null, $generated_from = 'Backend')
    {
        Helper::writeLog('emergency', $context, $fn, $message);

        // For this project, we won’t pass some variables from outside, instead, we’ll define them here.
        $user_id = null; // the admin panel has no login
        $generated_from = ucfirst(env('APP_ENV')) . ' Admin Backend';
        $session_id = Session::getId()?? 'N/A';

        ApiResponseHelper::TCMLogger(
            'EMERGENCY',
            $context,
            $fn,
            $message,
            $user_id,
            $custom_data,
            $session_id,
            $generated_from,
            500,
        );
    }
}

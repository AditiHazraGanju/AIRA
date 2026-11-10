<?php


namespace App\Helpers;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Str;

class Helper
{
    /**
     * To maintain log text
     * @param mixed $log_level
     * @param string $module
     * @param string $function
     * @param mixed $message
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return void
     */
    public static function writeLog($log_level = 'info', string $module = null, string $function = null, $message = null)
    {
        try {
            if (method_exists(Log::channel('single'), $log_level)) {
                Log::channel('single')->$log_level("Module# $module || Function# $function || $message");
            } else {
                throw new InvalidArgumentException("Invalid log level: $log_level");
            }
            return;
        } catch (Exception $err) {
            // If the date is not in a recognizable format, return null
            throw new Exception("An error occurred: " . $err->getMessage());
        }
    }
}

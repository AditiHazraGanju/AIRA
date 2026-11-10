<?php

namespace App\Services;

class ResponseService
{
    public static function success($message = 'Success', $code = 200, $data = [], $meta = [])
    {
        return response()->json([
            'success' => true,
            'status'  => $code,   
            'message' => $message,
            'data'    => $data,
        ], $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error($message = 'Something went wrong', $code = 400, $errors = [], $meta = [])
    {
        return response()->json([
            'success' => false,
            'status'  => $code, 
            'message' => $message,
            'errors'  => $errors,
        ], $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
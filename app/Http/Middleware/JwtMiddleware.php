<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;

// Import Services
use App\Services\ResponseService;


class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            return ResponseService::error('Unauthorized, Access Denied', 401);
        }

        // A valid signature isn't enough: the user must still exist (tokens of
        // deleted users would otherwise pass, since authenticate() returns false).
        if (! $user) {
            return ResponseService::error('Unauthorized, Access Denied', 401);
        }

        return $next($request);
    }
}

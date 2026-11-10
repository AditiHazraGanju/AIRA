<?php

namespace App\Http\Middleware;

use App\Services\ResponseService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * For endpoints read by both sides: the admin panel (X-Admin-Key) and the Aira
 * app or healthtalk-relay (an Aira user's JWT). Guardrails and start pitch.
 */
class JwtOrAdminApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.admin.api_key');
        $given = (string) $request->header('X-Admin-Key');

        if ($expected !== '' && $given !== '' && hash_equals($expected, $given)) {
            $request->attributes->set('is_admin', true);

            return $next($request);
        }

        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return ResponseService::error('Unauthorized, Access Denied', 401);
            }
        } catch (Exception $e) {
            return ResponseService::error('Unauthorized, Access Denied', 401);
        }

        return $next($request);
    }
}

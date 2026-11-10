<?php

namespace App\Http\Middleware;

use App\Services\ResponseService;
use Closure;
use Illuminate\Http\Request;

/**
 * Admin endpoints (content library, guardrails, start pitch, reports, SH users)
 * are called only by the admin panel, server to server. The panel proves it is
 * the panel by sending the shared secret in the X-Admin-Key header; it must
 * equal config('services.admin.api_key') (ADMIN_API_KEY in .env).
 */
class AdminApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.admin.api_key');
        $given = (string) $request->header('X-Admin-Key');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return ResponseService::error('Unauthorized, Access Denied', 401);
        }

        $request->attributes->set('is_admin', true);

        return $next($request);
    }
}

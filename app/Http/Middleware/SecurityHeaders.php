<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every request:
 * - in production, redirects plain HTTP to HTTPS;
 * - adds security headers (HSTS only over HTTPS);
 * - stops browsers from caching pages shown to a signed-in user, so confidential records are
 *   not kept in the cache of a shared computer or shown again with the Back button after logout.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && ! $request->secure()) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user()) {
            $headers->set('Cache-Control', 'no-store, private');
            $headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}

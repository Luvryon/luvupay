<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardened security headers for the merchant console + public pages.
 * Alpine 3 needs script-src 'unsafe-eval'; keep scripts external (no unsafe-inline).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '0'); // modern browsers: CSP only; disable legacy XSS auditor
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()'
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $isProd = app()->environment('production') || $request->isSecure();
        if ($isProd) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        // CSP: no unsafe-inline scripts; styles allowed inline for AG Grid / console theme
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "object-src 'none'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "script-src 'self' 'unsafe-eval'",
            "connect-src 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "upgrade-insecure-requests",
        ]);

        // Report-only can be enabled later via env; enforce by default
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

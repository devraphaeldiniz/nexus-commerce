<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiCsrf
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/webhooks/*') || $request->is('api/auth/*') || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $cookie = $request->cookie('nexus_csrf');
        $header = $request->header('X-CSRF-TOKEN') ?: $request->header('X-XSRF-TOKEN');
        if (!$cookie || !$header || !hash_equals($cookie, $header)) {
            return response()->json(['message' => 'Token CSRF inválido ou ausente.'], 419);
        }
        return $next($request);
    }

    public static function issue(Response $response): Response
    {
        $token = Str::random(64);
        return $response->withCookie(cookie('nexus_csrf', $token, 60, '/', config('session.domain'), false, false, false, config('session.same_site', 'lax')));
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInternalUsageToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = $request->header('X-Internal-Usage-Key');

        if ($providedToken === null || $providedToken === '') {
            return new JsonResponse([
                'message' => 'Internal usage token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $expectedToken = (string) config('internal_usage.token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse([
                'message' => 'Invalid internal usage token.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCentralAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = $request->header('X-Central-Admin-Key');

        if ($providedToken === null || $providedToken === '') {
            return new JsonResponse([
                'message' => 'Central admin token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $expectedToken = (string) config('central_admin.token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse([
                'message' => 'Invalid central admin token.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('central_admin_authenticated', true);

        return $next($request);
    }
}
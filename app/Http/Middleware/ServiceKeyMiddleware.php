<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ServiceKeyMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $serviceKey = $request->header('X-Service-Key');
        $expected   = config('services.service_key', env('SERVICE_KEY'));

        if (empty($expected) || $serviceKey !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Invalid or missing service key.',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}

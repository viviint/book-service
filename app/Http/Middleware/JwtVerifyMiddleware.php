<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class JwtVerifyMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        try {
            $publicKey = config('jwt.public_key');

            if (empty($publicKey)) {
                throw new \RuntimeException('JWT public key not configured');
            }

            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            $request->attributes->set('auth_user_id', $decoded->sub ?? $decoded->id ?? null);
            $request->attributes->set('auth_email',   $decoded->email ?? null);
            $request->attributes->set('auth_role',    $decoded->role  ?? null);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}

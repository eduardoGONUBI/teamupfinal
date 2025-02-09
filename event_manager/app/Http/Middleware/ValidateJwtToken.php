<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class ValidateJwtToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Authorization token not found'], 401);
        }

        try {
            $algorithm = env('JWT_ALGO', 'HS256');

            if ($algorithm === 'RS256') {
                $publicKey = env('JWT_PUBLIC_KEY');
                $decoded = JWT::decode($token, new Key($publicKey, $algorithm));
            } else {
                $secretKey = env('JWT_SECRET');
                $decoded = JWT::decode($token, new Key($secretKey, $algorithm));
            }

            // Attach user ID to the request
            $request->attributes->add(['user_id' => $decoded->sub]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}

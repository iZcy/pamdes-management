<?php


// app/Http/Middleware/ValidateVillageToken.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidateVillageToken
{
    /**
     * Handle an incoming request for API endpoints requiring village system authentication.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(10)->get(config('services.village_system.url') . '/api/auth/validate');

            if ($response->successful()) {
                $userData = $response->json();
                $request->merge(['auth_user' => $userData]);
                return $next($request);
            }

            Log::warning('Village token validation failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return response()->json(['error' => 'Invalid token'], 401);
        } catch (\Exception $e) {
            Log::error('Village token validation error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
            ]);

            return response()->json(['error' => 'Authentication service unavailable'], 503);
        }
    }
}

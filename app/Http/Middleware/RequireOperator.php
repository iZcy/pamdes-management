<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireOperator
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            // Redirect to Filament login if not authenticated
            return redirect()->route('filament.admin.auth.login');
        }

        if (!Auth::user()->isOperator()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Operator access required'], 403);
            }

            abort(403, 'Operator access required');
        }

        return $next($request);
    }
}
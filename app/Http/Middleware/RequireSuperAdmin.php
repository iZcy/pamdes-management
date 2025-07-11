<?php
// app/Http/Middleware/RequireSuperAdmin.php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !User::find(Auth::user()->id)->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Super admin access required'], 403);
            }

            abort(403, 'Super admin access required');
        }

        return $next($request);
    }
}

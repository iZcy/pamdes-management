<?php

// app/Http/Middleware/AdminAuthenticate.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next, string ...$guards)
    {
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        return $next($request);
    }
}

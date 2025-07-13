<?php
// app/Http/Middleware/AutoLogoutOnAccessDenied.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\User;

class AutoLogoutOnAccessDenied
{
    /**
     * Handle an incoming request and auto-logout if access requirements not met
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip check for non-authenticated routes
        if (!Auth::check()) {
            return $next($request);
        }

        $user = User::find(Auth::user()->id);

        if (!$user) {
            $this->performLogout($request, 'User not found');
            return $this->redirectToLogin($request, 'User account not found');
        }

        // Check if user is still active
        if (!$user->is_active) {
            $this->performLogout($request, 'User account deactivated');
            return $this->redirectToLogin($request, 'Your account has been deactivated');
        }

        // Check access requirements based on current context
        if (!$this->checkUserAccess($user, $request)) {
            $this->performLogout($request, 'Access requirements not met');
            return $this->redirectToLogin($request, 'You no longer have access to this system');
        }

        return $next($request);
    }

    /**
     * Check if user still has required access
     */
    protected function checkUserAccess(User $user, Request $request): bool
    {
        $currentVillageId = config('pamdes.current_village_id');
        $isSuperAdminDomain = $request->getHost() === config('pamdes.domains.super_admin', env('PAMDES_SUPER_ADMIN_DOMAIN'));
        $isAdminRoute = $request->is('admin') || $request->is('admin/*');

        // For admin routes, use Filament's access check
        if ($isAdminRoute) {
            try {
                $panel = app(\Filament\Panel::class);
                return $user->canAccessPanel($panel);
            } catch (\Exception $e) {
                Log::warning('Error checking panel access', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }

        // For super admin users
        if ($user->isSuperAdmin()) {
            // if domain is not super admin, redirect
            if (!$isSuperAdminDomain) {
                return false;
            }

            return true;
        }

        // For village admin users
        if ($user->isVillageAdmin()) {
            // Village admin cannot access super admin domain
            if ($isSuperAdminDomain) {
                return false;
            }

            // If we have a village context, check if user has access to it
            if ($currentVillageId) {
                return $user->hasAccessToVillage($currentVillageId);
            }

            // If no village context but user has villages, allow access
            return $user->getAccessibleVillages()->count() > 0;
        }

        // Unknown role
        return false;
    }

    /**
     * Perform logout operations
     */
    protected function performLogout(Request $request, string $reason): void
    {
        $userId = Auth::id();

        Log::info('Auto-logout triggered', [
            'user_id' => $userId,
            'reason' => $reason,
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'current_village_id' => config('pamdes.current_village_id'),
            'is_super_admin_domain' => config('pamdes.is_super_admin_domain'),
        ]);

        // Logout user
        Auth::logout();

        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear any cached user data
        Session::flush();
    }

    /**
     * Redirect to appropriate login page with message
     */
    protected function redirectToLogin(Request $request, string $message)
    {
        // Store logout message in session
        session()->flash('logout_message', $message);
        session()->flash('logout_type', 'access_denied');

        // For API requests, return JSON response
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Access denied',
                'message' => $message,
                'redirect' => route('home')
            ], 403);
        }

        // For admin routes, redirect to login with admin context
        if ($request->is('admin') || $request->is('admin/*')) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', $message);
        }

        // For regular web routes, redirect to standard login
        return redirect()->route('home');
    }
}

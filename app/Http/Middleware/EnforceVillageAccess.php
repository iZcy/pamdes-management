<?php
// app/Http/Middleware/EnforceVillageAccess.php - New middleware for admin access control

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceVillageAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Only apply to admin routes
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return $next($request);
        }

        // Skip if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $user = User::with('village')->find($user->id);
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        $tenantContext = config('pamdes.tenant');

        // Super admin users can access everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // For village-specific domains, ensure user belongs to that village
        if ($tenantContext && $tenantContext['type'] === 'village_website') {
            $requestedVillageId = $tenantContext['village_id'];

            // Regular admin can only access their assigned village
            if ($user->village_id !== $requestedVillageId) {
                abort(403, 'You do not have access to this village administration.');
            }
        }

        // For super admin domain, regular users should not have access
        if ($tenantContext && $tenantContext['is_super_admin'] && !$user->isSuperAdmin()) {
            abort(403, 'You do not have super admin privileges.');
        }

        return $next($request);
    }
}

<?php
// app/Http/Middleware/EnsurePublicWebsite.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePublicWebsite
{
    public function handle(Request $request, Closure $next)
    {
        $tenantType = $request->attributes->get('tenant_type');

        if ($tenantType !== 'public_website') {
            abort(404);
        }

        return $next($request);
    }
}

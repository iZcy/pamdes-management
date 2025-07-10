<?php
// app/Http/Middleware/EnsureVillageWebsite.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVillageWebsite
{
    public function handle(Request $request, Closure $next)
    {
        $tenantType = $request->attributes->get('tenant_type');

        if ($tenantType !== 'village_website') {
            abort(404);
        }

        return $next($request);
    }
}

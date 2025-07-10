<?php
// bootstrap/app.php - Updated for multi-tenant architecture

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'village.context' => \App\Http\Middleware\ResolveVillageContext::class,
            'village.access' => \App\Http\Middleware\EnforceVillageAccess::class,
            'ensure.village' => \App\Http\Middleware\EnsureVillageWebsite::class,
            'ensure.public' => \App\Http\Middleware\EnsurePublicWebsite::class,
        ]);

        // Global web middleware
        $middleware->web(append: [
            // Village context should be applied globally for tenant resolution
        ]);

        // API middleware
        $middleware->api(prepend: [
            // Add tenant context to API routes if needed
        ]);

        // Throttling
        $middleware->throttleApi();
    })
    ->withProviders([
        App\Providers\VillageServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling
        $exceptions->render(function (\Throwable $e, $request) {
            // Log all exceptions with tenant context for debugging
            if (!$e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                $tenantContext = $request->attributes->get('tenant_type', 'unknown');
                $villageId = $request->attributes->get('village_id');

                Log::error('Application exception: ' . $e->getMessage(), [
                    'exception' => $e,
                    'tenant_type' => $tenantContext,
                    'village_id' => $villageId,
                    'request_url' => $request->fullUrl(),
                    'request_method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ]);
            }

            // Handle tenant-specific errors
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $tenantType = $request->attributes->get('tenant_type');

                // Custom 404 pages based on tenant
                if ($tenantType === 'village_not_found') {
                    return response()->view('errors.village-not-found', [], 404);
                }

                if ($tenantType === 'unknown') {
                    return response()->view('errors.unknown-domain', [], 404);
                }
            }

            // Handle access denied errors
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                $tenantType = $request->attributes->get('tenant_type');
                $village = $request->attributes->get('village');

                return response()->view('errors.access-denied', [
                    'tenant_type' => $tenantType,
                    'village' => $village,
                ], 403);
            }

            // Return JSON for API requests
            // if ($request->expectsJson()) {
            //     $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            //     return response()->json([
            //         'error' => 'An error occurred',
            //         'message' => app()->environment('production')
            //             ? 'Internal Server Error'
            //             : $e->getMessage(),
            //         'tenant' => [
            //             'type' => $request->attributes->get('tenant_type', 'unknown'),
            //             'village_id' => $request->attributes->get('village_id'),
            //         ]
            //     ], $statusCode);
            // }

            // Let Laravel handle other exceptions normally
            return null;
        });
    })->create();

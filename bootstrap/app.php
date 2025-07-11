<?php
// Update bootstrap/app.php - Add the session domain middleware

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'village.context' => \App\Http\Middleware\SetVillageContext::class,
            'super.admin' => \App\Http\Middleware\RequireSuperAdmin::class,
            'session.domain' => \App\Http\Middleware\SetSessionDomain::class,
        ]);

        // Apply middleware to web routes
        $middleware->web(append: [
            \App\Http\Middleware\SetVillageContext::class,
        ]);

        // Throttling
        $middleware->throttleApi();
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\VillageServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        // Simple exception handling
        $exceptions->render(function (\Throwable $e, $request) {
            // Handle model not found
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Resource not found'], 404);
                }
                return back()->withErrors(['error' => 'Resource not found']);
            }

            // Return JSON for API requests
            if ($request->expectsJson()) {
                $statusCode = 500; // default

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $statusCode = 404;
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $statusCode = 422;
                }

                return response()->json([
                    'error' => app()->environment('production') ? 'Internal Server Error' : $e->getMessage(),
                ], $statusCode);
            }

            return null;
        });
    })->create();

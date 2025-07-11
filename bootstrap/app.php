<?php
// bootstrap/app.php - Fixed middleware order for session handling

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
            'auto.logout' => \App\Http\Middleware\AutoLogoutOnAccessDenied::class,
        ]);

        // IMPORTANT: Add session domain middleware BEFORE web middleware group
        $middleware->web(prepend: [
            \App\Http\Middleware\SetSessionDomain::class,  // FIRST: Set session domain before session starts
        ]);

        // Apply middleware to web routes in correct order AFTER session starts
        $middleware->web(append: [
            \App\Http\Middleware\SetVillageContext::class,  // THEN: Set village context
            \App\Http\Middleware\AutoLogoutOnAccessDenied::class, // FINALLY: Check access and auto-logout
        ]);

        // Apply auto-logout to authenticated API routes
        $middleware->api(append: [
            \App\Http\Middleware\AutoLogoutOnAccessDenied::class,
        ]);

        // Throttling
        $middleware->throttleApi();
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\VillageServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle auto-logout exceptions gracefully
        $exceptions->render(function (\Throwable $e, $request) {
            // Handle CSRF token mismatch specifically
            if ($e instanceof \Illuminate\Session\TokenMismatchException) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Token mismatch',
                        'message' => 'Your session has expired. Please refresh the page and try again.',
                        'redirect' => $request->url()
                    ], 419);
                }

                return redirect()->back()
                    ->withInput($request->except('_token'))
                    ->withErrors(['error' => 'Your session has expired. Please try again.']);
            }

            // Handle model not found
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Resource not found'], 404);
                }
                return back()->withErrors(['error' => 'Resource not found']);
            }

            // Handle authentication exceptions
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Unauthenticated',
                        'message' => 'Your session has expired. Please log in again.',
                        'redirect' => route('home')
                    ], 401);
                }

                return redirect()->route('home')
                    ->with('error', 'Your session has expired. Please log in again.');
            }

            // Handle authorization exceptions (access denied)
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Access denied',
                        'message' => 'You do not have permission to access this resource.',
                        'redirect' => route('home')
                    ], 403);
                }

                return redirect()->route('home')
                    ->with('error', 'Access denied. Please log in with appropriate permissions.');
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

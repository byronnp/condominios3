<?php

use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RequireAdminAccess;
use App\Http\Middleware\RequireRole;
use App\Support\ApiResponder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => JwtAuthenticate::class,
            'admin.access' => RequireAdminAccess::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiResponder::class)
                ->error('Validation error', 422, $exception->errors())
                ->respond();
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiResponder::class)
                ->error('Resource not found', 404)
                ->respond();
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiResponder::class)
                ->error($exception->getMessage() ?: 'Request error', $exception->getStatusCode())
                ->respond();
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::error('Unhandled API error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return app(ApiResponder::class)
                ->error('Internal server error', 500)
                ->respond();
        });
    })->create();

<?php

use App\Exceptions\DomainException;
use App\Exceptions\SheetConflictException;
use App\Http\Middleware\EnsureCapability;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (y cualquier reverse proxy) termina TLS afuera: hace falta
        // confiar en X-Forwarded-* para URL HTTPS y cookies secure.
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('api', EnsureUserIsActive::class);

        $middleware->alias([
            'role' => EnsureRole::class,
            'capability' => EnsureCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([
            DomainException::class,
            SheetConflictException::class,
        ]);

        $exceptions->context(function () {
            $request = request();

            return array_filter([
                'user_id' => $request?->user()?->id,
                'company_id' => $request?->user()?->company_id,
                'path' => $request?->path(),
                'method' => $request?->method(),
                'ip' => $request?->ip(),
            ], fn ($value) => $value !== null && $value !== '');
        });

        $exceptions->render(function (SheetConflictException $e, Request $request) {
            Log::warning('Conflicto de planilla: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'path' => $request->path(),
            ]);
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        });
        $exceptions->render(function (DomainException $e, Request $request) {
            Log::warning('Regla de negocio: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'path' => $request->path(),
            ]);
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        });
    })->create();

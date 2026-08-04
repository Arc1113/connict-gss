<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::get('/up', function () {
                try {
                    Event::dispatch(new DiagnosingHealth);
                } catch (Throwable $exception) {
                    report($exception);

                    return response()->json(['status' => 'down'], 500);
                }

                return response()->json(['status' => 'up']);
            })->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production traffic arrives through the local Caddy HTTPS proxy.
        // Honor its forwarded scheme and host for secure redirects and assets.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('dashboard'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

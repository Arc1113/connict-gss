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

$runtimeEnvironment = getenv('GSS_ENV_FILE');
$dataDirectory = getenv('GSS_DATA_DIR');

if (! is_string($dataDirectory) || $dataDirectory === '') {
    $environmentFile = is_string($runtimeEnvironment) && $runtimeEnvironment !== ''
        ? $runtimeEnvironment
        : dirname(__DIR__).'/.env';

    if (is_file($environmentFile) && preg_match('/^GSS_DATA_DIR=(.+)$/m', (string) file_get_contents($environmentFile), $match) === 1) {
        $dataDirectory = trim($match[1], " \t\n\r\0\x0B\"'");
    }
}

$app = Application::configure(basePath: dirname(__DIR__))
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

// The Windows launcher supplies these process-level values. Keeping the
// generated environment file and every writable Laravel directory outside the
// installed release means upgrades never overwrite ceremony data.
if (is_string($runtimeEnvironment) && $runtimeEnvironment !== '' && is_file($runtimeEnvironment)) {
    $app->useEnvironmentPath(dirname($runtimeEnvironment));
    $app->loadEnvironmentFrom(basename($runtimeEnvironment));
}

if (is_string($dataDirectory) && $dataDirectory !== '') {
    $app->useStoragePath(rtrim($dataDirectory, '/\\').DIRECTORY_SEPARATOR.'storage');
}

return $app;

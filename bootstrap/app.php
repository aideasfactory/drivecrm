<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectCanonicalAuthHost;
use App\Support\FinanceReceiptUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('calendar:cleanup-drafts')->dailyAt('00:00');
        $schedule->command('orders:release-expired-holds')->everyMinute()->withoutOverlapping();
        $schedule->command('push:send-queued')->everyMinute();
        $schedule->command('reminders:send')->everyFiveMinutes()->withoutOverlapping();
        // Backup for weekly invoices, which are normally sent when the previous lesson is signed off
        $schedule->command('lessons:send-invoices')->hourly()->withoutOverlapping();
        $schedule->command('lessons:cancel-overdue-invoices')->hourly()->withoutOverlapping();
        // Banked lessons not rebooked in time: cancelled, paid ones sent to Head Office to refund
        $schedule->command('lessons:expire-banked')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('hmrc:monitor-token-expiry')->dailyAt('07:00');
        $schedule->command('hmrc:sync-itsa-obligations')->dailyAt('07:15');
        $schedule->command('hmrc:check-refresh-health')->hourly();
        $schedule->command('hmrc:prune-year-end-archives')->dailyAt('02:00');
        $schedule->command('account:process-deletion-requests')->dailyAt('01:00');
        // $schedule->command('bird:sync')->dailyAt('02:30');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhook/stripe',
        ]);

        // Runs on the web stack, after trusted-proxy host resolution.
        // Legacy hosts (app.just-drive.co.uk) send /login and /dashboard to
        // the canonical app URL. Booking, webhooks, and the API stay put.
        $middleware->web(prepend: [
            RedirectCanonicalAuthHost::class,
        ], append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mobile clients uploading receipts as multipart don't always send
        // `Accept: application/json`; without this they'd get a redirect/HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->expectsJson()
                || $request->is('api/v1/instructor/finances', 'api/v1/instructor/finances/*')
        );

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('api/v1/instructor/finances', 'api/v1/instructor/finances/*')) {
                return null;
            }

            $maxMb = intdiv(FinanceReceiptUploads::maxSizeKb(), 1024);

            return response()->json([
                'message' => "The upload is too large. Receipts must be under {$maxMb} MB.",
                'errors' => ['receipt' => ["The receipt is too large. The maximum size is {$maxMb} MB."]],
            ], 413);
        });
    })->create();

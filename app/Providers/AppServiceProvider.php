<?php

namespace App\Providers;

use App\Models\Enquiry;
use App\Observers\EnquiryObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerMandrillTransport();
        Enquiry::observe(EnquiryObserver::class);
    }

    /**
     * Register the Mandrill mailer transport so it can be selected via MAIL_MAILER=mandrill.
     */
    protected function registerMandrillTransport(): void
    {
        Mail::extend('mandrill', fn (array $config): MandrillApiTransport => new MandrillApiTransport(
            (string) ($config['key'] ?? '')
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}

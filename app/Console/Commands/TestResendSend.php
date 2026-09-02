<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ResendTestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send a test email through Laravel's Resend mailer so the API key and
 * verified from-domain can be checked after they are added to .env.
 */
class TestResendSend extends Command
{
    protected $signature = 'mail:test-resend
        {email : Recipient email address}
        {--subject= : Override the default test subject}';

    protected $description = 'Send a test transactional email via the Resend mailer';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $apiKey = (string) config('services.resend.key', '');

        if ($apiKey === '') {
            $this->error('RESEND_API_KEY is empty in config. Add it to .env and run `php artisan config:clear`.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Mailer: %s | From: %s <%s>',
            'resend',
            config('mail.from.name'),
            config('mail.from.address'),
        ));
        $this->info('API key tail: ...'.substr($apiKey, -6));
        $this->newLine();

        $mailable = new ResendTestMail(now()->toDateTimeString());

        if (is_string($this->option('subject')) && $this->option('subject') !== '') {
            $mailable->subject((string) $this->option('subject'));
        }

        $this->line("Sending test email to {$email} via the Resend mailer...");

        try {
            Mail::mailer('resend')->to($email)->send($mailable);
        } catch (Throwable $e) {
            $this->error('Send threw: '.$e->getMessage());
            Log::error('Resend test send threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }

        $this->info('Resend accepted the message. Check the recipient inbox and the Resend dashboard.');

        return self::SUCCESS;
    }
}

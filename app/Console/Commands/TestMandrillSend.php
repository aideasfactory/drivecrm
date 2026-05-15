<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MandrillTemplateService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Test Mandrill end-to-end and surface anything Mandrill silently rejects.
 *
 * The Symfony Mandrill transport returns HTTP 200 even when Mandrill rejects every
 * recipient (e.g. unsigned domain, suppression-list hit). To diagnose, this
 * command pings the API, lists verified sending domains, then sends via Mandrill's
 * direct API so we can see the per-recipient status array.
 */
class TestMandrillSend extends Command
{
    private const PING_URL = 'https://mandrillapp.com/api/1.0/users/ping';

    private const INFO_URL = 'https://mandrillapp.com/api/1.0/users/info';

    private const DOMAINS_URL = 'https://mandrillapp.com/api/1.0/senders/domains';

    private const SEND_URL = 'https://mandrillapp.com/api/1.0/messages/send';

    protected $signature = 'mail:test-mandrill
        {email : Recipient email address}
        {--template= : Mandrill-hosted template slug (omit to send a raw test email instead)}
        {--subject=Drive CRM — Mandrill test : Subject for raw sends}
        {--via-mailer : Send via Laravel Mail facade instead of the direct Mandrill API (less diagnostic info on failure)}';

    protected $description = 'Send a test email via Mandrill with full diagnostics (ping, domain check, raw response)';

    public function handle(MandrillTemplateService $mandrill): int
    {
        $email = (string) $this->argument('email');
        $template = $this->option('template');

        $apiKey = (string) config('services.mandrill.key', '');

        if ($apiKey === '') {
            $this->error('MANDRILL_API_KEY is empty in config. Check .env and run `php artisan config:clear`.');

            return 1;
        }

        $this->info(sprintf(
            'Mailer: %s | From: %s <%s>',
            config('mail.default'),
            config('mail.from.name'),
            config('mail.from.address'),
        ));
        $this->info('API key tail: ...'.substr($apiKey, -6));
        $this->newLine();

        if (! $this->preflight($apiKey)) {
            return 1;
        }

        if ($template) {
            return $this->sendTemplate($mandrill, (string) $template, $email);
        }

        if ($this->option('via-mailer')) {
            return $this->sendViaMailFacade($email);
        }

        return $this->sendViaDirectApi($apiKey, $email);
    }

    private function preflight(string $apiKey): bool
    {
        // 1. Ping
        $this->line('[1/3] Pinging Mandrill...');
        $ping = Http::acceptJson()->post(self::PING_URL, ['key' => $apiKey]);
        if ($ping->failed()) {
            $this->error('  Ping failed. HTTP '.$ping->status().' — body: '.$ping->body());

            return false;
        }
        $this->info('  OK: '.trim($ping->body(), '"'));

        // 2. Account info (shows subaccount/reputation hints)
        $this->line('[2/3] Fetching account info...');
        $info = Http::acceptJson()->post(self::INFO_URL, ['key' => $apiKey]);
        if ($info->ok()) {
            $payload = $info->json();
            $this->info(sprintf(
                '  Username: %s | Reputation: %s | Hourly quota: %s',
                $payload['username'] ?? '?',
                (string) ($payload['reputation'] ?? '?'),
                (string) ($payload['hourly_quota'] ?? '?'),
            ));
        } else {
            $this->warn('  Info call failed — continuing anyway.');
        }

        // 3. Verify from-address domain is signed
        $this->line('[3/3] Checking sender domains...');
        $domainsRes = Http::acceptJson()->post(self::DOMAINS_URL, ['key' => $apiKey]);
        if ($domainsRes->failed()) {
            $this->warn('  Could not fetch sender domains — continuing.');

            return true;
        }

        $fromAddress = (string) config('mail.from.address');
        $fromDomain = strtolower((string) substr((string) strrchr($fromAddress, '@'), 1));
        $this->line('  Checking from-domain: '.$fromDomain);

        $matched = null;
        foreach ((array) $domainsRes->json() as $domain) {
            if (strtolower((string) ($domain['domain'] ?? '')) === $fromDomain) {
                $matched = $domain;
                break;
            }
        }

        if (! $matched) {
            $this->error("  Sending domain '{$fromDomain}' is NOT in this Mandrill account's domain list.");
            $this->line('  Domains found: '.implode(', ', array_column((array) $domainsRes->json(), 'domain')));
            $this->warn('  Mandrill will reject sends from this domain. Add + verify it in the Mandrill dashboard.');

            return false;
        }

        $this->info(sprintf(
            "  Domain '%s' present. SPF: %s | DKIM: %s | Verified: %s | Valid signing: %s",
            $fromDomain,
            (string) ($matched['spf']['valid'] ?? 'unknown'),
            (string) ($matched['dkim']['valid'] ?? 'unknown'),
            $this->boolDisplay($matched['verified_at'] ?? null),
            $this->boolDisplay($matched['valid_signing'] ?? null),
        ));

        $this->newLine();

        return true;
    }

    private function sendViaDirectApi(string $apiKey, string $email): int
    {
        $subject = (string) $this->option('subject');
        $body = '<p>This is a Mandrill direct-API test from Drive CRM.</p>'
            .'<p>Sent at '.now()->toDateTimeString().'.</p>';

        $payload = [
            'key' => $apiKey,
            'message' => [
                'html' => $body,
                'text' => strip_tags($body),
                'subject' => $subject,
                'from_email' => (string) config('mail.from.address'),
                'from_name' => (string) config('mail.from.name'),
                'to' => [['email' => $email, 'type' => 'to']],
                'track_opens' => true,
                'track_clicks' => true,
            ],
        ];

        $this->line("Sending raw HTML email to {$email} via /messages/send (direct API)...");

        $response = Http::acceptJson()->post(self::SEND_URL, $payload);

        return $this->reportSendResponse($response);
    }

    private function sendViaMailFacade(string $email): int
    {
        $subject = (string) $this->option('subject');
        $body = '<p>This is a Mandrill transport test from Drive CRM.</p>'
            .'<p>Sent at '.now()->toDateTimeString().'.</p>';

        $this->line("Sending raw HTML email to {$email} via Mail::html() ...");

        try {
            $sent = Mail::html($body, function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        } catch (Throwable $e) {
            $this->error('Send threw: '.$e->getMessage());
            Log::error('Mandrill test send threw', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return 1;
        }

        $this->warn('Mail facade returned without throwing — but this does NOT prove Mandrill accepted the message.');
        $this->warn('Use the default direct-API path (omit --via-mailer) to see the per-recipient status.');

        if ($sent !== null && method_exists($sent, 'getDebug')) {
            $debug = (string) $sent->getDebug();
            if ($debug !== '') {
                $this->line('Symfony Mailer debug:');
                $this->line($debug);
            }
        }

        return 0;
    }

    private function sendTemplate(MandrillTemplateService $mandrill, string $template, string $email): int
    {
        $this->line("Sending Mandrill template '{$template}' to {$email}...");

        try {
            $result = $mandrill->send(
                template: $template,
                toEmail: $email,
                mergeVars: [
                    'EMAIL' => $email,
                    'URL' => 'https://example.com/test-link',
                    'USERID' => 'test-user',
                ],
            );
        } catch (Throwable $e) {
            $this->error('Template send failed: '.$e->getMessage());

            return 1;
        }

        $this->info('Mandrill response:');
        $this->line((string) json_encode($result, JSON_PRETTY_PRINT));

        return 0;
    }

    private function reportSendResponse(Response $response): int
    {
        if ($response->failed()) {
            $this->error('HTTP '.$response->status().' — '.$response->body());
            Log::warning('Mandrill direct-API send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 1;
        }

        $entries = (array) $response->json();
        $this->info('Mandrill returned '.count($entries).' recipient entr'.(count($entries) === 1 ? 'y' : 'ies').':');
        $this->line((string) json_encode($entries, JSON_PRETTY_PRINT));

        $allOk = true;
        foreach ($entries as $entry) {
            $status = (string) ($entry['status'] ?? 'unknown');
            $reason = (string) ($entry['reject_reason'] ?? '');
            $line = sprintf('  %s -> %s%s', (string) ($entry['email'] ?? '?'), $status, $reason !== '' ? " ({$reason})" : '');
            if (in_array($status, ['sent', 'queued', 'scheduled'], true)) {
                $this->info($line);
            } else {
                $this->error($line);
                $allOk = false;
            }
        }

        if (! $allOk) {
            $this->warn('At least one recipient was rejected by Mandrill — see status/reason above.');

            return 1;
        }

        $this->info('All recipients accepted by Mandrill. Check Outbound activity in the dashboard.');

        return 0;
    }

    private function boolDisplay(mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return 'no';
        }

        return is_string($value) ? $value : 'yes';
    }
}

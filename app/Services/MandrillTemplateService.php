<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends Mandrill-hosted templates via the Mandrill REST API.
 *
 * Use this only when the template is authored in Mandrill (e.g. the
 * smartdriving `magiclink` template). For Blade-rendered Mailables the
 * Laravel Mail facade is the right tool — Mandrill is just the transport.
 */
class MandrillTemplateService extends BaseService
{
    private const ENDPOINT = 'https://mandrillapp.com/api/1.0/messages/send-template';

    /**
     * Send a Mandrill template to a single recipient.
     *
     * @param  string  $template  Mandrill template slug (e.g. "magiclink")
     * @param  string  $toEmail  Recipient email address
     * @param  array<string, scalar|null>  $mergeVars  Template merge variables (key => value)
     * @param  string|null  $toName  Optional recipient display name
     * @param  string|null  $subject  Optional subject override
     * @return array<string, mixed> Parsed Mandrill response (first recipient entry)
     */
    public function send(
        string $template,
        string $toEmail,
        array $mergeVars = [],
        ?string $toName = null,
        ?string $subject = null,
    ): array {
        $apiKey = (string) config('services.mandrill.key', '');

        if ($apiKey === '') {
            throw new RuntimeException('MANDRILL_API_KEY is not configured.');
        }

        $payload = [
            'key' => $apiKey,
            'template_name' => $template,
            'template_content' => [],
            'message' => [
                'to' => [[
                    'email' => $toEmail,
                    'name' => $toName,
                    'type' => 'to',
                ]],
                'from_email' => (string) config('mail.from.address'),
                'from_name' => (string) config('mail.from.name'),
                'subject' => $subject,
                'global_merge_vars' => $this->formatMergeVars($mergeVars),
            ],
        ];

        $response = Http::acceptJson()->post(self::ENDPOINT, $payload);

        $this->assertSuccessful($response, $template, $toEmail);

        return $response->json()[0] ?? [];
    }

    /**
     * Convert an associative merge-vars array into Mandrill's `[{name, content}]` format.
     *
     * @param  array<string, scalar|null>  $vars
     * @return array<int, array{name: string, content: scalar|null}>
     */
    private function formatMergeVars(array $vars): array
    {
        $formatted = [];

        foreach ($vars as $name => $content) {
            $formatted[] = ['name' => $name, 'content' => $content];
        }

        return $formatted;
    }

    private function assertSuccessful(Response $response, string $template, string $toEmail): void
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Mandrill template send failed for %s -> %s: HTTP %d %s',
                $template,
                $toEmail,
                $response->status(),
                (string) $response->body(),
            ));
        }

        $first = $response->json()[0] ?? null;
        $status = is_array($first) ? ($first['status'] ?? null) : null;

        if (in_array($status, ['rejected', 'invalid'], true)) {
            $reason = is_array($first) ? ($first['reject_reason'] ?? 'unknown') : 'unknown';

            throw new RuntimeException(sprintf(
                'Mandrill rejected %s -> %s: %s (%s)',
                $template,
                $toEmail,
                $status,
                $reason,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enquiry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Upserts a Bird CRM contact from a booking enquiry via the Bird Contacts API.
 *
 * Uses the "Create or Update Contact by Identifier" endpoint (PATCH), so the
 * same enquiry email submitted twice updates the existing contact rather than
 * 409-ing.
 *
 * Docs: https://docs.bird.com/api/contacts-api/api-reference/manage-workspace-contacts/create-or-update-a-contact-by-identifier
 */
class BirdContactService extends BaseService
{
    private const BASE_URL = 'https://api.bird.com';

    /**
     * Upsert a Bird contact from the data captured during a booking enquiry.
     *
     * @return array<string, mixed> Parsed Bird response body
     */
    public function createFromEnquiry(Enquiry $enquiry): array
    {
        if (! config('services.bird.enabled')) {
            Log::info('Bird sync skipped (BIRD_ENABLED=false)', [
                'enquiry_id' => $enquiry->id,
            ]);

            return [];
        }

        $apiKey = (string) config('services.bird.api_key', '');
        $workspaceId = (string) config('services.bird.workspace_id', '');

        if ($apiKey === '' || $workspaceId === '') {
            throw new RuntimeException('Bird API credentials are not configured (BIRD_API_KEY, BIRD_WORKSPACE_ID).');
        }

        $step1 = $enquiry->getStepData(1) ?? [];
        $step2 = $enquiry->getStepData(2) ?? [];

        $email = $this->stringOrNull($step1['email'] ?? null);

        if ($email === null) {
            throw new RuntimeException(sprintf(
                'Cannot sync enquiry %s to Bird: email is missing from step 1.',
                $enquiry->id,
            ));
        }

        $payload = $this->buildPayload($step1, $step2);

        $url = sprintf(
            '%s/workspaces/%s/contacts/identifiers/emailaddress/%s',
            self::BASE_URL,
            $workspaceId,
            strtr(rawurlencode($email), ['%40' => '@']),
        );

        Log::info('Bird upsert request', [
            'enquiry_id' => $enquiry->id,
            'url' => $url,
            'payload' => $payload,
        ]);

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'AccessKey '.$apiKey])
            ->patch($url, $payload);

        Log::info('Bird upsert response', [
            'enquiry_id' => $enquiry->id,
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        $this->assertSuccessful($response, $enquiry->id);

        return $response->json() ?? [];
    }

    /**
     * Build the Bird upsert payload from an enquiry's step data.
     *
     * The contact is always upserted (operational follow-up is legitimate-
     * interest processing, not marketing consent). Subscription booleans and
     * marketing-list membership reflect the separate marketing_consent the
     * user ticked at step 1 — bundling that with terms acceptance is a GDPR
     * breach that the ICO actively fines for.
     *
     * @param  array<string, mixed>  $step1
     * @param  array<string, mixed>  $step2
     * @return array<string, mixed>
     */
    private function buildPayload(array $step1, array $step2): array
    {
        $firstName = $this->stringOrNull($step1['first_name'] ?? null);
        $lastName = $this->stringOrNull($step1['last_name'] ?? null);
        $phone = $this->normalisePhoneToE164($this->stringOrNull($step1['phone'] ?? null));
        $postalCode = $this->stringOrNull($step1['postcode'] ?? null);
        $availability = $this->resolveAvailability($step2);
        $marketingConsent = (bool) ($step1['marketing_consent'] ?? false);

        $addIdentifiers = [];

        if ($phone !== null) {
            $addIdentifiers[] = ['key' => 'phonenumber', 'value' => $phone];
        }

        $attributes = array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'postalCode' => $postalCode,
            'availability' => $availability,
            'subscribedEmail' => $marketingConsent,
            'subscribedSms' => $marketingConsent,
        ], fn ($value) => $value !== null);

        $listId = $this->stringOrNull(config('services.bird.booking_list_id'));

        return array_filter([
            'attributes' => $attributes !== [] ? $attributes : null,
            'addIdentifiers' => $addIdentifiers !== [] ? $addIdentifiers : null,
            'addToLists' => ($marketingConsent && $listId !== null) ? [$listId] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Map the step-2 in-area flag onto a human-readable Availability string.
     *
     * @param  array<string, mixed>  $step2
     */
    private function resolveAvailability(array $step2): ?string
    {
        if (! array_key_exists('in_area', $step2)) {
            return null;
        }

        return $step2['in_area'] ? 'In area' : 'Out of area';
    }

    /**
     * Normalise a UK-flavoured phone number into E.164 (e.g. "+447545399797").
     *
     * The booking form is UK-only, so bare numbers are assumed to be GB.
     * Returns null when the result isn't a plausible E.164 length (8–15 digits),
     * which causes the phone identifier to be skipped rather than failing the
     * whole contact create on Bird's strict validator.
     */
    private function normalisePhoneToE164(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = (string) preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            $normalised = '+'.$digits;
        } elseif (str_starts_with($digits, '00')) {
            $normalised = '+'.substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $normalised = '+44'.substr($digits, 1);
        } elseif (str_starts_with($digits, '44')) {
            $normalised = '+'.$digits;
        } else {
            $normalised = '+44'.$digits;
        }

        $length = strlen($normalised) - 1;

        if ($length < 8 || $length > 15) {
            return null;
        }

        return $normalised;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertSuccessful(Response $response, string $enquiryId): void
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Bird upsert-contact failed for enquiry %s: HTTP %d %s',
                $enquiryId,
                $response->status(),
                (string) $response->body(),
            ));
        }
    }
}

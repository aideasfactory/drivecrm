<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enquiry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Creates Contacts in the Bird CRM workspace via the Bird Contacts API.
 *
 * Docs: https://docs.bird.com/api/contacts-api/api-reference/manage-workspace-contacts/create-a-contact
 */
class BirdContactService extends BaseService
{
    private const BASE_URL = 'https://api.bird.com';

    /**
     * Create a Bird contact from the step-1 data on a booking enquiry.
     *
     * @return array<string, mixed> Parsed Bird response body
     */
    public function createFromEnquiry(Enquiry $enquiry): array
    {
        $apiKey = (string) config('services.bird.api_key', '');
        $workspaceId = (string) config('services.bird.workspace_id', '');

        if ($apiKey === '' || $workspaceId === '') {
            throw new RuntimeException('Bird API credentials are not configured (BIRD_API_KEY, BIRD_WORKSPACE_ID).');
        }

        $step1 = $enquiry->getStepData(1) ?? [];
        $step2 = $enquiry->getStepData(2) ?? [];

        $payload = $this->buildPayload($step1, $step2);

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'AccessKey '.$apiKey])
            ->post(self::BASE_URL.'/workspaces/'.$workspaceId.'/contacts', $payload);

        $this->assertSuccessful($response, $enquiry->id);

        return $response->json() ?? [];
    }

    /**
     * Build the Bird Create-Contact payload from an enquiry's step data.
     *
     * @param  array<string, mixed>  $step1
     * @param  array<string, mixed>  $step2
     * @return array<string, mixed>
     */
    private function buildPayload(array $step1, array $step2): array
    {
        $firstName = $this->stringOrNull($step1['first_name'] ?? null);
        $lastName = $this->stringOrNull($step1['last_name'] ?? null);
        $email = $this->stringOrNull($step1['email'] ?? null);
        $phone = $this->normalisePhoneToE164($this->stringOrNull($step1['phone'] ?? null));
        $postalCode = $this->stringOrNull($step1['postcode'] ?? null);
        $availability = $this->resolveAvailability($step2);

        $displayName = trim(($firstName ?? '').' '.($lastName ?? ''));

        $identifiers = [];

        if ($email !== null) {
            $identifiers[] = ['key' => 'emailaddress', 'value' => $email];
        }

        if ($phone !== null) {
            $identifiers[] = ['key' => 'phonenumber', 'value' => $phone];
        }

        $attributes = array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'postalCode' => $postalCode,
            'availability' => $availability,
        ], fn ($value) => $value !== null);

        return array_filter([
            'displayName' => $displayName !== '' ? $displayName : null,
            'identifiers' => $identifiers !== [] ? $identifiers : null,
            'attributes' => $attributes !== [] ? $attributes : null,
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
                'Bird create-contact failed for enquiry %s: HTTP %d %s',
                $enquiryId,
                $response->status(),
                (string) $response->body(),
            ));
        }
    }
}

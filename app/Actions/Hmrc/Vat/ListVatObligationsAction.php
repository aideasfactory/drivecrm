<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Vat;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcVatObligation;
use App\Models\User;
use Illuminate\Support\Collection;

class ListVatObligationsAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Fetch VAT obligations for an instructor and upsert into `hmrc_vat_obligations`.
     *
     * @param  array{from?: ?string, to?: ?string, status?: ?string}  $filters
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcVatObligation>
     */
    public function __invoke(User $user, array $filters = [], array $fraudContext = []): Collection
    {
        $vrn = $this->resolveVrn($user);

        $query = array_filter([
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'status' => $filters['status'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/organisations/vat/{$vrn}/obligations",
            version: '1.0',
            query: $query,
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        return $this->upsert($user, $vrn, $response);
    }

    private function resolveVrn(User $user): string
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->vrn) || $instructor->vrn === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a VAT registration number (VRN) before fetching VAT obligations.',
                statusCode: 400,
            );
        }

        return $instructor->vrn;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return Collection<int, HmrcVatObligation>
     */
    private function upsert(User $user, string $vrn, array $response): Collection
    {
        $now = now();
        $rows = collect();

        $obligations = is_array($response['obligations'] ?? null) ? $response['obligations'] : [];

        foreach ($obligations as $obligation) {
            if (! is_array($obligation)) {
                continue;
            }

            $row = HmrcVatObligation::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'vrn' => $vrn,
                    'period_key' => (string) ($obligation['periodKey'] ?? ''),
                ],
                [
                    'period_start_date' => $obligation['start'] ?? null,
                    'period_end_date' => $obligation['end'] ?? null,
                    'due_date' => $obligation['due'] ?? null,
                    'received_date' => $obligation['received'] ?? null,
                    'status' => $this->mapStatus((string) ($obligation['status'] ?? 'O')),
                    'last_synced_at' => $now,
                ],
            );

            $rows->push($row);
        }

        return $rows;
    }

    /**
     * HMRC's VAT API returns single-letter codes — map to the long form we share with ITSA.
     */
    private function mapStatus(string $code): string
    {
        return match ($code) {
            'O', 'Open' => 'Open',
            'F', 'Fulfilled' => 'Fulfilled',
            default => $code,
        };
    }
}

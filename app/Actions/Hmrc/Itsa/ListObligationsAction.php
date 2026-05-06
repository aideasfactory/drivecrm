<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaObligation;
use App\Models\User;
use Illuminate\Support\Collection;

class ListObligationsAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Fetch quarterly income-and-expenditure obligations for an instructor.
     * Upserts into `hmrc_itsa_obligations` so the rest of the app (UI, cron,
     * dashboard banner) reads from one cache.
     *
     * @param  array{from?: ?string, to?: ?string, status?: ?string, businessId?: ?string}  $filters
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcItsaObligation>
     */
    public function __invoke(User $user, array $filters = [], array $fraudContext = []): Collection
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->nino)) {
            throw new HmrcApiException(
                message: 'Tax profile must include a NINO before fetching HMRC obligations.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        $query = array_filter([
            'fromDate' => $filters['from'] ?? null,
            'toDate' => $filters['to'] ?? null,
            'status' => $filters['status'] ?? null,
            'businessId' => $filters['businessId'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/obligations/details/{$nino}/income-and-expenditure",
            version: '3.0',
            query: $query,
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        return $this->upsert($user, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return Collection<int, HmrcItsaObligation>
     */
    private function upsert(User $user, array $response): Collection
    {
        $now = now();
        $rows = collect();

        $businesses = is_array($response['obligations'] ?? null) ? $response['obligations'] : [];

        foreach ($businesses as $businessBlock) {
            if (! is_array($businessBlock)) {
                continue;
            }
            $businessId = (string) ($businessBlock['businessId'] ?? '');
            $obligations = is_array($businessBlock['obligationDetails'] ?? null)
                ? $businessBlock['obligationDetails']
                : (is_array($businessBlock['obligations'] ?? null) ? $businessBlock['obligations'] : []);

            foreach ($obligations as $obligation) {
                if (! is_array($obligation)) {
                    continue;
                }

                $start = $obligation['periodStartDate'] ?? null;
                $end = $obligation['periodEndDate'] ?? null;

                $periodKey = (string) ($obligation['periodKey'] ?? '');
                if ($periodKey === '' && is_string($start) && is_string($end)) {
                    $periodKey = "{$start}_{$end}";
                }

                $row = HmrcItsaObligation::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'business_id' => $businessId,
                        'period_key' => $periodKey,
                        'obligation_type' => (string) ($obligation['obligationType'] ?? 'Quarterly Update'),
                    ],
                    [
                        'period_start_date' => $start,
                        'period_end_date' => $end,
                        'due_date' => $obligation['dueDate'] ?? null,
                        'received_date' => $obligation['receivedDate'] ?? null,
                        'status' => ucfirst(strtolower((string) ($obligation['status'] ?? 'Open'))),
                        'last_synced_at' => $now,
                    ],
                );

                $rows->push($row);
            }
        }

        return $rows;
    }
}

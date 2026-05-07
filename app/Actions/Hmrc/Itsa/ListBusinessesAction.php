<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Actions\Hmrc\CallHmrcApiAction;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\HmrcItsaBusiness;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Collection;

class ListBusinessesAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcItsaBusiness>
     */
    public function __invoke(User $user, array $fraudContext = []): Collection
    {
        $instructor = $user->instructor;
        if ($instructor === null || ! is_string($instructor->utr) || $instructor->utr === '') {
            throw new HmrcApiException(
                message: 'Tax profile must include a UTR/NINO before listing HMRC businesses.',
                statusCode: 400,
            );
        }
        $nino = (string) $instructor->nino;

        $response = ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: "/individuals/business/details/{$nino}/list",
            version: '2.0',
            withFraudHeaders: true,
            fraudContext: $fraudContext,
        );

        $items = is_array($response['listOfBusinesses'] ?? null)
            ? $response['listOfBusinesses']
            : (is_array($response['businesses'] ?? null) ? $response['businesses'] : []);

        return $this->upsertAll($user, $instructor, $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, HmrcItsaBusiness>
     */
    private function upsertAll(User $user, Instructor $instructor, array $items): Collection
    {
        $now = now();
        $rows = collect();

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['businessId'])) {
                continue;
            }

            $row = HmrcItsaBusiness::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'business_id' => (string) $item['businessId'],
                ],
                [
                    'instructor_id' => $instructor->id,
                    'type_of_business' => (string) ($item['typeOfBusiness'] ?? 'self-employment'),
                    'trading_name' => isset($item['tradingName']) ? (string) $item['tradingName'] : null,
                    'accounting_type' => isset($item['accountingType']) ? (string) $item['accountingType'] : null,
                    'commencement_date' => $item['commencementDate'] ?? null,
                    'cessation_date' => $item['cessationDate'] ?? null,
                    'latency_details' => is_array($item['latencyDetails'] ?? null) ? $item['latencyDetails'] : null,
                    'last_synced_at' => $now,
                ],
            );

            $rows->push($row);
        }

        return $rows;
    }
}

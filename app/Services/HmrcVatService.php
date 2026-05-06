<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Hmrc\Vat\ListVatLiabilitiesAction;
use App\Actions\Hmrc\Vat\ListVatObligationsAction;
use App\Actions\Hmrc\Vat\ListVatPaymentsAction;
use App\Actions\Hmrc\Vat\RetrieveVatReturnAction;
use App\Actions\Hmrc\Vat\SubmitVatReturnAction;
use App\Enums\ItsaObligationStatus;
use App\Models\HmrcVatObligation;
use App\Models\HmrcVatReturn;
use App\Models\User;
use Illuminate\Support\Collection;

class HmrcVatService extends BaseService
{
    public function __construct(
        protected ListVatObligationsAction $listObligations,
        protected RetrieveVatReturnAction $retrieveReturn,
        protected SubmitVatReturnAction $submitReturn,
        protected ListVatLiabilitiesAction $listLiabilities,
        protected ListVatPaymentsAction $listPayments,
    ) {}

    /**
     * Refresh VAT obligations from HMRC and persist them locally.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcVatObligation>
     */
    public function syncObligations(User $user, array $fraudContext = []): Collection
    {
        $this->invalidateUserCache($user);

        return ($this->listObligations)($user, [], $fraudContext);
    }

    /**
     * @return Collection<int, HmrcVatObligation>
     */
    public function openObligations(User $user): Collection
    {
        return $this->remember(
            $this->cacheKey('user', $user->id, 'vat_open_obligations'),
            fn () => HmrcVatObligation::query()
                ->where('user_id', $user->id)
                ->where('status', ItsaObligationStatus::Open)
                ->orderBy('due_date')
                ->get(),
            ttl: 300,
        );
    }

    /**
     * @return Collection<int, HmrcVatReturn>
     */
    public function submissionHistory(User $user): Collection
    {
        return HmrcVatReturn::query()
            ->where('user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function submitReturn(User $user, string $periodKey, array $data, array $fraudContext = []): HmrcVatReturn
    {
        $row = ($this->submitReturn)($user, $periodKey, $data, $fraudContext);
        $this->invalidateUserCache($user);

        return $row;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function retrieveReturn(User $user, string $periodKey, array $fraudContext = []): array
    {
        return ($this->retrieveReturn)($user, $periodKey, $fraudContext);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function liabilities(User $user, string $from, string $to, array $fraudContext = []): array
    {
        return ($this->listLiabilities)($user, $from, $to, $fraudContext);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function payments(User $user, string $from, string $to, array $fraudContext = []): array
    {
        return ($this->listPayments)($user, $from, $to, $fraudContext);
    }

    private function invalidateUserCache(User $user): void
    {
        $this->invalidate([
            $this->cacheKey('user', $user->id, 'vat_open_obligations'),
        ]);
    }
}

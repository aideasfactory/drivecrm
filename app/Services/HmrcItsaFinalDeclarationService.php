<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Hmrc\Itsa\FinalDeclaration\PollCalculationAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\RetrieveAccountBalanceAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\RetrieveCalculationAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\RetrieveSupplementaryAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\SubmitFinalDeclarationAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\SubmitSupplementaryAction;
use App\Actions\Hmrc\Itsa\FinalDeclaration\TriggerCalculationAction;
use App\Enums\ItsaCalculationType;
use App\Enums\ItsaSupplementaryType;
use App\Models\HmrcItsaCalculation;
use App\Models\HmrcItsaFinalDeclaration;
use App\Models\HmrcItsaSupplementaryData;
use App\Models\User;
use Illuminate\Support\Collection;

class HmrcItsaFinalDeclarationService extends BaseService
{
    public function __construct(
        protected SubmitSupplementaryAction $submitSupplementary,
        protected RetrieveSupplementaryAction $retrieveSupplementary,
        protected TriggerCalculationAction $triggerCalculation,
        protected RetrieveCalculationAction $retrieveCalculation,
        protected PollCalculationAction $pollCalculation,
        protected SubmitFinalDeclarationAction $submitFinalDeclaration,
        protected RetrieveAccountBalanceAction $retrieveAccountBalance,
    ) {}

    /**
     * @return array<string, ?HmrcItsaSupplementaryData> Keyed by ItsaSupplementaryType value.
     */
    public function getSupplementary(User $user, string $taxYear): array
    {
        $rows = HmrcItsaSupplementaryData::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->get()
            ->keyBy(fn (HmrcItsaSupplementaryData $row) => $row->type instanceof ItsaSupplementaryType ? $row->type->value : (string) $row->type);

        $result = [];
        foreach (ItsaSupplementaryType::cases() as $type) {
            $result[$type->value] = $rows->get($type->value);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function saveSupplementary(
        User $user,
        string $taxYear,
        ItsaSupplementaryType $type,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaSupplementaryData {
        $row = ($this->submitSupplementary)($user, $type, $taxYear, $data, $fraudContext);
        $this->invalidateUserCache($user, $taxYear);

        return $row;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function triggerCalculation(
        User $user,
        string $taxYear,
        ItsaCalculationType $type,
        array $fraudContext = [],
    ): HmrcItsaCalculation {
        $row = ($this->triggerCalculation)($user, $taxYear, $type, $fraudContext);
        $this->invalidateUserCache($user, $taxYear);

        return $row;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function pollCalculation(
        User $user,
        HmrcItsaCalculation $calculation,
        array $fraudContext = [],
    ): HmrcItsaCalculation {
        return ($this->pollCalculation)($user, $calculation, $fraudContext);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function refreshCalculation(
        User $user,
        HmrcItsaCalculation $calculation,
        array $fraudContext = [],
    ): HmrcItsaCalculation {
        return ($this->retrieveCalculation)($user, $calculation, $fraudContext);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function submitFinalDeclaration(
        User $user,
        HmrcItsaCalculation $calculation,
        array $fraudContext = [],
    ): HmrcItsaFinalDeclaration {
        $row = ($this->submitFinalDeclaration)($user, $calculation, $fraudContext);
        $this->invalidateUserCache($user, $calculation->tax_year);

        return $row;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function accountBalance(User $user, array $fraudContext = []): array
    {
        return $this->remember(
            $this->cacheKey('user', $user->id, 'sa_account_balance'),
            fn () => ($this->retrieveAccountBalance)($user, $fraudContext),
            ttl: 300,
        );
    }

    public function findFinalDeclaration(User $user, string $taxYear): ?HmrcItsaFinalDeclaration
    {
        return HmrcItsaFinalDeclaration::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->first();
    }

    /**
     * @return Collection<int, HmrcItsaCalculation>
     */
    public function calculationsFor(User $user, string $taxYear): Collection
    {
        return HmrcItsaCalculation::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->orderByDesc('triggered_at')
            ->get();
    }

    private function invalidateUserCache(User $user, string $taxYear): void
    {
        $this->invalidate([
            $this->cacheKey('user', $user->id, "fd_supplementary_{$taxYear}"),
            $this->cacheKey('user', $user->id, 'sa_account_balance'),
        ]);
    }
}

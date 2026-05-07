<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Hmrc\Itsa\AmendQuarterlyUpdateAction;
use App\Actions\Hmrc\Itsa\ListBusinessesAction;
use App\Actions\Hmrc\Itsa\ListObligationsAction;
use App\Actions\Hmrc\Itsa\ResolveEnrolmentStatusAction;
use App\Actions\Hmrc\Itsa\RetrieveBusinessAction;
use App\Actions\Hmrc\Itsa\RetrieveQuarterlyUpdateAction;
use App\Actions\Hmrc\Itsa\SubmitQuarterlyUpdateAction;
use App\Enums\ItsaEnrolmentStatus;
use App\Enums\ItsaObligationStatus;
use App\Models\HmrcItsaBusiness;
use App\Models\HmrcItsaObligation;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\User;
use Illuminate\Support\Collection;

class HmrcItsaService extends BaseService
{
    public function __construct(
        protected ListBusinessesAction $listBusinesses,
        protected RetrieveBusinessAction $retrieveBusiness,
        protected ListObligationsAction $listObligations,
        protected SubmitQuarterlyUpdateAction $submitQuarterly,
        protected AmendQuarterlyUpdateAction $amendQuarterly,
        protected RetrieveQuarterlyUpdateAction $retrieveQuarterly,
        protected ResolveEnrolmentStatusAction $resolveEnrolmentStatus,
    ) {}

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcItsaBusiness>
     */
    public function syncBusinesses(User $user, array $fraudContext = []): Collection
    {
        $this->invalidateUserCache($user);

        return ($this->listBusinesses)($user, $fraudContext);
    }

    /**
     * @return Collection<int, HmrcItsaBusiness>
     */
    public function cachedBusinesses(User $user): Collection
    {
        return $this->remember(
            $this->cacheKey('user', $user->id, 'itsa_businesses'),
            fn () => HmrcItsaBusiness::query()->where('user_id', $user->id)->get(),
            ttl: 300,
        );
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return Collection<int, HmrcItsaObligation>
     */
    public function syncObligations(User $user, ?string $businessId = null, array $fraudContext = []): Collection
    {
        $this->invalidateUserCache($user);

        return ($this->listObligations)(
            $user,
            $businessId !== null ? ['businessId' => $businessId] : [],
            $fraudContext,
        );
    }

    /**
     * @return Collection<int, HmrcItsaObligation>
     */
    public function openObligations(User $user): Collection
    {
        return $this->remember(
            $this->cacheKey('user', $user->id, 'itsa_open_obligations'),
            fn () => HmrcItsaObligation::query()
                ->where('user_id', $user->id)
                ->where('status', ItsaObligationStatus::Open)
                ->whereIn('business_id', function ($q) use ($user) {
                    $q->select('business_id')
                        ->from('hmrc_itsa_businesses')
                        ->where('user_id', $user->id)
                        ->where('type_of_business', 'self-employment');
                })
                ->orderBy('due_date')
                ->get(),
            ttl: 300,
        );
    }

    /**
     * @return Collection<int, HmrcItsaQuarterlyUpdate>
     */
    public function submissionHistory(User $user): Collection
    {
        return HmrcItsaQuarterlyUpdate::query()
            ->where('user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function refreshEnrolmentStatus(User $user, array $fraudContext = []): ItsaEnrolmentStatus
    {
        $this->invalidateUserCache($user);

        return ($this->resolveEnrolmentStatus)($user, $fraudContext);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function submitQuarterly(
        User $user,
        string $businessId,
        string $periodKey,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaQuarterlyUpdate {
        $row = ($this->submitQuarterly)($user, $businessId, $periodKey, $data, $fraudContext);
        $this->invalidateUserCache($user);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function amendQuarterly(
        User $user,
        HmrcItsaQuarterlyUpdate $row,
        array $data,
        array $fraudContext = [],
    ): HmrcItsaQuarterlyUpdate {
        $row = ($this->amendQuarterly)($user, $row, $data, $fraudContext);
        $this->invalidateUserCache($user);

        return $row;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     * @return array<string, mixed>
     */
    public function retrieveQuarterly(User $user, HmrcItsaQuarterlyUpdate $row, array $fraudContext = []): array
    {
        return ($this->retrieveQuarterly)($user, $row, $fraudContext);
    }

    private function invalidateUserCache(User $user): void
    {
        $this->invalidate([
            $this->cacheKey('user', $user->id, 'itsa_businesses'),
            $this->cacheKey('user', $user->id, 'itsa_open_obligations'),
        ]);
    }
}

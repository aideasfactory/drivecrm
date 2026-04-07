<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Dashboard\GetDashboardMetricsAction;

class DashboardService extends BaseService
{
    /**
     * Dashboard metrics are cached for 5 minutes — they are aggregate-heavy
     * but should still feel reasonably fresh.
     */
    protected int $cacheTtl = 300;

    public function __construct(
        protected GetDashboardMetricsAction $getDashboardMetrics
    ) {}

    public function getMetrics(): array
    {
        return $this->remember(
            $this->cacheKey('dashboard', 'global', 'metrics'),
            fn () => ($this->getDashboardMetrics)()
        );
    }
}

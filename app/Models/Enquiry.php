<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasUuids;

    protected $fillable = [
        'data',
        'current_step',
        'max_step_reached',
        'privacy_consent',
        'marketing_consent',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'current_step' => 'integer',
            'max_step_reached' => 'integer',
            'privacy_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    /**
     * Get step data by step number
     */
    public function getStepData(int $step): ?array
    {
        return $this->data['steps']["step{$step}"] ?? null;
    }

    /**
     * Update step data
     */
    public function setStepData(int $step, array $data): void
    {
        $currentData = $this->data ?? ['steps' => []];
        $currentData['steps']["step{$step}"] = $data;
        $currentData['current_step'] = max($step, $currentData['current_step'] ?? 1);
        $this->data = $currentData;
    }

    /**
     * Get discount data stored in the enquiry (if any).
     *
     * @return array{id: string, label: string, percentage: int}|null
     */
    public function getDiscountData(): ?array
    {
        return $this->data['discount'] ?? null;
    }

    /**
     * Get marketing tracking data captured at entry (currently only the
     * Google Ads gclid + source label).
     *
     * @return array{gclid?: string, source?: string}|null
     */
    public function getTracking(): ?array
    {
        return $this->data['tracking'] ?? null;
    }
}

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
    ];

    protected $casts = [
        'data' => 'array',
        'current_step' => 'integer',
        'max_step_reached' => 'integer',
    ];

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
}

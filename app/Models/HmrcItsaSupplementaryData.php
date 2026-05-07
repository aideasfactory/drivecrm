<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItsaSupplementaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcItsaSupplementaryData extends Model
{
    protected $table = 'hmrc_itsa_supplementary_data';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ItsaSupplementaryType::class,
            'payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

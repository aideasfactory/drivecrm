<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressSubcategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'progress_category_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgressCategory::class, 'progress_category_id');
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlotOfferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_item_id',
        'instructor_id',
        'package_id',
        'student_id',
        'message',
        'status',
        'booked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SlotOfferStatus::class,
            'booked_at' => 'datetime',
        ];
    }

    public function calendarItem(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isOpen(): bool
    {
        return $this->status === SlotOfferStatus::Open;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', SlotOfferStatus::Open);
    }
}

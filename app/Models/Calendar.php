<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    protected $table = 'calendars';

    protected $fillable = [
        'instructor_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function items()
    {
        return $this->hasMany(CalendarItem::class, 'calendar_id');
    }

    public function availableItems()
    {
        return $this->items()->where('is_available', true);
    }
}

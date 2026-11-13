<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentTestResult;
use App\Enums\StudentTestType;
use Database\Factories\StudentTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pupil's theory or practical test and its result (centre, fault counts).
 * Filled by the data import; a practical test links to its diary slot.
 */
class StudentTest extends Model
{
    /** @use HasFactory<StudentTestFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'instructor_id',
        'calendar_item_id',
        'type',
        'date',
        'time',
        'test_centre',
        'result',
        'driving_faults',
        'serious_faults',
        'dangerous_faults',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => StudentTestType::class,
            'result' => StudentTestResult::class,
            'date' => 'date',
            'driving_faults' => 'integer',
            'serious_faults' => 'integer',
            'dangerous_faults' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function calendarItem(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class);
    }
}

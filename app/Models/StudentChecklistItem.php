<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StudentChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentChecklistItem extends Model
{
    /** @use HasFactory<StudentChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'key',
        'label',
        'category',
        'is_checked',
        'date',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Default checklist items to seed for new students.
     *
     * @return array<int, array{key: string, label: string, category: string, sort_order: int}>
     */
    public static function defaultItems(): array
    {
        return [
            ['key' => 'book_theory_test', 'label' => 'Book theory test', 'category' => 'Theory Test', 'sort_order' => 1],
            ['key' => 'sit_theory_test', 'label' => 'Sit theory test', 'category' => 'Theory Test', 'sort_order' => 2],
            ['key' => 'schedule_mock_test', 'label' => 'Schedule mock test', 'category' => 'Practical Test', 'sort_order' => 3],
            ['key' => 'sit_mock_test', 'label' => 'Sit mock test', 'category' => 'Practical Test', 'sort_order' => 4],
            ['key' => 'book_practical_test', 'label' => 'Book practical test', 'category' => 'Practical Test', 'sort_order' => 5],
            ['key' => 'sit_practical_test', 'label' => 'Sit practical test', 'category' => 'Practical Test', 'sort_order' => 6],
            ['key' => 'agreed_terms', 'label' => 'Agreed terms', 'category' => 'General', 'sort_order' => 7],
            ['key' => 'driving_licence_number', 'label' => 'Driving licence number', 'category' => 'General', 'sort_order' => 8],
            ['key' => 'eyesight_checked', 'label' => 'Eyesight checked', 'category' => 'General', 'sort_order' => 9],
        ];
    }

    /**
     * Get the student that owns this checklist item.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

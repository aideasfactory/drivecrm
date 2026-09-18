<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReflectiveLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'what_i_learned',
        'what_went_well',
        'what_to_improve',
        'additional_notes',
    ];

    /**
     * Get the lesson this reflective log belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Whether the three required reflection prompts are filled.
     */
    public function isComplete(): bool
    {
        return self::fieldsAreComplete([
            'what_i_learned' => $this->what_i_learned,
            'what_went_well' => $this->what_went_well,
            'what_to_improve' => $this->what_to_improve,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function fieldsAreComplete(array $fields): bool
    {
        return filled($fields['what_i_learned'] ?? null)
            && filled($fields['what_went_well'] ?? null)
            && filled($fields['what_to_improve'] ?? null);
    }

    /**
     * Normalise a request payload into the four reflective-log columns.
     *
     * Accepts a nested `reflective_log` object, flattened snake_case, or
     * camelCase aliases so the mobile client can send either shape.
     *
     * @param  array<string, mixed>  $input
     * @return array{what_i_learned: ?string, what_went_well: ?string, what_to_improve: ?string, additional_notes: ?string}|null
     */
    public static function normalizePayload(array $input): ?array
    {
        $source = [];

        if (isset($input['reflective_log']) && is_array($input['reflective_log'])) {
            $source = $input['reflective_log'];
        } elseif (self::hasAnyPromptField($input)) {
            $source = $input;
        } else {
            return null;
        }

        $normalized = [
            'what_i_learned' => self::stringOrNull($source['what_i_learned'] ?? $source['whatILearned'] ?? null),
            'what_went_well' => self::stringOrNull($source['what_went_well'] ?? $source['whatWentWell'] ?? null),
            'what_to_improve' => self::stringOrNull($source['what_to_improve'] ?? $source['whatToImprove'] ?? null),
            'additional_notes' => self::stringOrNull($source['additional_notes'] ?? $source['additionalNotes'] ?? null),
        ];

        if (
            $normalized['what_i_learned'] === null
            && $normalized['what_went_well'] === null
            && $normalized['what_to_improve'] === null
            && $normalized['additional_notes'] === null
        ) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function hasAnyPromptField(array $input): bool
    {
        foreach (['what_i_learned', 'whatILearned', 'what_went_well', 'whatWentWell', 'what_to_improve', 'whatToImprove', 'additional_notes', 'additionalNotes'] as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }

        return false;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

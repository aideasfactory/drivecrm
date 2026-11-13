<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The permanent record of what the legacy importer brought in. Each row links
 * a legacy system's reference (e.g. its student ID) to the row created for it,
 * and the import run that did it. Re-running a bundle skips mapped refs
 * instead of duplicating them. "Was this row imported?" = a mapping exists
 * for its entity + model_id with action `created`.
 */
class ImportMapping extends Model
{
    public const ENTITY_INSTRUCTOR = 'instructor'; // → instructors.id

    public const ENTITY_LOCATION = 'location'; // → locations.id (ref: "{instructor_ref}:{sector}")

    public const ENTITY_STUDENT = 'student'; // → students.id

    public const ENTITY_DIARY = 'diary'; // → calendar_items.id

    public const ENTITY_LESSON = 'lesson'; // → lessons.id (ref: the diary_ref)

    public const ENTITY_FINANCE = 'finance'; // → instructor_finances.id

    public const ENTITY_MILEAGE = 'mileage'; // → mileage_logs.id (ref: the mileage_ref; import:mileage)

    public const ENTITY_TEST = 'test'; // → student_tests.id (ref: the test_ref)

    public const ENTITY_PROGRESS = 'progress'; // → student_progress.id (ref: "{student_ref}:{category}:{skill}")

    public const ENTITY_REFLECTIVE_LOG = 'reflective_log'; // → reflective_logs.id (ref: the log_ref)

    public const ENTITY_PICKUP_POINT = 'pickup_point'; // → student_pickup_points.id (ref: "{student_ref}:{position}")

    /** Row was created by the import. */
    public const ACTION_CREATED = 'created';

    /** Row already existed (matched by email) and was only linked to the ref. */
    public const ACTION_LINKED = 'linked';

    protected $fillable = [
        'import_run_id',
        'entity',
        'source_ref',
        'model_id',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'model_id' => 'integer',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    /**
     * Resolve the model ID mapped to a source ref, or null when not yet imported.
     */
    public static function modelIdFor(string $entity, string $sourceRef): ?int
    {
        return static::query()
            ->where('entity', $entity)
            ->where('source_ref', $sourceRef)
            ->value('model_id');
    }

    /**
     * Resolve many source refs at once (one query per 1,000 refs) — for the
     * import jobs, which would otherwise look refs up one row at a time.
     *
     * @param  array<int, string>  $sourceRefs
     * @return array<string, int> source ref => model ID, for refs already imported
     */
    public static function modelIdsFor(string $entity, array $sourceRefs): array
    {
        $ids = [];

        foreach (array_chunk(array_values(array_unique($sourceRefs)), 1000) as $chunk) {
            $ids += static::query()
                ->where('entity', $entity)
                ->whereIn('source_ref', $chunk)
                ->pluck('model_id', 'source_ref')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $ids;
    }

    /**
     * Whether a row was created by the importer.
     */
    public static function wasImported(string $entity, int $modelId): bool
    {
        return static::query()
            ->where('entity', $entity)
            ->where('model_id', $modelId)
            ->where('action', self::ACTION_CREATED)
            ->exists();
    }

    /**
     * Record the model created (or linked) for a source ref.
     */
    public static function record(
        ImportRun $run,
        string $entity,
        string $sourceRef,
        int $modelId,
        string $action = self::ACTION_CREATED,
    ): void {
        static::query()->updateOrCreate(
            ['entity' => $entity, 'source_ref' => $sourceRef],
            ['model_id' => $modelId, 'import_run_id' => $run->id, 'action' => $action],
        );
    }
}

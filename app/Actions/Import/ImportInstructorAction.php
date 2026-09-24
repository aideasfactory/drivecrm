<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\FetchPostcodeCoordinatesAction;
use App\Actions\ProgressTracker\SeedInstructorProgressTrackerAction;
use App\Enums\UserRole;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportInstructorAction
{
    public function __construct(
        protected FetchPostcodeCoordinatesAction $fetchPostcodeCoordinates,
        protected SeedInstructorProgressTrackerAction $seedInstructorProgressTracker,
    ) {}

    /**
     * Create (or link) the instructor for an instructors.csv row.
     *
     * - Ref already imported → the mapped instructor, untouched.
     * - Email belongs to an existing instructor → linked, untouched.
     * - Otherwise a new user + instructor is created with no email sent:
     *   `imported_at` set, `welcome_email_pending = true`, random password.
     *   `import:send-welcome-emails` sends the password-setup email later.
     *
     * @param  array<string, string|int>  $row
     * @return array{instructor: Instructor, created: bool}
     */
    public function __invoke(array $row, ImportRun $run): array
    {
        $ref = (string) $row['instructor_ref'];

        $mappedId = ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $ref);
        if ($mappedId !== null) {
            return ['instructor' => Instructor::query()->findOrFail($mappedId), 'created' => false];
        }

        $email = strtolower((string) $row['email']);

        $existing = Instructor::query()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();

        if ($existing) {
            ImportMapping::record($run, ImportMapping::ENTITY_INSTRUCTOR, $ref, $existing->id, ImportMapping::ACTION_LINKED);

            return ['instructor' => $existing, 'created' => false];
        }

        $postcode = $this->nullable($row, 'postcode');
        $coordinates = $postcode ? ($this->fetchPostcodeCoordinates)($postcode) : null;

        $user = User::create([
            'name' => (string) $row['name'],
            'email' => $email,
            'password' => Hash::make(Str::random(48)),
            'password_change_required' => true,
            'welcome_email_pending' => true,
            'imported_at' => now(),
            'role' => UserRole::INSTRUCTOR,
        ]);

        $instructor = Instructor::create([
            'user_id' => $user->id,
            'phone' => $this->nullable($row, 'phone'),
            'bio' => $this->nullable($row, 'bio'),
            'address' => $this->nullable($row, 'address'),
            'postcode' => $postcode ? strtoupper($postcode) : null,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'status' => $this->nullable($row, 'status') ?? 'active',
            'pdi_status' => $this->nullable($row, 'pdi_status'),
            'transmission_type' => (string) $row['transmission_type'],
            'priority' => false,
            'rating' => 4,
            'onboarding_complete' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'meta' => [
                'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-'.rand(1, 5).'.jpg',
            ],
        ]);

        ($this->seedInstructorProgressTracker)($instructor);

        ImportMapping::record($run, ImportMapping::ENTITY_INSTRUCTOR, $ref, $instructor->id);

        return ['instructor' => $instructor, 'created' => true];
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function nullable(array $row, string $field): ?string
    {
        $value = trim((string) ($row[$field] ?? ''));

        return $value === '' ? null : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\FetchPostcodeCoordinatesAction;
use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkImportInstructorsAction
{
    public function __construct(
        protected FetchPostcodeCoordinatesAction $fetchPostcodeCoordinates
    ) {}

    /**
     * Parse and import instructors from CSV data.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows (associative arrays)
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function __invoke(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for header row + 0-index

            // Normalize keys to lowercase/trimmed
            $row = array_change_key_case(array_map('trim', $row), CASE_LOWER);

            // Validate the row
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'transmission_type' => ['required', Rule::in(['manual', 'automatic', 'both'])],
                'phone' => ['nullable', 'string', 'max:50'],
                'bio' => ['nullable', 'string'],
                'status' => ['nullable', 'string', 'max:50'],
                'pdi_status' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string'],
                'postcode' => ['nullable', 'string', 'max:10'],
            ], [
                'name.required' => 'Name is required.',
                'email.required' => 'Email is required.',
                'email.email' => 'Email must be a valid email address.',
                'email.unique' => 'This email is already in use.',
                'transmission_type.required' => 'Transmission type is required.',
                'transmission_type.in' => 'Transmission type must be "manual", "automatic", or "both".',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => null,
                        'message' => $message,
                    ];
                }
                $skipped++;

                continue;
            }

            $validated = $validator->validated();

            try {
                DB::beginTransaction();

                // Fetch coordinates if postcode provided
                $latitude = null;
                $longitude = null;

                if (! empty($validated['postcode'])) {
                    $coordinates = ($this->fetchPostcodeCoordinates)($validated['postcode']);

                    if ($coordinates && $coordinates['latitude'] && $coordinates['longitude']) {
                        $latitude = $coordinates['latitude'];
                        $longitude = $coordinates['longitude'];
                    } else {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'postcode',
                            'message' => "Could not resolve coordinates for postcode '{$validated['postcode']}'. Instructor created without coordinates.",
                        ];
                    }
                }

                // Create user account
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::INSTRUCTOR,
                ]);

                $avatarNumber = rand(1, 5);

                // Create instructor profile
                Instructor::create([
                    'user_id' => $user->id,
                    'bio' => $validated['bio'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'postcode' => $validated['postcode'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $validated['status'] ?? 'active',
                    'pdi_status' => $validated['pdi_status'] ?? null,
                    'priority' => false,
                    'rating' => 4,
                    'onboarding_complete' => false,
                    'charges_enabled' => false,
                    'payouts_enabled' => false,
                    'meta' => [
                        'transmission_type' => $validated['transmission_type'],
                        'phone' => $validated['phone'] ?? null,
                        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-'.$avatarNumber.'.jpg',
                    ],
                ]);

                DB::commit();
                $imported++;
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('CSV instructor import row failed', [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = [
                    'row' => $rowNumber,
                    'field' => null,
                    'message' => 'Failed to create instructor: '.$e->getMessage(),
                ];
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}

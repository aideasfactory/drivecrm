<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\UpdateInstructorProfilePictureRequest;
use App\Services\InstructorService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $instructorData = null;

        if ($user->isInstructor() && $user->instructor) {
            $instructorData = [
                'id' => $user->instructor->id,
                'avatar' => $user->instructor->avatar,
                'profile_picture_url' => $user->instructor->profile_picture_url,
                'has_profile_picture' => (bool) $user->instructor->profile_picture_path,
            ];
        }

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'instructor' => $instructorData,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Upload or replace the instructor's profile picture.
     */
    public function updateProfilePicture(UpdateInstructorProfilePictureRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;

        if (! $instructor) {
            abort(403);
        }

        $this->instructorService->updateProfilePicture(
            $instructor,
            $request->file('profile_picture')
        );

        return to_route('profile.edit');
    }

    /**
     * Delete the instructor's profile picture.
     */
    public function deleteProfilePicture(Request $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;

        if (! $instructor) {
            abort(403);
        }

        $this->instructorService->deleteProfilePicture($instructor);

        return to_route('profile.edit');
    }
}

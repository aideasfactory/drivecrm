<?php

declare(strict_types=1);

namespace App\Actions\Owner;

use App\Enums\OwnerAccess;
use App\Enums\UserRole;
use App\Mail\AdminWelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateOwnerAction
{
    /**
     * Create a new admin (owner) with a temporary password and email them
     * their login details. The account is pre-verified so the admin can sign
     * in straight away; `password_change_required` flags the temp password.
     */
    public function __invoke(string $name, string $email, string $temporaryPassword, OwnerAccess $access, User $createdBy): User
    {
        $owner = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $temporaryPassword,
            'role' => UserRole::OWNER,
            'owner_access' => $access,
            'password_change_required' => true,
        ]);

        $owner->forceFill(['email_verified_at' => now()])->save();

        Mail::to($owner->email)->queue(new AdminWelcomeMail($owner, $temporaryPassword));

        Log::info('Owner created', [
            'owner_user_id' => $owner->id,
            'owner_access' => $access->value,
            'created_by_user_id' => $createdBy->id,
        ]);

        return $owner;
    }
}

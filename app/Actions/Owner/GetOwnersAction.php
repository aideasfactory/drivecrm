<?php

declare(strict_types=1);

namespace App\Actions\Owner;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class GetOwnersAction
{
    /**
     * @return Collection<int, User>
     */
    public function __invoke(): Collection
    {
        return User::query()
            ->where('role', UserRole::OWNER)
            ->orderBy('name')
            ->get();
    }
}

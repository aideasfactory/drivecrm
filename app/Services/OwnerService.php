<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Owner\CreateOwnerAction;
use App\Actions\Owner\GetOwnersAction;
use App\Actions\Owner\UpdateOwnerAccessAction;
use App\Enums\OwnerAccess;
use App\Models\User;
use Illuminate\Support\Collection;

class OwnerService extends BaseService
{
    public function __construct(
        protected GetOwnersAction $getOwners,
        protected CreateOwnerAction $createOwner,
        protected UpdateOwnerAccessAction $updateOwnerAccess
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function getAll(): Collection
    {
        return ($this->getOwners)();
    }

    public function create(string $name, string $email, string $temporaryPassword, OwnerAccess $access, User $createdBy): User
    {
        return ($this->createOwner)($name, $email, $temporaryPassword, $access, $createdBy);
    }

    public function updateAccess(User $owner, OwnerAccess $access, User $changedBy): User
    {
        return ($this->updateOwnerAccess)($owner, $access, $changedBy);
    }
}

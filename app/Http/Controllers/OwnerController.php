<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OwnerAccess;
use App\Http\Requests\UpdateOwnerAccessRequest;
use App\Models\User;
use App\Services\OwnerService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function __construct(
        protected OwnerService $ownerService
    ) {}

    /**
     * Display all owners with their admin access level.
     */
    public function index(): Response
    {
        return Inertia::render('Owners/Index', [
            'owners' => $this->ownerService->getAll()->map(fn (User $owner) => $this->formatOwner($owner)),
        ]);
    }

    /**
     * Switch an owner between full and restricted admin access.
     */
    public function updateAccess(UpdateOwnerAccessRequest $request, User $user): JsonResponse
    {
        abort_unless($user->isOwner(), 404);

        $owner = $this->ownerService->updateAccess($user, $request->ownerAccess(), $request->user());

        return response()->json([
            'owner' => $this->formatOwner($owner),
        ]);
    }

    /**
     * @return array{id: int, name: string, email: string, owner_access: string, owner_access_label: string, created_at: string|null}
     */
    private function formatOwner(User $owner): array
    {
        $access = $owner->owner_access ?? OwnerAccess::All;

        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'owner_access' => $access->value,
            'owner_access_label' => $access->label(),
            'created_at' => $owner->created_at?->format('d M Y'),
        ];
    }
}

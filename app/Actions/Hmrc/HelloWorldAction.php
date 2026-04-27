<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Models\User;

class HelloWorldAction
{
    public function __construct(
        private readonly CallHmrcApiAction $callHmrcApi,
    ) {}

    /**
     * Call the user-restricted Hello World endpoint to prove the OAuth round-trip.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        return ($this->callHmrcApi)(
            user: $user,
            method: 'GET',
            path: '/hello/user',
            version: '1.0',
        );
    }
}

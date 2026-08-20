<?php

declare(strict_types=1);

namespace App\Actions\Bird;

use App\Models\BirdMessage;
use Illuminate\Database\Eloquent\Collection;

class GetAllBirdMessagesAction
{
    /**
     * Every mirrored message with its conversation's contact details, in
     * chronological order — the "one-stop dump" used for the full CSV export.
     *
     * @return Collection<int, BirdMessage>
     */
    public function __invoke(): Collection
    {
        return BirdMessage::query()
            ->with('conversation')
            ->orderBy('sent_at')
            ->get();
    }
}

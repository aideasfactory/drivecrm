<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class AutomaticFinanceEntryException extends Exception
{
    public function __construct(string $message = 'This finance record was added automatically from a lesson payment and cannot be changed or deleted.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}

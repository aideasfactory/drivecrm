<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\FillAvailableSlotsRequest as WebFillAvailableSlotsRequest;

/**
 * The web request's rules have no route/instructor dependency (the API resolves
 * the instructor from the authenticated user), so the validation is shared.
 */
class FillAvailableSlotsRequest extends WebFillAvailableSlotsRequest {}

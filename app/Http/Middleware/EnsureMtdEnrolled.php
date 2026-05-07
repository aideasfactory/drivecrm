<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ItsaEnrolmentStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates ITSA submission routes — only instructors whose MTD ITSA enrolment is
 * resolved AND whose status permits submission may pass. The Index route is
 * deliberately NOT gated so users in the not_signed_up / income_source_missing
 * states can still see the explanatory page.
 */
class EnsureMtdEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $instructor = $request->user()?->instructor;

        if ($instructor === null) {
            abort(403, 'Instructor profile not found.');
        }

        $status = $instructor->mtd_itsa_status instanceof ItsaEnrolmentStatus
            ? $instructor->mtd_itsa_status
            : ItsaEnrolmentStatus::tryFrom((string) $instructor->mtd_itsa_status) ?? ItsaEnrolmentStatus::Unknown;

        if (! $status->canSubmit()) {
            abort(403, 'You are not currently signed up for MTD ITSA. Visit the HMRC ITSA page for next steps.');
        }

        return $next($request);
    }
}

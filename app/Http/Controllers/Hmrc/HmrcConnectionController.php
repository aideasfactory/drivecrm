<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc;

use App\Enums\BusinessType;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInstructorTaxProfileRequest;
use App\Models\HmrcDeviceIdentifier;
use App\Models\User;
use App\Services\HmrcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class HmrcConnectionController extends Controller
{
    public function __construct(
        protected HmrcService $hmrc,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $instructor = $user->instructor;
        $environment = (string) config('hmrc.environment', 'sandbox');

        return Inertia::render('Hmrc/Connection', [
            'environment' => $environment,
            'connection' => $this->hmrc->connectionStatusFor($user),
            'helloWorldResponse' => $request->session()->get('hmrc_hello_world'),
            'taxProfile' => $instructor ? $this->hmrc->getTaxProfile($instructor) : null,
            'applicability' => $instructor ? $this->hmrc->getMtdApplicability($instructor) : null,
            'businessTypes' => $this->businessTypeOptions(),
        ]);
    }

    public function updateTaxProfile(UpdateInstructorTaxProfileRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;

        $this->hmrc->updateTaxProfile($instructor, $request->validated());

        return redirect($this->hmrcAreaUrl())
            ->with('success', 'Tax profile updated.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function businessTypeOptions(): array
    {
        return array_map(
            fn (BusinessType $type) => ['value' => $type->value, 'label' => $type->label()],
            BusinessType::cases(),
        );
    }

    public function connect(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->ensureDeviceIdentifier($request, $user->id);

        $url = $this->hmrc->beginAuthorization($user);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();
        $error = $request->query('error');

        if ($error) {
            return redirect()
                ->route('hmrc.index')
                ->with('error', "HMRC reported an error: {$error}");
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect()
                ->route('hmrc.index')
                ->with('error', 'HMRC callback was missing required parameters.');
        }

        try {
            $this->hmrc->completeAuthorization($user, $code, $state);
        } catch (HmrcApiException $exception) {
            return redirect()
                ->route('hmrc.index')
                ->with('error', $exception->userMessage());
        }

        return redirect($this->hmrcAreaUrl())
            ->with('success', 'Connected to HMRC successfully.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->hmrc->disconnect($request->user());

        return redirect($this->hmrcAreaUrl())
            ->with('success', 'HMRC connection removed.');
    }

    private function hmrcAreaUrl(?User $user = null): string
    {
        $user = $user ?? request()->user();
        $instructor = $user?->instructor;

        if ($instructor) {
            return url("/instructors/{$instructor->id}?tab=hmrc");
        }

        return route('hmrc.index');
    }

    private function ensureDeviceIdentifier(Request $request, int $userId): void
    {
        $cookieName = (string) config('hmrc.device_cookie.name', 'hmrc_device_id');
        $cookieValue = $request->cookie($cookieName);

        $identifier = HmrcDeviceIdentifier::forUser($request->user(), is_string($cookieValue) ? $cookieValue : null);

        if ($cookieValue !== $identifier->device_id) {
            Cookie::queue(Cookie::make(
                $cookieName,
                $identifier->device_id,
                (int) config('hmrc.device_cookie.lifetime_minutes', 60 * 24 * 365 * 10),
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }
    }
}

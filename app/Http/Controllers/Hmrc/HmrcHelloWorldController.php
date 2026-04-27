<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\HmrcReconnectRequiredException;
use App\Http\Controllers\Controller;
use App\Services\HmrcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HmrcHelloWorldController extends Controller
{
    public function __construct(
        protected HmrcService $hmrc,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $response = $this->hmrc->helloWorld($request->user());
        } catch (HmrcReconnectRequiredException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return back()->with('error', $exception->userMessage());
        }

        return back()
            ->with('success', 'Hello World call succeeded.')
            ->with('hmrc_hello_world', $response);
    }
}

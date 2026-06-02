<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc\Vehicles;

use App\Enums\VehicleMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hmrc\Vehicles\BackfillPrimaryVehicleRequest;
use App\Http\Requests\Hmrc\Vehicles\ReviewInsuranceSplitRequest;
use App\Http\Requests\Hmrc\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Hmrc\Vehicles\SwitchVehicleMethodRequest;
use App\Http\Requests\Hmrc\Vehicles\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(
        protected VehicleService $vehicles,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Hmrc/Vehicles/Index', $this->indexData($request));
    }

    /**
     * Build the data array for the Vehicles index. Reused by InstructorController
     * when embedding the panel inside the instructor layout.
     *
     * @return array<string, mixed>
     */
    public function indexData(Request $request): array
    {
        $instructor = $request->user()->instructor;

        $vehicles = $this->vehicles->vehiclesFor($instructor)->map(fn (Vehicle $v) => [
            'id' => $v->id,
            'display_name' => $v->display_name,
            'registration' => $v->registration,
            'engine_size_cc' => $v->engine_size_cc,
            'method' => [
                'value' => $v->method->value,
                'label' => $v->method->label(),
            ],
            'business_use_percentage' => $v->business_use_percentage,
            'acquired_on' => $v->acquired_on?->toDateString(),
            'disposed_on' => $v->disposed_on?->toDateString(),
            'method_locked' => $v->methodLocked(),
        ])->values()->all();

        return [
            'vehicles' => $vehicles,
            'methodOptions' => [
                ['value' => VehicleMethod::Simplified->value, 'label' => VehicleMethod::Simplified->label()],
                ['value' => VehicleMethod::Actual->value, 'label' => VehicleMethod::Actual->label()],
            ],
        ];
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;

        $this->vehicles->createForInstructor($instructor, $request->validated());

        return back(fallback: route('hmrc.vehicles.index'))->with('success', 'Vehicle added.');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->vehicles->updateVehicle($vehicle, $request->validated());

        return back(fallback: route('hmrc.vehicles.index'))->with('success', 'Vehicle updated.');
    }

    public function dispose(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->assertOwnsVehicle($request, $vehicle);

        $this->vehicles->dispose($vehicle);

        return back(fallback: route('hmrc.vehicles.index'))->with('success', 'Vehicle marked as disposed.');
    }

    public function switchMethod(SwitchVehicleMethodRequest $request, Vehicle $vehicle): JsonResponse
    {
        $target = VehicleMethod::from($request->validated('method'));
        $confirmed = (bool) $request->validated('confirmed', false);

        $result = $this->vehicles->switchMethod($vehicle, $target, $confirmed);

        return response()->json([
            'status' => $result['status'],
            'from' => $result['from']->value,
            'to' => $result['to']->value,
            'message' => $result['message'] ?? null,
            'vehicle' => [
                'id' => $result['vehicle']->id,
                'method' => [
                    'value' => $result['vehicle']->method->value,
                    'label' => $result['vehicle']->method->label(),
                ],
                'method_locked' => $result['vehicle']->methodLocked(),
            ],
        ]);
    }

    public function compare(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertOwnsVehicle($request, $vehicle);

        $end = Carbon::now()->startOfDay();
        $start = $end->copy()->subYear();

        $result = $this->vehicles->compareMethods($vehicle, $start, $end);

        return response()->json([
            'window' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'simplified_pence' => $result['simplified_pence'],
            'actual_pence' => $result['actual_pence'],
            'business_miles' => $result['business_miles'],
            'vehicle_running_costs_pence' => $result['vehicle_running_costs_pence'],
            'business_use_percentage' => $result['business_use_percentage'],
        ]);
    }

    public function backfillPrimary(BackfillPrimaryVehicleRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;

        $result = $this->vehicles->backfillPrimaryFor($instructor, $request->validated());

        return back(fallback: route('hmrc.vehicles.index'))->with(
            'success',
            "Vehicle saved. {$result['finance_rows_tagged']} expense rows and {$result['mileage_rows_tagged']} mileage entries tagged.",
        );
    }

    public function reviewInsurance(ReviewInsuranceSplitRequest $request): RedirectResponse
    {
        $instructor = $request->user()->instructor;
        $decisions = $request->validated('decisions');

        $updated = $this->vehicles->applyInsuranceReview($instructor, $decisions);

        return back(fallback: route('hmrc.vehicles.index'))->with('success', "{$updated} insurance rows re-tagged.");
    }

    private function assertOwnsVehicle(Request $request, Vehicle $vehicle): void
    {
        $instructor = $request->user()?->instructor;

        if ($instructor === null || $vehicle->instructor_id !== $instructor->id) {
            abort(403);
        }
    }
}

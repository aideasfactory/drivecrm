<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\CreateOrderFromEnquiryAction;
use App\Actions\Onboarding\CreateUserAndStudentFromEnquiryAction;
use App\Actions\Onboarding\SendOrderConfirmationEmailAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepSixRequest;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Package;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StepSixController extends Controller
{
    public function __construct(
        protected StripeService $stripeService,
        protected CreateUserAndStudentFromEnquiryAction $createUserAndStudentAction,
        protected CreateOrderFromEnquiryAction $createOrderAction,
        protected SendOrderConfirmationEmailAction $sendEmailAction
    ) {}

    /**
     * Show Step 6: Payment page.
     */
    public function show(Request $request): Response
    {
        $enquiry = $request->get('enquiry');

        // Gather data from all previous steps
        $step1 = $enquiry->getStepData(1) ?? [];
        $step2 = $enquiry->getStepData(2) ?? [];
        $step3 = $enquiry->getStepData(3) ?? [];
        $step4 = $enquiry->getStepData(4) ?? [];
        $step5 = $enquiry->getStepData(5) ?? [];

        // Load instructor
        $instructor = null;
        if (! empty($step2['instructor_id'])) {
            $instructor = Instructor::with('user')->find($step2['instructor_id']);
        }

        // Load package
        $package = null;
        if (! empty($step3['package_id'])) {
            $package = Package::find($step3['package_id']);
        }

        if (! $package) {
            return redirect()
                ->route('onboarding.step3', ['uuid' => $enquiry->id])
                ->with('error', 'Please select a package first.');
        }

        // Calculate pricing (in pounds for display)
        $packagePrice = $package->total_price;
        $lessonPrice = $package->weekly_payment;

        return Inertia::render('Onboarding/Step6', [
            'uuid' => $enquiry->id,
            'currentStep' => 6,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(6),
            'maxStepReached' => $enquiry->max_step_reached,

            // Instructor details
            'instructor' => $instructor ? [
                'id' => $instructor->id,
                'name' => $instructor->user->name,
            ] : null,

            // Package details
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'lessons_count' => $package->lessons_count,
                'formatted_total_price' => $package->formatted_total_price,
                'formatted_lesson_price' => $package->formatted_lesson_price,
                'booking_fee' => $package->booking_fee,
                'digital_fee' => $package->digital_fee,
                'total_price' => $package->total_price,
                'weekly_payment' => $package->weekly_payment,
                'lesson_price' => $lessonPrice,
            ],

            // Schedule details
            'schedule' => [
                'date' => $step4['date'] ?? null,
                'start_time' => $step4['start_time'] ?? null,
                'end_time' => $step4['end_time'] ?? null,
            ],

            // Pricing for both payment modes
            'pricing' => [
                'upfront' => [
                    'total' => $packagePrice,
                    'per_lesson' => $lessonPrice,
                ],
                'weekly' => [
                    'per_lesson' => $lessonPrice,
                    'total_over_time' => $packagePrice,
                ],
            ],
        ]);
    }

    /**
     * Process Step 6: Create user/student/order and redirect to Stripe.
     */
    public function store(StepSixRequest $request): RedirectResponse|HttpResponse
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        Log::info('=== ONBOARDING STEP 6: Payment Process Started ===', [
            'enquiry_id' => $enquiry->id,
            'validated_data' => $validated,
            'all_request_data' => $request->all(),
        ]);

        $paymentMode = PaymentMode::from($validated['payment_mode']);

        Log::info('Payment mode determined', [
            'payment_mode' => $paymentMode->value,
            'enquiry_id' => $enquiry->id,
        ]);

        try {
            DB::beginTransaction();

            // Get package
            $step3 = $enquiry->getStepData(3) ?? [];

            Log::info('Step 3 data retrieved', [
                'step3_data' => $step3,
                'enquiry_id' => $enquiry->id,
            ]);

            $package = Package::findOrFail($step3['package_id']);

            Log::info('Package loaded', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'stripe_product_id' => $package->stripe_product_id,
                'stripe_price_id' => $package->stripe_price_id,
                'enquiry_id' => $enquiry->id,
            ]);

            // Verify package is active
            if (! $package->active) {
                return redirect()
                    ->route('onboarding.step3', ['uuid' => $enquiry->id])
                    ->with('error', 'This package is no longer available.');
            }

            // Create or retrieve user and student
            Log::info('Creating/retrieving user and student', [
                'enquiry_id' => $enquiry->id,
            ]);

            $result = $this->createUserAndStudentAction->execute($enquiry);
            $user = $result['user'];
            $student = $result['student'];

            Log::info('User and student ready for onboarding checkout', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'student_id' => $student->id,
                'stripe_customer_id' => $user->stripe_customer_id,
                'is_new_user' => $result['is_new_user'],
                'enquiry_id' => $enquiry->id,
            ]);

            // Create order with lessons
            Log::info('Creating order from enquiry', [
                'enquiry_id' => $enquiry->id,
                'student_id' => $student->id,
                'package_id' => $package->id,
                'payment_mode' => $paymentMode->value,
            ]);

            $order = $this->createOrderAction->execute(
                $enquiry,
                $student,
                $package,
                $paymentMode
            );

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'status' => $order->status->value,
                'lessons_count' => $order->lessons()->count(),
                'enquiry_id' => $enquiry->id,
            ]);

            // Save order details to enquiry
            $enquiry->setStepData(6, [
                'payment_mode' => $paymentMode->value,
                'user_id' => $user->id,
                'student_id' => $student->id,
                'order_id' => $order->id,
                'payment_status' => 'pending',
            ]);
            $enquiry->current_step = 6;
            $enquiry->max_step_reached = max($enquiry->max_step_reached, 6);
            $enquiry->save();

            // Branch based on payment mode
            Log::info('Processing payment based on mode', [
                'payment_mode' => $paymentMode->value,
                'order_id' => $order->id,
                'enquiry_id' => $enquiry->id,
            ]);

            if ($paymentMode === PaymentMode::UPFRONT) {
                // UPFRONT PAYMENT: Redirect to Stripe Checkout
                Log::info('Handling upfront payment - creating Stripe session', [
                    'order_id' => $order->id,
                    'enquiry_id' => $enquiry->id,
                ]);
                $sessionResult = $this->handleUpfrontPayment($enquiry, $order, $package, $user);
            } else {
                // WEEKLY PAYMENT: Activate immediately (invoices sent later)
                Log::info('Handling weekly payment - activating order immediately', [
                    'order_id' => $order->id,
                    'enquiry_id' => $enquiry->id,
                ]);
                $sessionResult = $this->handleWeeklyPayment($enquiry, $order);
            }

            Log::info('Payment handling result', [
                'success' => $sessionResult['success'],
                'session_id' => $sessionResult['session_id'] ?? null,
                'url' => $sessionResult['url'] ?? null,
                'error' => $sessionResult['error'] ?? null,
                'order_id' => $order->id,
                'enquiry_id' => $enquiry->id,
            ]);

            if (! $sessionResult['success']) {
                throw new \Exception('Failed to create checkout session: '.($sessionResult['error'] ?? 'Unknown error'));
            }

            // Save checkout session ID
            if (! empty($sessionResult['session_id'])) {
                $order->stripe_checkout_session_id = $sessionResult['session_id'];
                $order->save();
            }

            DB::commit();

            Log::info('Onboarding checkout session created', [
                'order_id' => $order->id,
                'payment_mode' => $paymentMode->value,
                'redirect_url' => $sessionResult['url'],
            ]);

            // Return Inertia response with redirect URL for frontend to handle
            return Inertia::location($sessionResult['url']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Onboarding checkout failed', [
                'enquiry_id' => $enquiry->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('onboarding.step6', ['uuid' => $enquiry->id])
                ->with('error', 'Failed to process payment: '.$e->getMessage());
        }
    }

    /**
     * Handle upfront payment checkout session creation.
     */
    protected function handleUpfrontPayment($enquiry, Order $order, Package $package, $user): array
    {
        $instructor = $order->instructor;

        $successUrl = route('onboarding.checkout.success', ['uuid' => $enquiry->id]).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('onboarding.checkout.cancel', ['uuid' => $enquiry->id]);

        Log::info('Preparing Stripe checkout session', [
            'order_id' => $order->id,
            'package_id' => $package->id,
            'package_stripe_product_id' => $package->stripe_product_id,
            'package_stripe_price_id' => $package->stripe_price_id,
            'user_id' => $user->id,
            'user_stripe_customer_id' => $user->stripe_customer_id,
            'instructor_id' => $instructor?->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'enquiry_id' => $enquiry->id,
        ]);

        // Check if user has Stripe customer ID
        if (! $user->stripe_customer_id) {
            Log::warning('User missing Stripe customer ID - will need to create', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'order_id' => $order->id,
            ]);

            // Create Stripe customer
            $customerResult = $this->stripeService->createOrGetCustomer($user);

            if (! $customerResult['success']) {
                Log::error('Failed to create Stripe customer', [
                    'user_id' => $user->id,
                    'error' => $customerResult['error'] ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to create Stripe customer: '.($customerResult['error'] ?? 'Unknown error'),
                ];
            }

            $user->stripe_customer_id = $customerResult['customer_id'];
            $user->save();

            Log::info('Stripe customer created successfully', [
                'user_id' => $user->id,
                'stripe_customer_id' => $user->stripe_customer_id,
            ]);
        }

        // Check if package has Stripe price ID
        if (! $package->stripe_price_id) {
            Log::error('Package missing Stripe price ID', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'stripe_product_id' => $package->stripe_product_id,
            ]);

            return [
                'success' => false,
                'error' => 'Package is not configured for Stripe payments. Please contact support.',
            ];
        }

        $result = $this->stripeService->createCheckoutSession(
            $order,
            $package,
            $user,
            $instructor,
            $successUrl,
            $cancelUrl
        );

        Log::info('Stripe checkout session creation result', [
            'success' => $result['success'],
            'session_id' => $result['session_id'] ?? null,
            'error' => $result['error'] ?? null,
            'order_id' => $order->id,
        ]);

        return $result;
    }

    /**
     * Handle weekly payment (no Stripe checkout needed).
     */
    protected function handleWeeklyPayment($enquiry, Order $order): array
    {
        // Activate order immediately for weekly payments
        $order->status = OrderStatus::ACTIVE;
        $order->save();

        // Redirect to success page
        $successUrl = route('onboarding.checkout.success', ['uuid' => $enquiry->id]);

        return [
            'success' => true,
            'session_id' => null,
            'url' => $successUrl,
        ];
    }

    /**
     * Handle successful checkout callback.
     */
    public function success(Request $request): RedirectResponse
    {
        $enquiry = $request->get('enquiry');
        $step6 = $enquiry->getStepData(6) ?? [];

        if (empty($step6['order_id'])) {
            return redirect()
                ->route('onboarding.start')
                ->with('error', 'Invalid checkout session.');
        }

        $order = Order::with(['student', 'package', 'instructor'])->find($step6['order_id']);

        if (! $order) {
            return redirect()
                ->route('onboarding.start')
                ->with('error', 'Order not found.');
        }

        // For weekly payment, order is already active
        if ($order->isWeekly()) {
            // Send confirmation email
            $this->sendEmailAction->execute($order, $order->student);

            // Update enquiry
            $enquiry->setStepData(6, array_merge($step6, [
                'payment_status' => 'completed',
            ]));
            $enquiry->save();

            return redirect()
                ->route('onboarding.complete', ['uuid' => $enquiry->id]);
        }

        // For upfront payment, verify Stripe session
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return redirect()
                ->route('onboarding.start')
                ->with('error', 'Invalid checkout session.');
        }

        try {
            // Retrieve the checkout session from Stripe
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            // Verify the session matches the order
            if ($session->id !== $order->stripe_checkout_session_id) {
                throw new \Exception('Session ID mismatch.');
            }

            // Check payment status
            if ($session->payment_status === 'paid') {
                // Update order if still pending (webhook might have already processed it)
                if ($order->status === OrderStatus::PENDING) {
                    $order->status = OrderStatus::ACTIVE;
                    $order->stripe_payment_intent_id = $session->payment_intent;
                    $order->save();
                }

                // Send confirmation email
                $this->sendEmailAction->execute($order, $order->student);

                // Update enquiry
                $enquiry->setStepData(6, array_merge($step6, [
                    'payment_status' => 'completed',
                    'stripe_session_id' => $sessionId,
                ]));
                $enquiry->save();

                return redirect()
                    ->route('onboarding.complete', ['uuid' => $enquiry->id]);
            }

            return redirect()
                ->route('onboarding.step6', ['uuid' => $enquiry->id])
                ->with('warning', 'Payment is being processed. Please check back shortly.');

        } catch (\Exception $e) {
            Log::error('Failed to verify onboarding payment', [
                'enquiry_id' => $enquiry->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('onboarding.step6', ['uuid' => $enquiry->id])
                ->with('error', 'Failed to verify payment: '.$e->getMessage());
        }
    }

    /**
     * Handle cancelled checkout.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $enquiry = $request->get('enquiry');

        Log::info('Onboarding checkout cancelled', [
            'enquiry_id' => $enquiry->id,
        ]);

        // Keep the order as pending - user can retry
        return redirect()
            ->route('onboarding.step6', ['uuid' => $enquiry->id])
            ->with('warning', 'Checkout cancelled. You can try again when ready.');
    }
}

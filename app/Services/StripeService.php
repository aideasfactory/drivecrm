<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Transfer;
use Stripe\Webhook;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        // Set API key globally for all Stripe operations
        Stripe::setApiKey(config('services.stripe.secret'));

        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Connect Express account for an instructor.
     */
    public function createConnectAccount(Instructor $instructor): array
    {
        try {
            $account = $this->stripe->accounts->create([
                'type' => 'express',
                'country' => 'GB',
                'email' => $instructor->user->email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
            ]);

            return [
                'success' => true,
                'account_id' => $account->id,
                'account' => $account,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect account creation failed', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate an Account Link for instructor onboarding.
     */
    public function createAccountLink(Instructor $instructor, string $returnUrl, string $refreshUrl): array
    {
        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account' => $instructor->stripe_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return [
                'success' => true,
                'url' => $accountLink->url,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Account Link creation failed', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Stripe Product for a package.
     */
    public function createProduct(Package $package): array
    {
        try {
            $product = $this->stripe->products->create([
                'name' => $package->name,
                'description' => "{$package->lessons_count} driving lessons",
            ]);

            return [
                'success' => true,
                'product_id' => $product->id,
                'product' => $product,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Product creation failed', [
                'package_id' => $package->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Stripe Price for a package (upfront payment).
     */
    public function createPrice(Package $package): array
    {
        try {
            $price = $this->stripe->prices->create([
                'product' => $package->stripe_product_id,
                'unit_amount' => $package->total_price_pence,
                'currency' => 'gbp',
            ]);

            return [
                'success' => true,
                'price_id' => $price->id,
                'price' => $price,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Price creation failed', [
                'package_id' => $package->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create or retrieve Stripe Customer for a student.
     */
    public function createOrGetCustomer(User $student): array
    {
        try {
            Log::info('StripeService: Creating or retrieving Stripe customer', [
                'user_id' => $student->id,
                'user_email' => $student->email,
                'existing_stripe_customer_id' => $student->stripe_customer_id,
            ]);

            // If customer already exists, return it
            if ($student->stripe_customer_id) {
                Log::info('StripeService: Retrieving existing Stripe customer', [
                    'stripe_customer_id' => $student->stripe_customer_id,
                ]);

                $customer = $this->stripe->customers->retrieve($student->stripe_customer_id);

                Log::info('StripeService: Existing customer retrieved successfully', [
                    'customer_id' => $customer->id,
                    'customer_email' => $customer->email,
                ]);

                return [
                    'success' => true,
                    'customer_id' => $customer->id,
                    'customer' => $customer,
                ];
            }

            // Create new customer
            Log::info('StripeService: Creating new Stripe customer', [
                'user_id' => $student->id,
                'user_email' => $student->email,
                'user_name' => $student->name,
            ]);

            $customer = $this->stripe->customers->create([
                'email' => $student->email,
                'name' => $student->name,
                'metadata' => [
                    'user_id' => $student->id,
                ],
            ]);

            Log::info('StripeService: New customer created successfully', [
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'user_id' => $student->id,
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'customer' => $customer,
            ];
        } catch (ApiErrorException $e) {
            Log::error('StripeService: Customer creation/retrieval failed', [
                'user_id' => $student->id,
                'user_email' => $student->email,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Checkout Session for student enrollment.
     */
    public function createCheckoutSession(Order $order, Package $package, User $student, ?Instructor $instructor, string $successUrl, string $cancelUrl): array
    {
        try {
            // Use total_price_pence (package + booking fee + digital fees) when available,
            // otherwise fall back to package_total_price_pence for legacy orders
            $chargeAmountPence = $order->total_price_pence ?? $order->package_total_price_pence;

            $hasDiscount = $order->discount_percentage !== null && $order->discount_percentage > 0;
            $hasFeesOrDiscount = $order->total_price_pence !== null || $hasDiscount;

            Log::info('StripeService: Creating checkout session', [
                'order_id' => $order->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'student_id' => $student->id,
                'student_email' => $student->email,
                'stripe_customer_id' => $student->stripe_customer_id,
                'stripe_price_id' => $package->stripe_price_id,
                'instructor_id' => $instructor?->id,
                'has_discount' => $hasDiscount,
                'discount_percentage' => $order->discount_percentage,
                'charge_amount_pence' => $chargeAmountPence,
                'booking_fee_pence' => $order->booking_fee_pence,
                'digital_fee_pence' => $order->digital_fee_pence,
            ]);

            // Always use price_data when fees are included or a discount is applied,
            // since the pre-created Stripe Price only reflects the base package price
            if ($hasFeesOrDiscount) {
                $lineItem = [
                    'price_data' => [
                        'currency' => 'gbp',
                        'product' => $package->stripe_product_id,
                        'unit_amount' => $chargeAmountPence,
                    ],
                    'quantity' => 1,
                ];
            } else {
                $lineItem = [
                    'price' => $package->stripe_price_id,
                    'quantity' => 1,
                ];
            }

            $sessionData = [
                'mode' => 'payment',
                'customer' => $student->stripe_customer_id,
                'line_items' => [$lineItem],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'order_id' => $order->id,
                    'package_id' => $package->id,
                    'student_id' => $student->id,
                    'discount_code_id' => $order->discount_code_id,
                    'discount_percentage' => $order->discount_percentage,
                ],
            ];

            Log::info('StripeService: Session data prepared', [
                'session_data' => $sessionData,
                'order_id' => $order->id,
            ]);

            // For instructor packages (not needed in Phase 1 upfront payment, but prepared for future)
            // Note: Platform holds funds and transfers per lesson after completion
            // This is handled in lesson completion, not at checkout

            Log::info('StripeService: Calling Stripe API to create session', [
                'order_id' => $order->id,
            ]);

            $session = $this->stripe->checkout->sessions->create($sessionData);

            Log::info('StripeService: Stripe session created successfully', [
                'session_id' => $session->id,
                'session_url' => $session->url,
                'payment_status' => $session->payment_status,
                'order_id' => $order->id,
            ]);

            return [
                'success' => true,
                'session_id' => $session->id,
                'session' => $session,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            Log::error('StripeService: Checkout Session creation failed', [
                'order_id' => $order->id,
                'package_id' => $package->id,
                'student_id' => $student->id,
                'stripe_customer_id' => $student->stripe_customer_id,
                'stripe_price_id' => $package->stripe_price_id,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Transfer to an instructor's connected account.
     *
     * When $sourceTransaction (a Stripe charge id) is provided, the transfer is tied to
     * that specific charge so Stripe draws against it rather than the general available
     * balance — this permits the transfer even when the platform balance is still pending.
     */
    public function createTransfer(Lesson $lesson, Instructor $instructor, int $amountPence, ?string $sourceTransaction = null): array
    {
        try {
            $payload = [
                'amount' => $amountPence,
                'currency' => 'gbp',
                'destination' => $instructor->stripe_account_id,
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'order_id' => $lesson->order_id,
                    'instructor_id' => $instructor->id,
                ],
            ];

            if ($sourceTransaction !== null) {
                $payload['source_transaction'] = $sourceTransaction;
            }

            $transfer = $this->stripe->transfers->create($payload);

            return [
                'success' => true,
                'transfer_id' => $transfer->id,
                'transfer' => $transfer,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Transfer creation failed', [
                'lesson_id' => $lesson->id,
                'instructor_id' => $instructor->id,
                'amount_pence' => $amountPence,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the funding charge id for a PaymentIntent (upfront orders).
     *
     * Returns the PaymentIntent's `latest_charge` or null if it cannot be resolved.
     */
    public function getChargeIdForPaymentIntent(string $paymentIntentId): ?string
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return $paymentIntent->latest_charge ?: null;
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to resolve charge id for payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve the funding charge id for an Invoice (weekly orders).
     *
     * Prefers the invoice's `charge`, falling back to the invoice's payment
     * intent `latest_charge`. Returns null if it cannot be resolved.
     */
    public function getChargeIdForInvoice(string $invoiceId): ?string
    {
        try {
            $invoice = $this->stripe->invoices->retrieve($invoiceId);

            if ($invoice->charge) {
                return $invoice->charge;
            }

            if ($invoice->payment_intent) {
                return $this->getChargeIdForPaymentIntent($invoice->payment_intent);
            }

            return null;
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to resolve charge id for invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify Stripe webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );

            return [
                'success' => true,
                'event' => $event,
            ];
        } catch (Exception $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a Stripe Account (for checking onboarding status).
     */
    public function retrieveAccount(string $accountId): array
    {
        try {
            $account = $this->stripe->accounts->retrieve($accountId);

            return [
                'success' => true,
                'account' => $account,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Account retrieval failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Stripe Invoice for a lesson payment.
     *
     * When $breakdown is provided (an array with 'lesson', 'booking_fee' and
     * 'digital_fee' pence integers that sum to $amountPence), separate
     * invoice items are created for each non-zero component so the student
     * sees a proper cost breakdown on the hosted invoice. Otherwise a single
     * line item is used.
     *
     * @param  array{lesson: int, booking_fee: int, digital_fee: int}|null  $breakdown
     */
    public function createInvoice(Lesson $lesson, User $student, int $amountPence, int $lessonPaymentId, ?array $breakdown = null): array
    {
        try {
            if ($amountPence <= 0) {
                Log::error('Cannot create invoice with zero or negative amount', [
                    'lesson_id' => $lesson->id,
                    'amount_pence' => $amountPence,
                ]);

                return ['success' => false, 'error' => 'Invoice amount must be greater than zero'];
            }

            $package = $lesson->order->package;
            $packageName = $lesson->order->package_name ?? ($package->name ?? 'Driving lessons');
            $lessonDateLabel = $lesson->date->format('d M Y').' '.$lesson->start_time->format('H:i');

            // Create draft invoice first, then attach the line item(s) to it
            $invoice = $this->stripe->invoices->create([
                'customer' => $student->stripe_customer_id,
                'auto_advance' => true,
                'collection_method' => 'send_invoice',
                'days_until_due' => 1,
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'lesson_payment_id' => $lessonPaymentId,
                    'order_id' => $lesson->order_id,
                    'payment_mode' => 'weekly',
                ],
            ]);

            $lineItems = $this->buildInvoiceLineItems(
                $amountPence,
                $breakdown,
                $packageName,
                $lessonDateLabel
            );

            $sharedMetadata = [
                'lesson_id' => $lesson->id,
                'lesson_payment_id' => $lessonPaymentId,
                'order_id' => $lesson->order_id,
                'package_id' => $package?->id,
            ];

            foreach ($lineItems as $lineItem) {
                $this->stripe->invoiceItems->create([
                    'customer' => $student->stripe_customer_id,
                    'invoice' => $invoice->id,
                    'amount' => $lineItem['amount'],
                    'currency' => 'gbp',
                    'description' => $lineItem['description'],
                    'metadata' => array_merge($sharedMetadata, [
                        'component' => $lineItem['component'],
                    ]),
                ]);
            }

            // Finalize the invoice to send it
            $invoice = $this->stripe->invoices->finalizeInvoice($invoice->id);

            return [
                'success' => true,
                'invoice_id' => $invoice->id,
                'invoice' => $invoice,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Invoice creation failed', [
                'lesson_id' => $lesson->id,
                'amount_pence' => $amountPence,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the invoice line items for a lesson payment. If a breakdown is
     * provided with non-zero fee components, each becomes its own itemised
     * line so the student sees the cost breakdown on the hosted invoice.
     * If the breakdown is missing or has no fee components, a single
     * "lesson payment" line item is returned.
     *
     * @param  array{lesson: int, booking_fee: int, digital_fee: int}|null  $breakdown
     * @return list<array{amount: int, description: string, component: string}>
     */
    protected function buildInvoiceLineItems(int $amountPence, ?array $breakdown, string $packageName, string $lessonDateLabel): array
    {
        $lessonComponent = (int) ($breakdown['lesson'] ?? 0);
        $bookingComponent = (int) ($breakdown['booking_fee'] ?? 0);
        $digitalComponent = (int) ($breakdown['digital_fee'] ?? 0);

        $hasBreakdown = $breakdown !== null
            && ($bookingComponent > 0 || $digitalComponent > 0)
            && ($lessonComponent + $bookingComponent + $digitalComponent) === $amountPence;

        if (! $hasBreakdown) {
            return [[
                'amount' => $amountPence,
                'description' => "Lesson payment for {$packageName} - {$lessonDateLabel}",
                'component' => 'lesson_payment',
            ]];
        }

        $items = [];

        if ($lessonComponent > 0) {
            $items[] = [
                'amount' => $lessonComponent,
                'description' => "Lesson cost — {$packageName} on {$lessonDateLabel}",
                'component' => 'lesson',
            ];
        }

        if ($bookingComponent > 0) {
            $items[] = [
                'amount' => $bookingComponent,
                'description' => 'Booking fee (weekly instalment)',
                'component' => 'booking_fee',
            ];
        }

        if ($digitalComponent > 0) {
            $items[] = [
                'amount' => $digitalComponent,
                'description' => 'Digital services fee (weekly instalment)',
                'component' => 'digital_fee',
            ];
        }

        return $items;
    }

    /**
     * Get the raw Stripe client for advanced operations.
     */
    public function getClient(): StripeClient
    {
        return $this->stripe;
    }
}

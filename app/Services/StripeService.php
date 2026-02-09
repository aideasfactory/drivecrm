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
use Stripe\StripeClient;
use Stripe\Transfer;
use Stripe\Webhook;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        // Set API key globally for all Stripe operations
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

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
            Log::info('StripeService: Creating checkout session', [
                'order_id' => $order->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'student_id' => $student->id,
                'student_email' => $student->email,
                'stripe_customer_id' => $student->stripe_customer_id,
                'stripe_price_id' => $package->stripe_price_id,
                'instructor_id' => $instructor?->id,
            ]);

            $sessionData = [
                'mode' => 'payment',
                'customer' => $student->stripe_customer_id,
                'line_items' => [[
                    'price' => $package->stripe_price_id,
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'order_id' => $order->id,
                    'package_id' => $package->id,
                    'student_id' => $student->id,
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
     */
    public function createTransfer(Lesson $lesson, Instructor $instructor, int $amountPence): array
    {
        try {
            $transfer = $this->stripe->transfers->create([
                'amount' => $amountPence,
                'currency' => 'gbp',
                'destination' => $instructor->stripe_account_id,
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'order_id' => $lesson->order_id,
                    'instructor_id' => $instructor->id,
                ],
            ]);

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
     */
    public function createInvoice(Lesson $lesson, User $student): array
    {
        try {
            $package = $lesson->order->package;

            // Create invoice item
            $invoiceItem = $this->stripe->invoiceItems->create([
                'customer' => $student->stripe_customer_id,
                'amount' => $lesson->amount_pence,
                'currency' => 'gbp',
                'description' => "Lesson payment for {$package->name} - ".$lesson->scheduled_at->format('d M Y H:i'),
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'order_id' => $lesson->order_id,
                    'package_id' => $package->id,
                ],
            ]);

            // Create and finalize invoice
            $invoice = $this->stripe->invoices->create([
                'customer' => $student->stripe_customer_id,
                'auto_advance' => true,
                'collection_method' => 'send_invoice',
                'days_until_due' => 1,
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'order_id' => $lesson->order_id,
                    'payment_mode' => 'weekly',
                ],
            ]);

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
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the raw Stripe client for advanced operations.
     */
    public function getClient(): StripeClient
    {
        return $this->stripe;
    }
}

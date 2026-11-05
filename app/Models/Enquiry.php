<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasUuids;

    /**
     * Google Ads click identifiers captured from the landing URL.
     *
     * @var list<string>
     */
    public const AD_CLICK_ID_FIELDS = ['gclid', 'gbraid', 'wbraid'];

    protected $fillable = [
        'data',
        'current_step',
        'max_step_reached',
        'privacy_consent',
        'marketing_consent',
        'consented_at',
        'gclid',
        'gbraid',
        'wbraid',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'current_step' => 'integer',
            'max_step_reached' => 'integer',
            'privacy_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    /**
     * Get step data by step number
     */
    public function getStepData(int $step): ?array
    {
        return $this->data['steps']["step{$step}"] ?? null;
    }

    /**
     * Update step data
     */
    public function setStepData(int $step, array $data): void
    {
        $currentData = $this->data ?? ['steps' => []];
        $currentData['steps']["step{$step}"] = $data;
        $currentData['current_step'] = max($step, $currentData['current_step'] ?? 1);
        $this->data = $currentData;
    }

    /**
     * Get discount data stored in the enquiry (if any).
     *
     * @return array{id: string, label: string, percentage: int}|null
     */
    public function getDiscountData(): ?array
    {
        return $this->data['discount'] ?? null;
    }

    /**
     * Get marketing tracking data captured at entry (currently only the
     * Google Ads gclid + source label).
     *
     * @return array{gclid?: string, source?: string}|null
     */
    public function getTracking(): ?array
    {
        return $this->data['tracking'] ?? null;
    }

    /**
     * Store the non-empty Google Ads click IDs from the given values. Empty
     * values never clear a stored ID, so a step reached without the query
     * string keeps the IDs captured on landing.
     *
     * @param  array<string, mixed>  $values
     */
    public function fillAdClickIds(array $values): void
    {
        foreach (self::AD_CLICK_ID_FIELDS as $field) {
            $value = $values[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $this->{$field} = trim($value);
            }
        }
    }

    /**
     * The stored Google Ads click IDs, non-empty values only. Enquiries saved
     * before the columns existed fall back to the gclid in data.tracking.
     *
     * @return array{gclid?: string, gbraid?: string, wbraid?: string}
     */
    public function adClickIds(): array
    {
        $ids = [
            'gclid' => $this->gclid ?? ($this->getTracking()['gclid'] ?? null),
            'gbraid' => $this->gbraid,
            'wbraid' => $this->wbraid,
        ];

        return array_filter($ids, fn ($value) => is_string($value) && $value !== '');
    }

    /**
     * Get the admin/bookings team member who started this enquiry on a
     * student's behalf. Staff bookings email the Stripe payment link to the
     * student instead of redirecting the browser to Stripe Checkout.
     *
     * @return array{user_id: int, name: string}|null
     */
    public function getStaffBooking(): ?array
    {
        return $this->data['staff_booking'] ?? null;
    }

    public function isStaffBooking(): bool
    {
        return $this->getStaffBooking() !== null;
    }

    /**
     * Whether this enquiry came from the 2-step /booking coverage form rather
     * than the full /onboarding booking flow.
     */
    public function isShortBookingForm(): bool
    {
        return ($this->data['source'] ?? null) === 'booking';
    }

    /**
     * Find the enquiry whose step 6 created the given order.
     */
    public static function findForOrder(int $orderId): ?self
    {
        return self::query()
            ->where(function ($query) use ($orderId): void {
                $query->where('data->steps->step6->order_id', $orderId)
                    ->orWhere('data->steps->step6->order_id', (string) $orderId);
            })
            ->latest()
            ->first();
    }
}

<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'instructor_id',
        'first_name',
        'surname',
        'email',
        'phone',
        'contact_first_name',
        'contact_surname',
        'contact_email',
        'contact_phone',
        'terms_accepted',
        'allow_communications',
        'contact_terms',
        'contact_communications',
        'owns_account',
        'status',
        'inactive_reason',
        'profile_picture_path',
    ];

    protected $appends = [
        'avatar',
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'allow_communications' => 'boolean',
        'contact_terms' => 'boolean',
        'contact_communications' => 'boolean',
        'owns_account' => 'boolean',
    ];

    /**
     * Calculate the total revenue in pence from all student orders.
     */
    protected function totalRevenuePence(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $this->loadMissing('orders.lessons.lessonPayment');

                $total = 0;

                foreach ($this->orders as $order) {
                    if ($order->payment_mode === PaymentMode::UPFRONT) {
                        if (in_array($order->status, [OrderStatus::ACTIVE, OrderStatus::COMPLETED])) {
                            $total += $order->package_total_price_pence ?? 0;
                        }
                    } else {
                        foreach ($order->lessons as $lesson) {
                            if ($lesson->lessonPayment?->status === PaymentStatus::PAID) {
                                $total += $lesson->lessonPayment->amount_pence;
                            }
                        }
                    }
                }

                return $total;
            },
        );
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->profile_picture_path) {
                    return Storage::disk('s3')->url($this->profile_picture_path);
                }

                return null;
            },
        );
    }

    /**
     * Get the profile picture URL (public S3 URL or null).
     */
    protected function profilePictureUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->profile_picture_path) {
                    return Storage::disk('s3')->url($this->profile_picture_path);
                }

                return null;
            },
        );
    }

    /**
     * Get the user that owns this student profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assigned instructor for this student.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get orders for this student.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if student has an assigned instructor.
     */
    public function hasInstructor(): bool
    {
        return $this->instructor_id !== null;
    }

    /**
     * Check if booking was made by a third party.
     */
    public function isBookedByThirdParty(): bool
    {
        return ! $this->owns_account;
    }

    /**
     * Get contact details (booker or learner based on context).
     */
    public function getBookerDetails(): array
    {
        if ($this->owns_account) {
            return [
                'first_name' => $this->first_name,
                'surname' => $this->surname,
                'email' => $this->email,
                'phone' => $this->phone,
            ];
        }

        return [
            'first_name' => $this->contact_first_name,
            'surname' => $this->contact_surname,
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
        ];
    }

    /**
     * Get emergency contacts for this student.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    /**
     * Get notes for this student.
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Get activity logs for this student.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Get pickup points for this student.
     */
    public function pickupPoints(): HasMany
    {
        return $this->hasMany(StudentPickupPoint::class);
    }

    /**
     * Get checklist items for this student.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(StudentChecklistItem::class);
    }

    public function mockTests(): HasMany
    {
        return $this->hasMany(MockTest::class);
    }

    public function hazardPerceptionAttempts(): HasMany
    {
        return $this->hasMany(HazardPerceptionAttempt::class);
    }

    /**
     * Check if student is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Helper method to log activity.
     */
    public function logActivity(string $message, string $category, ?array $metadata = null): void
    {
        app(\App\Actions\Shared\LogActivityAction::class)($this, $message, $category, $metadata);
    }
}

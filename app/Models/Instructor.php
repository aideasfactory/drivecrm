<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_account_id',
        'onboarding_complete',
        'charges_enabled',
        'payouts_enabled',
        'bio',
        'rating',
        'transmission_type',
        'status',
        'pdi_status',
        'priority',
        'address',
        'postcode',
        'latitude',
        'longitude',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_complete' => 'boolean',
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'priority' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected $appends = [
        'name',
        'first_name',
        'last_name',
        'avatar',
    ];

    /**
     * Get the user that owns this instructor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get instructor's locations (postcode sectors).
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get bespoke packages created by this instructor.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Get instructor's calendars.
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Get orders assigned to this instructor.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get payouts received by this instructor.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /**
     * Get lessons assigned to this instructor.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Check if instructor has completed onboarding.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_complete && $this->stripe_account_id !== null;
    }

    /**
     * Check if instructor can receive payouts.
     */
    public function canReceivePayouts(): bool
    {
        return $this->payouts_enabled;
    }

    /**
     * Scope query to only include active instructors.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to only include priority instructors.
     */
    public function scopePriority($query)
    {
        return $query->where('priority', true);
    }

    /**
     * Get instructor's first name.
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user->name ? explode(' ', $this->user->name)[0] : null,
        );
    }

    /**
     * Get instructor's last name.
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user->name ? explode(' ', $this->user->name)[1] : null,
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user->name ? explode(' ', $this->user->name)[0].' '.explode(' ', $this->user->name)[1] : null,
        );
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['avatar'] ?? null,
        );
    }

    /**
     * Get instructor's experience from meta data.
     */
    protected function experience(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['experience'] ?? null
        );
    }

    /**
     * Get instructor's pass rate from meta data.
     */
    protected function passRate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['pass_rate'] ?? null
        );
    }

    /**
     * Get instructor's total students from meta data.
     */
    protected function totalStudents(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['total_students'] ?? null
        );
    }

    /**
     * Get instructor's specialties from meta data.
     */
    protected function specialties(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['specialties'] ?? []
        );
    }

    /**
     * Get instructor's qualifications from meta data.
     */
    protected function qualifications(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['qualifications'] ?? []
        );
    }

    /**
     * Get instructor's languages from meta data.
     */
    protected function languages(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['languages'] ?? []
        );
    }

    /**
     * Get instructor's reviews count from meta data.
     */
    protected function reviews(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['reviews'] ?? null
        );
    }

    /**
     * Check if instructor is a top pick.
     */
    protected function isTopPick(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['is_top_pick'] ?? false
        );
    }

    /**
     * Get instructor's special offer from meta data.
     */
    protected function specialOffer(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['special_offer'] ?? null
        );
    }

    /**
     * Get instructor's transmissions from meta data.
     */
    protected function transmissions(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meta['meta']['transmissions'] ?? []
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'allow_communications' => 'boolean',
        'contact_terms' => 'boolean',
        'contact_communications' => 'boolean',
        'owns_account' => 'boolean',
    ];

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
}

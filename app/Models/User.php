<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the instructor profile if user is an instructor.
     */
    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
    }

    /**
     * Get the student profile if user is a student.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get orders where this user is the student (via student profile).
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Student::class, 'user_id', 'student_id');
    }

    /**
     * Check if user is owner.
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    /**
     * Check if user is instructor.
     */
    public function isInstructor(): bool
    {
        return $this->role === UserRole::INSTRUCTOR;
    }

    /**
     * Check if user is student.
     */
    public function isStudent(): bool
    {
        return $this->role === UserRole::STUDENT;
    }
}

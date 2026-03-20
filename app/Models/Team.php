<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /**
     * Default values for team settings.
     *
     * @var array<string, mixed>
     */
    public const SETTING_DEFAULTS = [
        'primary_color' => null,
        'default_slot_duration_minutes' => 120,
    ];

    protected $fillable = [
        'uuid',
        'name',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * Get the users that belong to this team.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_team_id');
    }

    /**
     * Get a specific setting value with fallback to defaults.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? self::SETTING_DEFAULTS[$key] ?? $default;
    }

    /**
     * Get the team's primary colour (hex string or null for default).
     */
    public function getPrimaryColor(): ?string
    {
        return $this->getSetting('primary_color');
    }

    /**
     * Get the team's default time-slot duration in minutes.
     */
    public function getDefaultSlotDurationMinutes(): int
    {
        return (int) $this->getSetting('default_slot_duration_minutes', 120);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /**
     * The seeded "Drive" team whose settings hold platform-wide configuration.
     */
    public const DEFAULT_TEAM_ID = 1;

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
}

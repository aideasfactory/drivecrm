<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'instructor_id',
        'postcode_sector',
    ];

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDestination extends Model
{
    protected $fillable = [
        'trip_id', 'destination_id', 'order', 'trip_day', 'visit_time', 'budget_allocated', 'notes',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }
}

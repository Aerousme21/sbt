<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'user_id', 'title', 'trip_date', 'total_budget', 'actual_spent', 'notes', 'status',
    ];

    protected $casts = [
        'trip_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tripDestinations()
    {
        return $this->hasMany(TripDestination::class)->orderBy('order');
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'trip_destinations')
            ->withPivot('order', 'visit_time', 'budget_allocated', 'notes')
            ->orderByPivot('order');
    }
}

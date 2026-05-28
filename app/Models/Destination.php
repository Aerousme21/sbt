<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'address',
        'lat', 'lng', 'price', 'opening_hours', 'image_url', 'images',
        'estimated_duration', 'rating', 'review_count', 'is_active', 'tips',
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'rating' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function savedByUsers()
    {
        return $this->hasMany(SavedDestination::class);
    }

    public function tripDestinations()
    {
        return $this->hasMany(TripDestination::class);
    }

    public function updateRating(): void
    {
        $avg = $this->reviews()->avg('rating') ?? 0;
        $count = $this->reviews()->count();
        $this->update(['rating' => round($avg, 2), 'review_count' => $count]);
    }
}

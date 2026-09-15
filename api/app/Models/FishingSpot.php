<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class FishingSpot extends Model
{
    protected $fillable = [
        'id',
        'name',
        'fishing_level',
        'territory_name',
        'map_id',
        'raw_x',
        'raw_z',
        'radius',
        'map_size_factor',
        'map_offset_x',
        'map_offset_y',
    ];

    public $incrementing = false;

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Item::class,
            'fishing_spot_items',
            'fishing_spot_id',
            'item_id'
        )->withTimestamps();
    }

    public function getMapX(): float
    {
        return ($this->raw_x / $this->map_size_factor * 2) + 1;
    }

    public function getMapY(): float
    {
        return ($this->raw_z / $this->map_size_factor * 2) + 1;
    }
}

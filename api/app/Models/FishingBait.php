<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FishingBait extends Model
{
    protected $fillable = ['fish_item_id', 'fishing_spot_id', 'bait_item_id', 'source_data', 'source_hash'];

    protected function casts(): array
    {
        return ['source_data' => 'array'];
    }

    public function fishItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'fish_item_id');
    }

    public function baitItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'bait_item_id');
    }

    public function fishingSpot(): BelongsTo
    {
        return $this->belongsTo(FishingSpot::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'gathering_checked_at',
        'fishing_checked_at',
    ];

    public function gatheringNodeItems()
    {
        return $this->hasMany(GatheringNodeItem::class);
    }

    public function gatheringNodes()
    {
        return $this->belongsToMany(
            GatheringNode::class,
            'gathering_node_items'
        )->withPivot('gathering_item_id')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'gathering_checked_at' => 'datetime',
            'fishing_checked_at' => 'datetime',
        ];
    }

    public function dungeonDrops(): HasMany
    {
        return $this->hasMany(DungeonDrop::class);
    }

    public function mobDrops(): HasMany
    {
        return $this->hasMany(MobDrop::class);
    }

    public function gilShopItems(): HasMany
    {
        return $this->hasMany(GilShopItem::class);
    }

    public function fishingBaits(): HasMany
    {
        return $this->hasMany(FishingBait::class, 'fish_item_id');
    }

    public function fishingSpots(): BelongsToMany
    {
        return $this->belongsToMany(
            FishingSpot::class,
            'fishing_spot_items',
            'item_id',
            'fishing_spot_id'
        )->withTimestamps();
    }
}

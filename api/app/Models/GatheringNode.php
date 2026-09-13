<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatheringNode extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'id',
        'gathering_type',
        'gathering_level',
        'area_name',
        'territory_name',
        'map_id',
        'raw_x',
        'raw_y',
        'radius',
        'map_size_factor',
        'map_offset_x',
        'map_offset_y',
    ];

    public function gatheringNodeItems()
    {
        return $this->hasMany(GatheringNodeItem::class);
    }

    public function items()
    {
        return $this->belongsToMany(
            Item::class,
            'gathering_node_items'
        )->withPivot('gathering_item_id')
            ->withTimestamps();
    }

    public function getMapX(): float
    {
        return (($this->raw_x + 1024) * 41 / 2048)
            * (100 / $this->map_size_factor)
            + 1
            + ($this->map_offset_x / 50);
    }

    public function getMapY(): float
    {
        return (($this->raw_y + 1024) * 41 / 2048)
            * (100 / $this->map_size_factor)
            + 1
            + ($this->map_offset_y / 50);
    }
}

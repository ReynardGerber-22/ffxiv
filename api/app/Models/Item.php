<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\GatheringNode;
use App\Models\GatheringNodeItem;

class Item extends Model
{
    public $incrementing = false;
    protected $fillable = [
        'id',
        'name',
        'gathering_checked_at',
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
        ];
    }
}

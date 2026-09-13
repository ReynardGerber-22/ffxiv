<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatheringNodeItem extends Model
{
    protected $fillable = [
        'gathering_node_id',
        'item_id',
        'gathering_item_id',
    ];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function gatheringNode()
    {
        return $this->belongsTo(GatheringNode::class);
    }
}

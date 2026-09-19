<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DungeonDrop extends Model
{
    protected $fillable = ['item_id', 'instance_id', 'name'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

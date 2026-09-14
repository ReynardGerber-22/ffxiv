<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobDrop extends Model
{
    protected $fillable = [
        'item_id',
        'bnpc_name_id',
        'mob_name',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GilShopItem extends Model
{
    protected $fillable = ['shop_id', 'subrow_id', 'item_id', 'price', 'is_hq', 'source_data'];

    protected function casts(): array
    {
        return ['source_data' => 'array', 'is_hq' => 'boolean', 'price' => 'integer'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(GilShop::class, 'shop_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

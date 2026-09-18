<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GilShop extends Model
{
    protected $fillable = ['id', 'name', 'source_data'];

    public $incrementing = false;

    protected function casts(): array
    {
        return ['source_data' => 'array'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(GilShopItem::class, 'shop_id');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(VendorNpc::class, 'vendor_shop_links', 'shop_id', 'vendor_npc_id')->withPivot('source_data')->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorNpc extends Model
{
    protected $fillable = ['id', 'name', 'title'];

    public $incrementing = false;

    public function locations(): HasMany
    {
        return $this->hasMany(VendorLocation::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(GilShop::class, 'vendor_shop_links', 'vendor_npc_id', 'shop_id')->withPivot('source_data')->withTimestamps();
    }
}

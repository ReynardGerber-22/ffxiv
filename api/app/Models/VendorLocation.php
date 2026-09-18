<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorLocation extends Model
{
    protected $fillable = ['vendor_npc_id', 'territory_id', 'territory', 'map_id', 'area', 'raw_x', 'raw_y', 'raw_z', 'x', 'y', 'source', 'source_key'];

    protected function casts(): array
    {
        return ['x' => 'float', 'y' => 'float'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorNpc::class, 'vendor_npc_id');
    }
}

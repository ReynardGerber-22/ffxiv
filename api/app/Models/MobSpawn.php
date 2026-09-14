<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobSpawn extends Model
{
    protected $fillable = [
        'bnpc_name_id',
        'bnpc_base_id',
        'territory_type_id',
        'territory_name',
        'x',
        'y',
        'z',
        'subtype',
    ];
}

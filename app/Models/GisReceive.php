<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GisReceive extends Model
{
    protected $table = 'gis_receives';
    protected $guarded = ['id'];

    protected $casts = [
        'items_json' => 'array',
    ];
}

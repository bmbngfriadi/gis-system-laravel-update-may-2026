<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GisRequest extends Model
{
    protected $table = 'gis_requests';
    protected $guarded = ['id'];

    protected $casts = [
        'items_json' => 'array',
    ];
}

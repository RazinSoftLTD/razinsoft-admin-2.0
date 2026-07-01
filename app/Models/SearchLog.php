<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $guarded = [];

    public const UPDATED_AT = null; // only track created_at

    protected $casts = ['results_count' => 'integer'];
}

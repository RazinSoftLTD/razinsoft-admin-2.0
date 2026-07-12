<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    protected $guarded = [];

    protected $casts = ['required' => 'boolean'];
}

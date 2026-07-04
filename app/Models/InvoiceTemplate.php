<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $guarded = [];

    protected $casts = ['items' => 'array'];
}

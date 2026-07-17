<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCategory extends Model
{
    protected $guarded = [];

    public static function names(): array
    {
        return static::orderBy('position')->orderBy('name')->pluck('name')->all();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCategory extends Model
{
    protected $guarded = [];

    protected $casts = ['show_in_menu' => 'boolean'];

    public static function names(): array
    {
        return static::orderBy('position')->orderBy('name')->pluck('name')->all();
    }

    /** The categories that asked for their own sidebar entry, in the order they are configured. */
    public static function inMenu()
    {
        return static::where('show_in_menu', true)->orderBy('position')->orderBy('name')->get(['id', 'name']);
    }
}

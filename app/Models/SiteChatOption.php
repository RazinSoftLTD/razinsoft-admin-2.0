<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A button the widget offers before the visitor types.
 *
 * Most people do not know what to ask; a short menu of the things we can actually help with
 * turns a blank box into a first message.
 */
class SiteChatOption extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('position')->orderBy('id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'variables', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Render subject + body with {{placeholder}} substitution. Returns null if missing/inactive. */
    public static function render(string $key, array $data = []): ?array
    {
        $tpl = static::where('key', $key)->where('is_active', true)->first();
        if (! $tpl) {
            return null;
        }

        $replace = function (string $text) use ($data) {
            foreach ($data as $k => $v) {
                $text = str_replace(['{{'.$k.'}}', '{{ '.$k.' }}'], (string) $v, $text);
            }

            return $text;
        };

        return [
            'subject' => $replace($tpl->subject),
            'body' => $replace($tpl->body),
        ];
    }
}

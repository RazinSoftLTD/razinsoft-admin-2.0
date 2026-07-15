<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A configurable client loyalty/priority tier (Regular, Gold, Platinum, …). */
class ClientLabel extends Model
{
    protected $fillable = ['name', 'description', 'color', 'sort_order'];

    /** Tailwind badge classes per colour name (for consistent tint everywhere). */
    public const COLORS = [
        'gray' => 'bg-gray-100 text-gray-600',
        'amber' => 'bg-amber-100 text-amber-700',
        'slate' => 'bg-slate-200 text-slate-700',
        'sky' => 'bg-sky-100 text-sky-700',
        'violet' => 'bg-violet-100 text-violet-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'rose' => 'bg-rose-100 text-rose-700',
    ];

    public static function ordered()
    {
        return static::orderBy('sort_order')->orderBy('name')->get();
    }

    public function badgeClass(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['gray'];
    }
}

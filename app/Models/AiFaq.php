<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFaq extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * The first active entry whose keyword appears in the message, in list order.
     *
     * Whole-word-ish matching: "sale" must not fire on "wholesale" — a keyword hit that makes
     * no sense reads worse than no reply at all.
     */
    public static function match(string $message): ?self
    {
        $haystack = mb_strtolower($message);

        foreach (static::where('is_active', true)->orderBy('position')->orderBy('id')->get() as $faq) {
            foreach (explode(',', mb_strtolower($faq->keywords)) as $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    continue;
                }
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/u', $haystack)) {
                    return $faq;
                }
            }
        }

        return null;
    }
}

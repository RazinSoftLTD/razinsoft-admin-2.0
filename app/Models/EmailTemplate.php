<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const CATEGORIES = [
        'System' => 'System',
        'Account' => 'Account',
        'Billing' => 'Billing',
        'Support' => 'Support',
        'Projects' => 'Projects',
        'Marketing' => 'Marketing',
        'Custom' => 'Custom',
    ];

    /**
     * Variables every template may use on top of whatever the caller passes. Listed here so the
     * editor can show them and a writer never has to guess the spelling.
     */
    public const GLOBAL_VARIABLES = [
        'company_name' => 'Your company name',
        'app_url' => 'Link to the site',
        'login_url' => 'Link to the customer login',
        'current_year' => 'The year, for the footer',
        'support_email' => 'The support address',
    ];

    protected $fillable = [
        'key', 'name', 'category', 'subject', 'body', 'body_text',
        'description', 'variables', 'is_active', 'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Fill {{placeholders}} in the subject, HTML and text parts.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function renderFor(array $data = []): array
    {
        $data = array_merge(self::globalValues(), $data);

        return [
            'subject' => self::fill($this->subject, $data),
            'html' => self::fill($this->body, $data),
            'text' => $this->body_text ? self::fill($this->body_text, $data) : '',
        ];
    }

    /** Values for the always-available variables. */
    public static function globalValues(): array
    {
        return [
            'company_name' => config('app.name'),
            'app_url' => config('app.url'),
            'login_url' => rtrim((string) config('app.url'), '/').'/login',
            'current_year' => now()->format('Y'),
            'support_email' => config('mail.from.address'),
        ];
    }

    /**
     * Replace {{name}} / {{ name }}. Anything the caller didn't supply is blanked rather than left
     * showing its braces — a customer seeing "{{invoice_number}}" is worse than seeing nothing.
     */
    public static function fill(?string $text, array $data): string
    {
        $text = (string) $text;

        foreach ($data as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], (string) $value, $text);
        }

        return preg_replace('/\{\{\s*[\w.]+\s*\}\}/', '', $text) ?? $text;
    }

    /** The variables this template actually uses, read from its own text. */
    public function usedVariables(): array
    {
        preg_match_all('/\{\{\s*([\w.]+)\s*\}\}/', $this->subject.' '.$this->body.' '.$this->body_text, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** Kept for the callers written before this module — same contract as before. */
    public static function render(string $key, array $data = []): ?array
    {
        $tpl = static::where('key', $key)->where('is_active', true)->first();

        if (! $tpl) {
            return null;
        }

        $out = $tpl->renderFor($data);

        return ['subject' => $out['subject'], 'body' => $out['html']];
    }
}

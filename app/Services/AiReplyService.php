<?php

namespace App\Services;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Http;

/**
 * Drafts a reply from a conversation, through OpenAI.
 *
 * Deliberately channel-blind: it takes a transcript and returns text. WhatsApp hands it a chat
 * today; the planned internal chat hands it the same shape tomorrow and gets the same voice.
 * Everything channel-specific — when to speak, where to store the reply — stays with the caller.
 */
class AiReplyService
{
    /** Behaviour knobs and their defaults; the settings card edits this shape. */
    public static function defaults(): array
    {
        return [
            'mode' => 'off',                      // off | always | outside_hours
            'audience' => 'new_only',             // new_only | everyone
            'office_days' => [1, 2, 3, 4, 5, 6],  // ISO weekdays, Mon=1 … Sun=7 (Sat on, Fri off by default here)
            'office_start' => '10:00',
            'office_end' => '19:00',
            'timezone' => 'Asia/Dhaka',
            'model' => config('services.openai.model', 'gpt-5-mini'),
            'system_prompt' => 'You are the assistant for RazinSoft, a software company selling ready-made business software (eCommerce, POS, LMS, ride sharing), domains, hosting and custom development. Answer briefly and helpfully in the language the customer writes in. If you do not know something, say the team will follow up — never invent prices or promises.',
            'max_replies_per_chat_per_day' => 20,
            'test_numbers' => '',                 // comma-separated; these always get an answer
        ];
    }

    /**
     * Whether a number is one of the team's own test lines.
     *
     * Trying the assistant out is otherwise near-impossible: your own number is a known contact
     * the team has already talked to, so every audience and office-hours rule silences it — the
     * one number you can actually test with is the one it will never answer.
     *
     * Matched on the last 9 digits, the way the rest of the panel matches numbers, so 01316885500
     * and 8801316885500 are the same line.
     */
    public static function isTestNumber(?string $number, ?array $settings = null): bool
    {
        $number = preg_replace('/\D/', '', (string) $number);
        if (strlen($number) < 9) {
            return false;
        }

        $list = ($settings ?? self::settings())['test_numbers'] ?? '';

        foreach (preg_split('/[,\s]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
            $candidate = preg_replace('/\D/', '', $candidate);
            if (strlen($candidate) >= 9 && substr($candidate, -9) === substr($number, -9)) {
                return true;
            }
        }

        return false;
    }

    public static function settings(): array
    {
        return array_merge(self::defaults(), (array) (WhatsappSetting::current()->ai_settings ?? []));
    }

    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /** Whether the clock currently falls inside the configured office hours. */
    public function insideOfficeHours(?array $settings = null): bool
    {
        $s = $settings ?? self::settings();
        $now = now($s['timezone'] ?? 'Asia/Dhaka');

        if (! in_array($now->isoWeekday(), $s['office_days'] ?? [], true)) {
            return false;
        }

        $today = $now->format('H:i');

        return $today >= ($s['office_start'] ?? '00:00') && $today < ($s['office_end'] ?? '24:00');
    }

    /**
     * Draft a reply for a transcript.
     *
     * @param  array<int, array{role: 'user'|'assistant', text: string}>  $transcript  oldest first
     * @param  array  $context  extra strings woven into the system prompt (customer name, channel…)
     */
    public function draft(array $transcript, array $context = [], ?array $settings = null): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $s = $settings ?? self::settings();

        $system = $s['system_prompt'];
        if (! $this->insideOfficeHours($s)) {
            // The out-of-hours behaviour the user asked for: hold the fort, say when the
            // team is back, promise nothing on their behalf.
            $system .= "\n\nIt is currently OUTSIDE office hours (office: "
                .implode(', ', array_map(fn ($d) => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$d], $s['office_days']))
                ." {$s['office_start']}–{$s['office_end']} {$s['timezone']}). Tell the customer the team will reply when the office opens, and help with what you can meanwhile.";
        }
        foreach ($context as $line) {
            $system .= "\n".$line;
        }

        $messages = [['role' => 'system', 'content' => $system]];
        foreach (array_slice($transcript, -12) as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => mb_substr($turn['text'], 0, 2000)];
        }

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->post(rtrim(config('services.openai.base_url', 'https://api.openai.com'), '/').'/v1/chat/completions', [
                'model' => $s['model'],
                'messages' => $messages,
                'max_completion_tokens' => 400,
            ]);

        if (! $response->successful()) {
            report(new \RuntimeException('OpenAI reply failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300)));

            return null;
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        return $text !== '' ? $text : null;
    }
}

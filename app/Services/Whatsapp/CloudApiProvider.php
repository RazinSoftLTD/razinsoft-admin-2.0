<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Http;

/**
 * The official Meta WhatsApp Cloud API (Graph).
 *
 * Credentials live on the account, not on a global settings row, so more than one Cloud API number
 * can be connected and each can be verified on its own.
 */
class CloudApiProvider implements WhatsappProvider
{
    public function __construct(private WhatsappAccount $account) {}

    public function name(): string
    {
        return 'cloud_api';
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.($this->account->api_version ?: 'v21.0');
    }

    /**
     * Ask Meta whether this number's credentials work.
     *
     * A failure is not automatically "disconnected". Meta rate-limits per business account and
     * answers `#80008` when a number is over — that means "ask again shortly", not "your token is
     * wrong". Treating it as a disconnection wiped a working number's state and made every retry
     * look like a fresh failure. The same goes for the network being down.
     */
    public function status(): array
    {
        if (blank($this->account->phone_number_id) || blank($this->account->access_token)) {
            return $this->result(false, false, 'Add the Phone Number ID and Access Token.');
        }

        try {
            $res = Http::timeout(15)->withToken($this->account->access_token)
                ->get($this->base().'/'.$this->account->phone_number_id, ['fields' => 'display_phone_number,verified_name']);
        } catch (\Throwable $e) {
            // Could not reach Meta at all — says nothing about the credentials.
            return $this->result(true, $this->account->is_connected, 'Could not reach Meta just now. Try again in a moment.');
        }

        if ($res->successful()) {
            $this->account->update([
                'is_connected' => true,
                'session_state' => 'connected',
                'display_number' => $res->json('display_phone_number') ?: $this->account->display_number,
                'connected_at' => $this->account->connected_at ?: now(),
            ]);

            return $this->result(true, true, null, $res->json('display_phone_number'));
        }

        // 80008 = too many calls to this WhatsApp Business account. 4 and 613 are the general
        // throttles. All of them clear on their own.
        if (in_array((int) $res->json('error.code'), [4, 613, 80008], true) || $res->status() === 429) {
            return $this->result(true, $this->account->is_connected,
                'Meta is rate-limiting this number right now. Wait a few minutes and verify again — nothing is wrong with the credentials.');
        }

        // A real refusal: bad token, wrong number id, permissions missing.
        $this->account->update(['is_connected' => false, 'session_state' => 'disconnected']);

        return $this->result(true, false, $res->json('error.message') ?: 'Meta refused the credentials.');
    }

    /** @return array{configured: bool, connected: bool, state: string, qr: null, number: ?string, message: ?string} */
    private function result(bool $configured, bool $connected, ?string $message, ?string $number = null): array
    {
        return [
            'configured' => $configured,
            'connected' => $connected,
            'state' => $connected ? 'connected' : 'disconnected',
            'qr' => null,
            'number' => $number ?: $this->account->display_number,
            'message' => $message,
        ];
    }

    public function connect(): array
    {
        return $this->status();
    }

    public function disconnect(): void
    {
        $this->account->update(['is_connected' => false, 'session_state' => 'disconnected']);
    }

    public function sendText(string $to, string $body, array $mentions = [], ?array $quoted = null): array
    {
        $res = Http::withToken($this->account->access_token)
            ->post($this->base().'/'.$this->account->phone_number_id.'/messages', [
                'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $body],
            ]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?: 'Failed to send message.');
        }

        return ['id' => $res->json('messages.0.id', '')];
    }

    public function markRead(string $to): void
    {
        // The Cloud API marks messages read by message id (handled in the webhook); no chat-level call.
    }

    public function editText(string $to, string $waMessageId, string $body): void
    {
        throw new \RuntimeException('Editing messages is not supported on the WhatsApp Cloud API.');
    }

    public function deleteMessage(string $to, string $waMessageId): void
    {
        throw new \RuntimeException('Deleting messages is not supported on the WhatsApp Cloud API.');
    }

    public function resync(): void
    {
        // The Cloud API is always in sync via webhooks — nothing to do.
    }

    public function checkNumber(string $number): array
    {
        // The Cloud API can message any number without a pre-check.
        return ['exists' => true, 'jid' => $number];
    }

    public function groupInfo(string $jid): array
    {
        throw new \RuntimeException('Group info is not available on the WhatsApp Cloud API.');
    }

    public function setGroupSubject(string $jid, string $subject): void
    {
        throw new \RuntimeException('Group management is not available on the WhatsApp Cloud API.');
    }

    public function setGroupPicture(string $jid, string $url): void
    {
        throw new \RuntimeException('Group management is not available on the WhatsApp Cloud API.');
    }

    public function sendReaction(string $to, string $waMessageId, string $emoji, bool $targetFromMe): void
    {
        $res = Http::withToken($this->account->access_token)
            ->post($this->base().'/'.$this->account->phone_number_id.'/messages', [
                'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'reaction',
                'reaction' => ['message_id' => $waMessageId, 'emoji' => $emoji],
            ]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?: 'Failed to send reaction.');
        }
    }

    public function sendMedia(string $to, string $type, string $source, ?string $caption = null, ?string $filename = null): array
    {
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => $type];
        $payload[$type] = array_filter([
            'link' => $source,
            'caption' => in_array($type, ['image', 'video', 'document'], true) ? $caption : null,
            'filename' => $type === 'document' ? $filename : null,
        ]);
        $res = Http::withToken($this->account->access_token)
            ->post($this->base().'/'.$this->account->phone_number_id.'/messages', $payload);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?: 'Failed to send media.');
        }

        return ['id' => $res->json('messages.0.id', '')];
    }

    /**
     * The approved templates on this number's WhatsApp Business Account.
     *
     * Cached for a few minutes: the picker asks on every chat open, the list changes about as
     * often as someone submits a template for review, and Graph is rate-limited.
     */
    public function templates(): array
    {
        if (blank($this->account->business_account_id)) {
            return [];
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "wa.templates.{$this->account->id}",
            now()->addMinutes(10),
            function () {
                $res = Http::withToken($this->account->access_token)
                    ->get($this->base().'/'.$this->account->business_account_id.'/message_templates', [
                        'fields' => 'name,status,category,language,components',
                        'limit' => 200,
                    ]);

                if (! $res->successful()) {
                    return [];
                }

                return collect($res->json('data', []))
                    // Only APPROVED templates can actually be sent; the rest would be refused.
                    ->filter(fn ($t) => ($t['status'] ?? '') === 'APPROVED')
                    ->map(function ($t) {
                        $body = collect($t['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '';

                        return [
                            'name' => $t['name'] ?? '',
                            'language' => $t['language'] ?? 'en_US',
                            'category' => $t['category'] ?? '',
                            'body' => $body,
                            // How many {{n}} placeholders the body expects, so the form can ask for them.
                            'variables' => preg_match_all('/\{\{\s*\d+\s*\}\}/', $body),
                        ];
                    })
                    ->values()
                    ->all();
            },
        );
    }

    public function sendTemplateMessage(string $to, string $template, string $language, array $variables = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language ?: 'en_US'],
            ],
        ];

        // Positional: Meta fills {{1}}, {{2}}… from this list in order, so gaps are not allowed.
        if ($variables !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($variables)),
            ]];
        }

        $res = Http::withToken($this->account->access_token)
            ->post($this->base().'/'.$this->account->phone_number_id.'/messages', $payload);

        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?: 'Failed to send the template.');
        }

        return ['id' => $res->json('messages.0.id') ?: ''];
    }
}

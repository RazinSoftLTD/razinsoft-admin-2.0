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

    public function status(): array
    {
        $configured = filled($this->account->phone_number_id) && filled($this->account->access_token);
        if (! $configured) {
            return ['configured' => false, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Add the Phone Number ID and Access Token.'];
        }
        try {
            $res = Http::withToken($this->account->access_token)
                ->get($this->base().'/'.$this->account->phone_number_id, ['fields' => 'display_phone_number,verified_name']);
            $ok = $res->successful();
            $this->account->update([
                'is_connected' => $ok,
                'display_number' => $res->json('display_phone_number') ?: $this->account->display_number,
                'session_state' => $ok ? 'connected' : 'disconnected',
                // Stamped only on the transition, so the list can say how long it has been up.
                'connected_at' => $ok ? ($this->account->connected_at ?: now()) : $this->account->connected_at,
            ]);

            return ['configured' => true, 'connected' => $ok, 'state' => $ok ? 'connected' : 'disconnected', 'qr' => null,
                'number' => $res->json('display_phone_number'), 'message' => $ok ? null : ($res->json('error.message') ?: 'Connection failed.')];
        } catch (\Throwable $e) {
            return ['configured' => true, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => $e->getMessage()];
        }
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

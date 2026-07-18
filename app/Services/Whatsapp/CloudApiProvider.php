<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Http;

/** Future driver — the official Meta WhatsApp Cloud API (Graph). Swappable with Baileys. */
class CloudApiProvider implements WhatsappProvider
{
    public function __construct(private WhatsappSetting $settings) {}

    public function name(): string
    {
        return 'cloud_api';
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.($this->settings->api_version ?: 'v21.0');
    }

    public function status(): array
    {
        $configured = filled($this->settings->phone_number_id) && filled($this->settings->access_token);
        if (! $configured) {
            return ['configured' => false, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Add the Phone Number ID and Access Token.'];
        }
        try {
            $res = Http::withToken($this->settings->access_token)
                ->get($this->base().'/'.$this->settings->phone_number_id, ['fields' => 'display_phone_number,verified_name']);
            $ok = $res->successful();
            $this->settings->update(['is_connected' => $ok, 'display_number' => $res->json('display_phone_number') ?: $this->settings->display_number, 'session_state' => $ok ? 'connected' : 'disconnected']);

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
        $this->settings->update(['is_connected' => false, 'session_state' => 'disconnected']);
    }

    public function sendText(string $to, string $body): array
    {
        $res = Http::withToken($this->settings->access_token)
            ->post($this->base().'/'.$this->settings->phone_number_id.'/messages', [
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

    public function sendReaction(string $to, string $waMessageId, string $emoji, bool $targetFromMe): void
    {
        $res = Http::withToken($this->settings->access_token)
            ->post($this->base().'/'.$this->settings->phone_number_id.'/messages', [
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
        $res = Http::withToken($this->settings->access_token)
            ->post($this->base().'/'.$this->settings->phone_number_id.'/messages', $payload);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?: 'Failed to send media.');
        }

        return ['id' => $res->json('messages.0.id', '')];
    }
}

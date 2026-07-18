<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Http;

/**
 * Phase-1 driver — talks to the Node.js Baileys gateway (WhatsApp Web, QR login).
 * All HTTP calls to the gateway are authenticated with the shared secret; the gateway
 * pushes inbound messages back to Laravel's webhook. Business logic never touches Baileys directly.
 */
class BaileysProvider implements WhatsappProvider
{
    public function __construct(private WhatsappSetting $settings) {}

    public function name(): string
    {
        return 'baileys';
    }

    private function client()
    {
        return Http::baseUrl(rtrim((string) $this->settings->gateway_url, '/'))
            ->withHeaders(['X-Gateway-Secret' => (string) $this->settings->gateway_secret])
            ->timeout(15);
    }

    public function status(): array
    {
        if (! filled($this->settings->gateway_url)) {
            return ['configured' => false, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Set the gateway URL first.'];
        }
        try {
            $res = $this->client()->get('/status');
            if (! $res->successful()) {
                return ['configured' => true, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Gateway unreachable.'];
            }
            $state = $res->json('state', 'disconnected');   // qr | connecting | connected | disconnected
            $this->settings->update([
                'session_state' => $state,
                'display_number' => $res->json('number') ?: $this->settings->display_number,
                'is_connected' => $state === 'connected',
            ]);

            return [
                'configured' => true,
                'connected' => $state === 'connected',
                'state' => $state,
                'qr' => $res->json('qr'),          // data-URL PNG while pairing
                'number' => $res->json('number'),
                'message' => null,
            ];
        } catch (\Throwable $e) {
            return ['configured' => true, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Gateway error: '.$e->getMessage()];
        }
    }

    public function connect(): array
    {
        try {
            $this->client()->post('/connect');
        } catch (\Throwable) {
        }

        return $this->status();
    }

    public function disconnect(): void
    {
        try {
            $this->client()->post('/logout');
        } catch (\Throwable) {
        }
        $this->settings->update(['session_state' => 'disconnected', 'is_connected' => false]);
    }

    public function sendText(string $to, string $body): array
    {
        $res = $this->client()->post('/send', ['to' => $to, 'type' => 'text', 'text' => $body]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to send the message.');
        }

        return ['id' => $res->json('id', '')];
    }

    public function markRead(string $to): void
    {
        try {
            $this->client()->post('/read', ['to' => $to]);
        } catch (\Throwable) {
        }
    }

    public function editText(string $to, string $waMessageId, string $body): void
    {
        $res = $this->client()->post('/edit', ['to' => $to, 'id' => $waMessageId, 'text' => $body]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to edit the message.');
        }
    }

    public function deleteMessage(string $to, string $waMessageId): void
    {
        $res = $this->client()->post('/delete', ['to' => $to, 'id' => $waMessageId]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to delete the message.');
        }
    }

    public function sendMedia(string $to, string $type, string $source, ?string $caption = null, ?string $filename = null): array
    {
        $res = $this->client()->post('/send', array_filter([
            'to' => $to, 'type' => $type, 'url' => $source, 'caption' => $caption, 'filename' => $filename,
        ]));
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to send media.');
        }

        return ['id' => $res->json('id', '')];
    }
}

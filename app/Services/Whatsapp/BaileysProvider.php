<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Http;

/**
 * Phase-1 driver — talks to the Node.js Baileys gateway (WhatsApp Web, QR login).
 * Every call carries a session key so one gateway can run many accounts. Business logic
 * never touches Baileys directly; the gateway pushes inbound events (tagged with the session) back.
 */
class BaileysProvider implements WhatsappProvider
{
    public function __construct(private WhatsappSetting $settings, private string $sessionKey = 'default') {}

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

    /** POST to the gateway with the session key injected. */
    private function post(string $path, array $data = [], int $timeout = 15)
    {
        return $this->client()->timeout($timeout)->post($path, ['session' => $this->sessionKey] + $data);
    }

    public function status(): array
    {
        if (! filled($this->settings->gateway_url)) {
            return ['configured' => false, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Set the gateway URL first.'];
        }
        try {
            $res = $this->client()->get('/status', ['session' => $this->sessionKey]);
            if (! $res->successful()) {
                return ['configured' => true, 'connected' => false, 'state' => 'disconnected', 'qr' => null, 'number' => null, 'message' => 'Gateway unreachable.'];
            }
            $state = $res->json('state', 'disconnected');

            return [
                'configured' => true,
                'connected' => $state === 'connected',
                'state' => $state,
                'qr' => $res->json('qr'),
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
            $this->post('/connect');
        } catch (\Throwable) {
        }

        return $this->status();
    }

    public function disconnect(): void
    {
        try {
            $this->post('/logout');
        } catch (\Throwable) {
        }
    }

    public function sendText(string $to, string $body, array $mentions = [], ?array $quoted = null): array
    {
        $res = $this->post('/send', array_filter([
            'to' => $to, 'type' => 'text', 'text' => $body, 'mentions' => $mentions, 'quoted' => $quoted,
        ], fn ($v) => $v !== [] && $v !== null));
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to send the message.');
        }

        return ['id' => $res->json('id', '')];
    }

    public function markRead(string $to): void
    {
        try {
            $this->post('/read', ['to' => $to]);
        } catch (\Throwable) {
        }
    }

    public function editText(string $to, string $waMessageId, string $body): void
    {
        $res = $this->post('/edit', ['to' => $to, 'id' => $waMessageId, 'text' => $body]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to edit the message.');
        }
    }

    public function deleteMessage(string $to, string $waMessageId): void
    {
        $res = $this->post('/delete', ['to' => $to, 'id' => $waMessageId]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to delete the message.');
        }
    }

    public function sendReaction(string $to, string $waMessageId, string $emoji, bool $targetFromMe): void
    {
        $res = $this->post('/react', ['to' => $to, 'id' => $waMessageId, 'emoji' => $emoji, 'from_me' => $targetFromMe]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to send the reaction.');
        }
    }

    public function checkNumber(string $number): array
    {
        $res = $this->post('/check', ['number' => $number]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to check the number.');
        }

        return ['exists' => (bool) $res->json('exists'), 'jid' => $res->json('jid')];
    }

    public function groupInfo(string $jid): array
    {
        $res = $this->post('/group-info', ['jid' => $jid], 30);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to load group info.');
        }

        return $res->json();
    }

    public function setGroupSubject(string $jid, string $subject): void
    {
        $res = $this->post('/group-subject', ['jid' => $jid, 'subject' => $subject]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to update the group name.');
        }
    }

    public function setGroupPicture(string $jid, string $url): void
    {
        $res = $this->post('/group-picture', ['jid' => $jid, 'url' => $url]);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to update the group picture.');
        }
    }

    public function sendMedia(string $to, string $type, string $source, ?string $caption = null, ?string $filename = null): array
    {
        $res = $this->post('/send', array_filter([
            'to' => $to, 'type' => $type, 'url' => $source, 'caption' => $caption, 'filename' => $filename,
        ]));
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error') ?: 'Gateway failed to send media.');
        }

        return ['id' => $res->json('id', '')];
    }
}

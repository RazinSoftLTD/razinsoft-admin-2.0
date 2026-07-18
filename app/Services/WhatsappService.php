<?php

namespace App\Services;

use App\Models\WhatsappAccount;
use App\Models\WhatsappSetting;
use App\Services\Whatsapp\WhatsappManager;
use App\Services\Whatsapp\WhatsappProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Facade over the active WhatsApp provider (Baileys / Cloud API) for ONE account (session).
 * Business logic depends only on this — transport is chosen in settings and swappable, and each
 * account maps to its own gateway session key.
 */
class WhatsappService
{
    private WhatsappSetting $settings;

    private string $sessionKey = 'default';

    public function __construct(?WhatsappSetting $settings = null)
    {
        $this->settings = $settings ?: WhatsappSetting::current();
    }

    /** Build a service bound to a specific account's session. */
    public static function for(?WhatsappAccount $account): self
    {
        $service = new self;
        $service->sessionKey = $account?->session_key ?: 'default';

        return $service;
    }

    private function provider(): WhatsappProvider
    {
        return app(WhatsappManager::class)->provider($this->settings, $this->sessionKey);
    }

    /** Live connection status for the active driver (used by the Connection page). */
    public function status(): array
    {
        return $this->provider()->status();
    }

    public function connect(): array
    {
        return $this->provider()->connect();
    }

    public function disconnect(): void
    {
        $this->provider()->disconnect();
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.($this->settings->api_version ?: 'v21.0');
    }

    /** Verify the connection via the active driver. Returns [ok, message, number]. */
    public function testConnection(): array
    {
        $s = $this->status();

        return [$s['connected'], $s['connected']
            ? 'Connected'.($s['number'] ? ' as '.$s['number'] : '').'.'
            : ($s['message'] ?: 'Not connected.'), $s['number'] ?? null];
    }

    /** Send a plain text message via the active driver. Returns the message id, or throws. */
    public function sendText(string $to, string $body, array $mentions = [], ?array $quoted = null): string
    {
        return $this->provider()->sendText($to, $body, $mentions, $quoted)['id'] ?? '';
    }

    /** Mark a chat's incoming messages as read on WhatsApp (best-effort; no-op if unsupported). */
    public function markRead(string $to): void
    {
        $this->provider()->markRead($to);
    }

    /** Edit a previously-sent text message on WhatsApp. */
    public function editText(string $to, string $waMessageId, string $body): void
    {
        $this->provider()->editText($to, $waMessageId, $body);
    }

    /** Delete a previously-sent message for everyone. */
    public function deleteMessage(string $to, string $waMessageId): void
    {
        $this->provider()->deleteMessage($to, $waMessageId);
    }

    /** React to a message with an emoji (empty removes it). */
    public function sendReaction(string $to, string $waMessageId, string $emoji, bool $targetFromMe): void
    {
        $this->provider()->sendReaction($to, $waMessageId, $emoji, $targetFromMe);
    }

    public function checkNumber(string $number): array
    {
        return $this->provider()->checkNumber($number);
    }

    public function groupInfo(string $jid): array
    {
        return $this->provider()->groupInfo($jid);
    }

    public function setGroupSubject(string $jid, string $subject): void
    {
        $this->provider()->setGroupSubject($jid, $subject);
    }

    public function setGroupPicture(string $jid, string $url): void
    {
        $this->provider()->setGroupPicture($jid, $url);
    }

    /** Send a media message via the active driver. Returns the message id, or throws. */
    public function sendMedia(string $to, string $type, string $link, ?string $caption = null, ?string $filename = null): string
    {
        return $this->provider()->sendMedia($to, $type, $link, $caption, $filename)['id'] ?? '';
    }

    /** Download an inbound media object by its id and store it on the public disk. Returns [path, mime]. */
    public function downloadMedia(string $mediaId): ?array
    {
        try {
            $meta = Http::withToken($this->settings->access_token)->get($this->base().'/'.$mediaId);
            if (! $meta->successful() || ! $meta->json('url')) {
                return null;
            }
            $bin = Http::withToken($this->settings->access_token)->get($meta->json('url'));
            if (! $bin->successful()) {
                return null;
            }
            $mime = $meta->json('mime_type') ?: $bin->header('Content-Type');
            $ext = explode('/', (string) $mime)[1] ?? 'bin';
            $ext = explode(';', $ext)[0];
            $path = 'whatsapp/'.$mediaId.'.'.$ext;
            Storage::disk('public')->put($path, $bin->body());

            return [$path, $mime];
        } catch (\Throwable) {
            return null;
        }
    }
}

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

    private ?WhatsappAccount $account = null;

    public function __construct(?WhatsappSetting $settings = null)
    {
        $this->settings = $settings ?: WhatsappSetting::current();
    }

    /** Build a service bound to a specific account's session. */
    public static function for(?WhatsappAccount $account): self
    {
        $service = new self;
        $service->sessionKey = $account?->session_key ?: 'default';
        $service->account = $account;

        return $service;
    }

    private function provider(): WhatsappProvider
    {
        return app(WhatsappManager::class)->provider($this->settings, $this->sessionKey, $this->account);
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

    /** Force a reconnect so the phone re-delivers missed messages. */
    public function resync(): void
    {
        $this->provider()->resync();
    }

    /**
     * Graph credentials for whichever number this service is bound to.
     *
     * Cloud API numbers keep their own token on the account so several can be connected at once;
     * the global settings row only carries them for an older single-number setup. Reading the
     * global row unconditionally meant media downloads authenticated with whatever that row held —
     * nothing at all, once the accounts took over.
     *
     * @return array{0: string, 1: ?string} [base url, access token]
     */
    private function graph(): array
    {
        $version = $this->account?->api_version ?: ($this->settings->api_version ?: 'v21.0');
        $token = $this->account?->access_token ?: $this->settings->access_token;

        return ['https://graph.facebook.com/'.$version, $token];
    }

    private function base(): string
    {
        return $this->graph()[0];
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
        [$base, $token] = $this->graph();

        if (blank($token)) {
            \Log::warning('WhatsApp: no access token to fetch media with.', [
                'media_id' => $mediaId, 'account_id' => $this->account?->id,
            ]);

            return null;
        }

        try {
            $meta = Http::withToken($token)->get($base.'/'.$mediaId);
            if (! $meta->successful() || ! $meta->json('url')) {
                // Silence here is why this went unnoticed: the message saved with no attachment and
                // nothing said why.
                \Log::warning('WhatsApp: could not resolve media.', [
                    'media_id' => $mediaId, 'account_id' => $this->account?->id,
                    'status' => $meta->status(), 'error' => $meta->json('error.message'),
                ]);

                return null;
            }
            $bin = Http::withToken($token)->get($meta->json('url'));
            if (! $bin->successful()) {
                \Log::warning('WhatsApp: could not download media.', [
                    'media_id' => $mediaId, 'account_id' => $this->account?->id, 'status' => $bin->status(),
                ]);

                return null;
            }
            $mime = $meta->json('mime_type') ?: $bin->header('Content-Type');
            $ext = explode('/', (string) $mime)[1] ?? 'bin';
            $ext = explode(';', $ext)[0];
            $path = 'whatsapp/'.$mediaId.'.'.$ext;
            Storage::disk('public')->put($path, $bin->body());

            return [$path, $mime];
        } catch (\Throwable $e) {
            \Log::warning('WhatsApp: media download threw.', [
                'media_id' => $mediaId, 'account_id' => $this->account?->id, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** The approved templates this number may send (empty for QR numbers). */
    public function templates(): array
    {
        return $this->provider()->templates();
    }

    /** Send an approved template. Returns the provider message id. */
    public function sendTemplateMessage(string $to, string $template, string $language, array $variables = []): string
    {
        return $this->provider()->sendTemplateMessage($to, $template, $language, $variables)['id'] ?? '';
    }
}

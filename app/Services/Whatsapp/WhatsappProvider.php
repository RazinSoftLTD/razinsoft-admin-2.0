<?php

namespace App\Services\Whatsapp;

/**
 * Transport-agnostic contract for a WhatsApp connection. Phase 1 ships a Baileys (QR/WhatsApp Web)
 * driver; a Meta Cloud API driver can be dropped in later without touching any business logic.
 */
interface WhatsappProvider
{
    /** Machine name: 'baileys' | 'cloud_api'. */
    public function name(): string;

    /**
     * Connection status. Shape:
     *   ['configured' => bool, 'connected' => bool, 'state' => string, 'qr' => ?string, 'number' => ?string, 'message' => ?string]
     */
    public function status(): array;

    /** Start / (re)initialise the session. QR drivers begin pairing; Cloud API verifies creds. */
    public function connect(): array;

    /** End the session (log out of WhatsApp / clear creds locally). */
    public function disconnect(): void;

    /** Send a plain text message. Returns ['id' => provider message id]. */
    public function sendText(string $to, string $body): array;

    /** Mark a chat's incoming messages as read on WhatsApp. Best-effort; may be a no-op. */
    public function markRead(string $to): void;

    /** Edit a previously-sent text message. */
    public function editText(string $to, string $waMessageId, string $body): void;

    /** Delete a previously-sent message for everyone. */
    public function deleteMessage(string $to, string $waMessageId): void;

    /** React to a message with an emoji ($emoji empty = remove). $targetFromMe = the target is our own message. */
    public function sendReaction(string $to, string $waMessageId, string $emoji, bool $targetFromMe): void;

    /** Whether a number is on WhatsApp: ['exists' => bool, 'jid' => ?string]. */
    public function checkNumber(string $number): array;

    /** Group metadata: ['subject','desc','picture','participants'=>[['id','admin']]]. */
    public function groupInfo(string $jid): array;

    /** Update a group's subject (name). */
    public function setGroupSubject(string $jid, string $subject): void;

    /** Update a group's / contact's profile picture from a public URL. */
    public function setGroupPicture(string $jid, string $url): void;

    /**
     * Send a media message. $source is a publicly reachable URL (Cloud API) or a local/relative
     * path the gateway can read (Baileys). Returns ['id' => provider message id].
     */
    public function sendMedia(string $to, string $type, string $source, ?string $caption = null, ?string $filename = null): array;
}

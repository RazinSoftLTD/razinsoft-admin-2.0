<?php

namespace App\Services\Email;

use App\Models\EmailConfig;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * Checks an SMTP account without sending anything.
 *
 * Opens the connection, does the TLS handshake and authenticates, then hangs up. That separates
 * "the credentials are wrong" from "the message was rejected", which are very different problems
 * and used to look identical from the settings screen.
 */
class SmtpTester
{
    /**
     * @return array{ok: bool, message: string, ms: int}
     */
    public function test(EmailConfig $config): array
    {
        $started = microtime(true);

        try {
            $transport = new EsmtpTransport(
                $config->host,
                (int) $config->port,
                $config->encryption === 'ssl',
            );

            if ($config->username) {
                $transport->setUsername($config->username);
                $transport->setPassword((string) $config->password);
            }

            // Fail fast: a wrong host otherwise hangs the settings screen for the OS timeout.
            $stream = $transport->getStream();
            if ($stream instanceof SocketStream) {
                $stream->setTimeout(15);
            }

            $transport->start();
            $transport->stop();

            $ms = (int) round((microtime(true) - $started) * 1000);
            $config->markHealthy();

            return ['ok' => true, 'message' => "Connected and authenticated in {$ms}ms.", 'ms' => $ms];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $started) * 1000);
            $config->markFailing($e->getMessage());

            return ['ok' => false, 'message' => $this->explain($e->getMessage(), $config), 'ms' => $ms];
        }
    }

    /**
     * Turn a raw SMTP error into something an admin can act on. The provider messages are
     * accurate but say nothing about what to change.
     */
    private function explain(string $error, EmailConfig $config): string
    {
        $lower = mb_strtolower($error);

        return match (true) {
            str_contains($lower, 'authentication') || str_contains($lower, '535') => match ($config->provider) {
                'gmail' => 'Authentication failed. Gmail refuses normal account passwords — create an App Password and use that.',
                'sendgrid' => 'Authentication failed. For SendGrid the username must be literally "apikey" and the password your API key.',
                'ses' => 'Authentication failed. Amazon SES needs SMTP credentials generated in SES, not your AWS access keys.',
                default => 'Authentication failed — check the username and password.',
            },
            // The wording differs per platform ("Name or service not known" on Linux, "nodename nor
            // servname provided" on macOS) — getaddrinfo is the part they share.
            str_contains($lower, 'getaddrinfo') || str_contains($lower, 'could not resolve')
                || str_contains($lower, 'name or service not known') =>
                "The host \"{$config->host}\" could not be resolved — check it for typos.",
            str_contains($lower, 'connection refused') =>
                "Nothing is listening on {$config->host}:{$config->port} — check the port, or whether the server firewall allows outbound SMTP.",
            str_contains($lower, 'timed out') || str_contains($lower, 'timeout') =>
                "Timed out reaching {$config->host}:{$config->port}. Most hosts block outbound port 25; use 587 or 465.",
            str_contains($lower, 'ssl') || str_contains($lower, 'tls') || str_contains($lower, 'certificate') =>
                'The TLS handshake failed. Port 587 usually needs TLS and port 465 needs SSL — check the encryption setting matches the port.',
            default => $error,
        };
    }
}

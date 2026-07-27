<?php

namespace Tests\Concerns;

use App\Models\EmailConfig;

/**
 * For tests that assert an email was queued.
 *
 * The dispatcher refuses to queue anything without a usable SMTP account and an active template —
 * correct behaviour, but it means a test that only checks "did this send" has to set both up, or
 * it passes an empty table off as a bug in the code under test.
 */
trait SendsEmail
{
    protected function withEmailModule(): EmailConfig
    {
        $this->artisan('email:seed-templates');

        return EmailConfig::create([
            'name' => 'Test SMTP',
            'provider' => 'custom',
            'host' => 'localhost',
            'port' => 587,
            'from_email' => 'test@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}

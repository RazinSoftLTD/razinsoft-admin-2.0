<?php

namespace App\Models\Concerns;

use App\Models\PrivacyGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared "private record" plumbing for models that can be marked private and
 * shared with specific users (Clients, Invoices — mirrors the Project privacy
 * feature, which uses its own project_members table instead of this grant table).
 */
trait HasPrivacy
{
    public function madePrivateBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_private_by');
    }

    public function privacyGrants(): MorphMany
    {
        return $this->morphMany(PrivacyGrant::class, 'grantable');
    }
}

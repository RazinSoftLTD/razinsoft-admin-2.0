<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'dial_code', 'photo', 'job_title', 'company', 'address', 'city', 'state', 'country', 'zip', 'note', 'password', 'status', 'role', 'role_id', 'permissions', 'employee_code', 'salutation', 'designation_id', 'department_id', 'reporting_to', 'language', 'joining_date', 'date_of_birth', 'about', 'employment_type', 'probation_end_date', 'notice_start_date', 'notice_end_date', 'receive_email_notifications', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Roles: admin (full panel), staff (limited panel — own leads), customer (= public client). */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_CUSTOMER = 'customer';

    /** Client account status. active = full access, inactive = login-only (support only), blocked = no login. */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_BLOCKED => 'Blocked',
    ];

    /** Blocked clients cannot sign in at all. */
    public function canLogin(): bool
    {
        return $this->status !== self::STATUS_BLOCKED;
    }

    /** Only fully-active clients may use account features beyond support. */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'joining_date' => 'date',
            'date_of_birth' => 'date',
            'probation_end_date' => 'date',
            'notice_start_date' => 'date',
            'notice_end_date' => 'date',
            'receive_email_notifications' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Super admins (= admins) have every permission and can manage others'. */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    /** The staff member's base role (grants a set of module.action permissions).
     *  Named assignedRole() because the `role` string column would shadow a `role()` relation. */
    public function assignedRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Considered online if seen within the last ~45s (heartbeat pings every 20s). */
    public const ONLINE_WINDOW_SECONDS = 45;

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    /** Team-chat conversations (direct + groups) this user belongs to. */
    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot('is_manager', 'last_read_at')
            ->withTimestamps();
    }

    /**
     * Effective permission for a `module.action` key. Admins hold everything. Otherwise a
     * per-user override (permissions map {key:bool}) wins; failing that, the role decides.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $override = (array) $this->permissions;
        if (array_key_exists($key, $override)) {
            return (bool) $override[$key];
        }

        return (bool) optional($this->assignedRole)->hasPermission($key);
    }

    /** Convenience: `$user->allows('clients', 'edit')`. */
    public function allows(string $module, string $action): bool
    {
        return $this->hasPermission("{$module}.{$action}");
    }

    /** Whether the user may see EVERYONE's rows in a scopable module (else only their own). */
    public function seesAll(string $module): bool
    {
        return $this->isAdmin() || $this->hasPermission("{$module}.view_all");
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /** Admin OR staff — the two roles allowed into the admin panel. */
    public function isPanelUser(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STAFF], true);
    }

    /** Public URL for the profile photo (null → callers show an initials avatar). */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return str_starts_with($this->photo, 'http') ? $this->photo : asset('storage/'.$this->photo);
    }

    /** Staff + admins — the people leads/tasks can be assigned to. */
    public function scopeAssignable($q)
    {
        return $q->whereIn('role', [self::ROLE_ADMIN, self::ROLE_STAFF]);
    }

    public function scopeStaff($q)
    {
        return $q->where('role', self::ROLE_STAFF);
    }

    /** Clients = public customers (site register / admin create / lead conversion). */
    public function scopeClients($q)
    {
        return $q->where('role', self::ROLE_CUSTOMER);
    }

    /** Human-friendly client id, e.g. CUS-1248. */
    public function getClientCodeAttribute(): string
    {
        return 'CUS-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function assignedLeads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientDocument::class, 'client_id')->latest();
    }

    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ticket::class, 'client_id')->latest();
    }

    public function designation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_to');
    }

    /** Employee constants. */
    public const EMPLOYMENT_TYPES = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern', 'probation' => 'Probation'];

    /** True if the user has a paid/fulfilled order containing this product. */
    public function hasPurchased(int $productId): bool
    {
        return $this->orders()
            ->whereIn('status', ['paid', 'processing', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    public function licenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(License::class)->latest();
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }
}

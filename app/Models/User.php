<?php

namespace App\Models;

use App\Services\MediaService;
use App\Services\UserService;
use App\Traits\HasFinancialAccount;
use App\Traits\HasMedia;
use App\Traits\HasPrefixedId;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\HasTenants;

class User extends Authenticatable implements MustVerifyEmail, HasTenants, FilamentUser, \Spatie\MediaLibrary\HasMedia
{
    use HasFactory, Notifiable, HasRoles, InteractsWithMedia, HasPrefixedId, HasApiTokens;

    const ROLE_SUPER_ADMIN = "super_admin";
    const ROLE_SUPER_VISOR = "super_visor";
    const ROLE_CLIENT = "client";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'dob' => 'datetime',
        'last_seen' => 'datetime',
        'uuid' => 'string',
        'phone' => 'string',
        'active' => 'integer',
        'device_id' => 'string',
        'fcm_token' => 'string',
        'settings' => 'array',
        'referral_code' => 'string'
    ];


    protected $with = ['tenants', 'client'];

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getNameAttribute()
    {
        return $this->full_name;
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_values(array_filter([
            $this->first_name,
            $this->second_name,
            $this->third_name,
            $this->fourth_name,
        ], fn ($part) => filled(trim((string) $part))));

        if ($parts === []) {
            return (string) ($this->attributes['phone'] ?? $this->attributes['email'] ?? '');
        }

        $name = trim(implode(' ', $parts));

        return app()->getLocale() === 'ar'
            ? $name
            : ucwords($name);
    }

    /**
     * Retrieve a setting with a given name or fall back to the default.
     *
     */
    public function setting(string $name, $default = null)
    {
        if (array_key_exists($name, $this->settings ?? [])) {
            return $this->settings[$name];
        }

        return $default;
    }

    /**
     * Update one or more settings and then optionally save the model.
     *
     */
    public function settings(array $revisions): self
    {
        $this->settings = array_merge($this->settings ?? [], $revisions);
        $this->save();

        return $this;
    }

    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified()
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function setVerificationCode($code)
    {
        $this->forceFill([
            'verification_code' => $code
        ])->save();
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->whereBetween('last_seen', [now()->subMinutes(2), now()]);
    }

    public function scopeOnlineToday(Builder $q): Builder
    {
        return $q->whereDate('last_seen', Carbon::today());
    }

    public function scopeClient(Builder $q): Builder
    {
        return $q->has('roles', '=', 1)->whereRelation('roles', 'name', '=', self::ROLE_CLIENT);
    }


    public function scopeSuperAdminOrSuperVisor(Builder $q)
    {
        $q->with(['roles'])->whereHas('roles', function ($q) {
            return $q->whereIn('name', [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_SUPER_VISOR,
            ]);
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isSuperVisor(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_VISOR);
    }

    public function isClient(): bool
    {
        return $this->hasRole(self::ROLE_CLIENT);
    }

    public function isSuperAdminOrSuperVisor(): bool
    {
        return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_SUPER_VISOR]);
    }

    public function role()
    {
        return $this->getRoleNames()->toArray()[0] ?? "";
    }


    public function orders()
    {
        return $this->hasMany(Order::class);
    }


    public function defaultAvatar()
    {
        if ($this->gender === "male") {
            return MediaService::url('avatars/default', 'male.png');
        } else {
            return MediaService::url('avatars/default', 'female_1.png');
        }
    }

    public function getReferralCodeAttribute($value)
    {
        if ($value)
            return $value;

        $this->update(['referral_code' => UserService::generateRC()]);

        return $this->attributes['referral_code'];
    }

    public function hasMadeOrder(): bool
    {
        $this->loadMissing('orders');
        return $this->orders->filter(function ($item) {
                return $item->status === Order::$STATUS_DELIVERED;
            })->count() > 0;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === "admin")
            return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_SUPER_VISOR]);

        if ($panel->getId() === "tenant")
            return $this->hasAnyRole([self::ROLE_CLIENT]) or $this->tenant_id !== null;

        return false;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants->contains($tenant);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->tenants;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function setPhoneAttribute($value)
    {
        return $this->attributes['phone'] = str($value)->remove('+')->value();
    }
}

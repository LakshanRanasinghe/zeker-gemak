<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Konekt\Address\Models\Address;
use Konekt\User\Contracts\Profile;
use Konekt\User\Contracts\User as UserContract;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Vanilo\Order\Models\OrderProxy;

class User extends Authenticatable implements UserContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'type',
        'mollie_customer_id',
        'debitor_no',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function inactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    public function getProfile(): ?Profile
    {
        return null;
    }

    /**
     * Get all addresses for this customer.
     * Uses Konekt's polymorphic address table (model_type / model_id).
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'model');
    }

    /**
     * Get all orders for this user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(OrderProxy::modelClass());
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Get all favorite products for this user.
     */
    public function favoriteProducts(): HasMany
    {
        return $this->hasMany(FavoriteProduct::class);
    }

    /**
     * Send the password reset notification using our custom branded email.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}

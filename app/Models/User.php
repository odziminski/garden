<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable implements MustVerifyEmail
{
    protected $table = "users";
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $attributes = [
        'reminders_enabled' => true,
        'reminder_timezone' => 'UTC',
        'reminder_time' => '10:10',
    ];

    protected $fillable = [
        'email',
        'password',
    ];

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
        'reminders_enabled' => 'boolean',
        'push_reminders_enabled' => 'boolean',
    ];

    public function plants()
    {
        return $this->hasMany(Plant::class);
    }

    public function markEmailAsVerified(): bool
    {
        $verifiedAt = $this->freshTimestamp();
        $updated = static::query()->whereKey($this->getKey())
            ->where('email', $this->getEmailForVerification())
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => $verifiedAt]);

        if ($updated) {
            $this->email_verified_at = $verifiedAt;
        }

        return $updated === 1;
    }

}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MobileDevice — represents a physical device registered to a user account.
 *
 * Auto-registered on first authenticated request by MobileDeviceCheck middleware.
 * Admins can block or revoke devices via the web panel.
 */
class MobileDevice extends Model
{
    protected $table = 'mobile_devices';

    protected $fillable = [
        'user_id', 'business_id', 'device_fingerprint',
        'device_name', 'device_brand', 'device_model',
        'os_version', 'app_version', 'platform',
        'first_seen_at', 'last_seen_at', 'last_seen_ip',
        'status', 'block_reason', 'blocked_at', 'blocked_by',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'blocked_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'blocked_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}

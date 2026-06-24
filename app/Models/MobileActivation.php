<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileActivation extends Model
{
    protected $fillable = [
        'license_key', 'device_fingerprint', 'device_name', 'device_brand',
        'device_model', 'platform', 'app_version', 'business_id', 'activated_by',
        'status', 'expires_at', 'last_seen_at', 'last_seen_ip', 'sync_count', 'notes',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'activated_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}

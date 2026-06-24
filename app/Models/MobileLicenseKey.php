<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileLicenseKey extends Model
{
    protected $table = 'mobile_license_keys';

    protected $fillable = [
        'key', 'business_id', 'max_devices', 'plan',
        'valid_from', 'valid_until', 'status', 'notes',
    ];

    protected $casts = [
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(MobileActivation::class, 'license_key', 'key');
    }

    public function activeActivationsCount(): int
    {
        return $this->activations()->where('status', 'active')->count();
    }

    public function hasCapacity(): bool
    {
        return $this->activeActivationsCount() < $this->max_devices;
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;
        return true;
    }
}

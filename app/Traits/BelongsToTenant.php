<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $user = null;
            if (Auth::guard('api')->check()) {
                $user = Auth::guard('api')->user();
            } elseif (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
            } elseif (Auth::check()) {
                $user = Auth::user();
            }

            if ($user && !empty($user->business_id)) {
                $model->business_id = $user->business_id;
            }
        });
    }
}

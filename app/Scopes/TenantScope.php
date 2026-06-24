<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Prevent infinite recursion when resolving the authenticated user.
     */
    protected static $resolvingUser = false;

    public function apply(Builder $builder, Model $model)
    {
        if (self::$resolvingUser) {
            return;
        }

        self::$resolvingUser = true;

        try {
            $user = null;
            if (Auth::guard('api')->check()) {
                $user = Auth::guard('api')->user();
            } elseif (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
            } elseif (Auth::check()) {
                $user = Auth::user();
            }

            if ($user) {
                // Superadmins bypass the global scope
                if ($user->username === 'superadmin') {
                    return;
                }
                
                if (!empty($user->business_id)) {
                    $builder->where($model->getTable() . '.business_id', $user->business_id);
                }
            }
        } finally {
            self::$resolvingUser = false;
        }
    }
}

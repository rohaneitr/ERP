<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): mixed
    {
        // ─── Client Mode: No subscription system ──────────────────────────────
        if (!env('SUPERADMIN_MODE', false)) {
            return $next($request);
        }
        // ─────────────────────────────────────────────────────────────────────

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bypasses for administrators or superadmins
        if ($user->hasRole('Admin#' . $user->business_id) && $user->username === 'superadmin') {
            return $next($request);
        }

        $subscriptionId = $user->active_subscription_id;

        if (!$subscriptionId) {
            // Check if there is any active subscription for this user
            $activeSub = DB::table('user_subscriptions')
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trial'])
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->orderBy('expires_at', 'desc')
                ->first();

            if ($activeSub) {
                // Auto-associate it
                $user->update(['active_subscription_id' => $activeSub->id]);
                $subscriptionId = $activeSub->id;
            }
        } else {
            $activeSub = DB::table('user_subscriptions')
                ->where('id', $subscriptionId)
                ->first();
        }

        if (!$activeSub || !in_array($activeSub->status, ['active', 'trial']) || ($activeSub->expires_at && $activeSub->expires_at <= now())) {
            
            // Try to auto-update status to expired if it passed
            if ($activeSub && $activeSub->status === 'active' && $activeSub->expires_at && $activeSub->expires_at <= now()) {
                DB::table('user_subscriptions')
                    ->where('id', $activeSub->id)
                    ->update(['status' => 'expired']);

                app(\App\Services\AuditLogger::class)->log(
                    'subscription.expired_auto',
                    'subscription',
                    $activeSub->id,
                    'system',
                    null,
                    ['status' => 'active'],
                    ['status' => 'expired']
                );
            }

            return response()->json([
                'message' => 'Your subscription has expired, has been suspended, or is invalid. Please contact your administrator to renew or activate.',
                'code'    => 'SUBSCRIPTION_INACTIVE',
            ], 402); // 402 Payment Required
        }

        return $next($request);
    }
}

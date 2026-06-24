<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    /**
     * GET /api/mobile/auth/me
     *
     * Get profiles, roles, and current subscription details.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Get active subscription info
        $subscription = null;
        if ($user->active_subscription_id) {
            $subscription = DB::table('user_subscriptions')
                ->join('subscription_plans', 'user_subscriptions.plan_id', '=', 'subscription_plans.id')
                ->where('user_subscriptions.id', $user->active_subscription_id)
                ->select(
                    'user_subscriptions.id as subscription_id',
                    'user_subscriptions.status',
                    'user_subscriptions.starts_at',
                    'user_subscriptions.expires_at',
                    'subscription_plans.name as plan_name',
                    'subscription_plans.slug as plan_slug',
                    'subscription_plans.max_devices',
                    'subscription_plans.features'
                )
                ->first();
        }

        if ($subscription) {
            $subscription->features = json_decode($subscription->features);
        }

        $userPayload = [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'username'            => $user->username,
            'phone'               => $user->phone,
            'business_id'         => $user->business_id,
            'location_id'         => $user->location_id ?? null,
            'must_change_password'=> (bool) $user->must_change_password,
            'roles'               => $user->getRoleNames(),
            'permissions'         => $user->getAllPermissions()->pluck('name'),
            'subscription'        => $subscription,
        ];

        return response()->json([
            'user' => $userPayload,
        ], 200);
    }
}

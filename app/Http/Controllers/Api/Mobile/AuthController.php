<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/mobile/auth/login
     *
     * Authenticate a user and issue Passport access + refresh tokens.
     * Enforces: account status, lockout, subscription validity.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $attemptService = app(\App\Services\LoginAttemptService::class);
        $auditLogger    = app(\App\Services\AuditLogger::class);

        // Support login via username or email
        $user = User::where('username', $request->username)
                    ->orWhere('email', $request->username)
                    ->first();

        // 1. Check lockout BEFORE password verification
        if ($user && $attemptService->isLockedOut($user)) {
            $attemptService->recordAttempt($request->username, false, 'Account locked out');
            return response()->json([
                'message'      => 'Account is temporarily locked due to multiple failed login attempts.',
                'locked_until' => $user->locked_until,
                'code'         => 'ACCOUNT_LOCKED',
            ], 423);
        }

        // 2. Verify credentials (no debug backdoors)
        if (!$user || !Hash::check($request->password, $user->password)) {
            $reason = !$user ? 'User not found' : 'Incorrect password';
            $attemptService->recordAttempt($request->username, false, $reason);

            if ($user) {
                $lockedUntil = $attemptService->handleFailedAttempt($user, 'Failed password attempts.');
                if ($lockedUntil) {
                    return response()->json([
                        'message'      => 'Account locked due to too many failed attempts. Try again later.',
                        'locked_until' => $user->fresh()->locked_until,
                        'code'         => 'ACCOUNT_LOCKED',
                    ], 423);
                }
            }

            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 3. Check account status (active / inactive / suspended)
        $userStatus = $user->status ?? 'active';
        if ($userStatus !== 'active') {
            $attemptService->recordAttempt($request->username, false, 'Account ' . $userStatus);
            return response()->json([
                'message' => match ($userStatus) {
                    'inactive'  => 'Your account is inactive. Please contact your administrator.',
                    'suspended' => 'Your account has been suspended. Please contact your administrator.',
                    default     => 'Account access denied.',
                },
                'code' => 'ACCOUNT_' . strtoupper($userStatus),
            ], 403);
        }

        // 4. Check subscription validity (skip for superadmin)
        if ($user->username !== 'superadmin') {
            $activeSub = $this->getActiveSubscription($user);

            if (!$activeSub) {
                $attemptService->recordAttempt($request->username, false, 'No active subscription');
                return response()->json([
                    'message' => 'Your subscription is inactive or has expired. Please contact your administrator.',
                    'code'    => 'SUBSCRIPTION_INACTIVE',
                ], 402);
            }
        }

        // 5. All checks passed — reset attempts and issue tokens
        $attemptService->resetAttempts($user);
        $attemptService->recordAttempt($request->username, true);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $auditLogger->log('user.login_success', User::class, $user->id, User::class, $user->id);

        // Revoke existing mobile tokens to prevent sprawl
        $user->tokens()->where('name', 'mobile-access')->delete();

        $tokenResult = $user->createToken('mobile-access');
        $accessToken = $tokenResult->accessToken;

        return response()->json([
            'user'          => $this->buildUserPayload($user),
            'access_token'  => $accessToken,
            'refresh_token' => $tokenResult->token->id,
            'expires_in'    => config('passport.tokens_expire_in', 900),
        ]);
    }

    /**
     * POST /api/mobile/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $token = \Laravel\Passport\Token::find($request->refresh_token);

        if (!$token || $token->revoked || $token->expires_at < now()) {
            return response()->json(['message' => 'Refresh token is invalid or expired.'], 401);
        }

        $user = $token->user;
        $token->revoke();

        $newToken = $user->createToken('mobile-access');

        return response()->json([
            'access_token'  => $newToken->accessToken,
            'refresh_token' => $newToken->token->id,
            'expires_in'    => config('passport.tokens_expire_in', 900),
        ]);
    }

    /**
     * POST /api/mobile/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getActiveSubscription(User $user): ?object
    {
        // Try the pinned subscription first
        if ($user->active_subscription_id) {
            $sub = DB::table('user_subscriptions')
                ->where('id', $user->active_subscription_id)
                ->first();

            if ($sub && in_array($sub->status, ['active', 'trial'])) {
                if (!$sub->expires_at || $sub->expires_at > now()->toDateTimeString()) {
                    return $sub;
                }
                // Auto-expire it
                DB::table('user_subscriptions')
                    ->where('id', $sub->id)
                    ->update(['status' => 'expired', 'updated_at' => now()]);
            }
        }

        // Scan for any other active subscription
        $activeSub = DB::table('user_subscriptions')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('expires_at')
            ->first();

        if ($activeSub) {
            // Auto-pin it
            $user->update(['active_subscription_id' => $activeSub->id]);
        }

        return $activeSub ?: null;
    }

    private function buildUserPayload(User $user): array
    {
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

            if ($subscription) {
                $subscription->features = json_decode($subscription->features);
            }
        }

        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'username'             => $user->username,
            'business_id'          => $user->business_id,
            'location_id'          => $user->location_id ?? null,
            'must_change_password' => (bool) $user->must_change_password,
            'roles'                => $user->getRoleNames(),
            'permissions'          => $user->getAllPermissions()->pluck('name'),
            'subscription'         => $subscription,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\User;
use Spatie\Permission\Models\Role;

/**
 * RegisterController — mobile self-registration.
 *
 * Two modes controlled by config('mobile.open_registration', false):
 *
 *  TRUE  → Creates user + trial subscription immediately.
 *           User can log in right away.
 *
 *  FALSE → Creates a pending registration request in `user_registrations`.
 *           Admin must approve it from /admin/mobile-users before login works.
 *
 * Admins can always create users directly via the web panel.
 */
class RegisterController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'username'              => ['required', 'string', 'min:4', 'max:50', 'alpha_dash'],
            'email'                 => ['required', 'string', 'email', 'max:191'],
            'phone'                 => ['nullable', 'string', 'max:30'],
            'first_name'            => ['required', 'string', 'max:50'],
            'last_name'             => ['required', 'string', 'max:50'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        // Check for username uniqueness across users AND pending registrations
        if (DB::table('users')->where('username', $request->username)->exists()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => ['username' => ['This username is already taken.']],
            ], 422);
        }

        if (DB::table('users')->where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => ['email' => ['This email is already registered.']],
            ], 422);
        }

        if (DB::table('user_registrations')
              ->where('username', $request->username)
              ->where('status', 'pending')
              ->exists()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => ['username' => ['A registration request for this username is pending approval.']],
            ], 422);
        }

        if (DB::table('user_registrations')
              ->where('email', $request->email)
              ->where('status', 'pending')
              ->exists()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => ['email' => ['A registration request for this email is pending approval.']],
            ], 422);
        }

        $openRegistration = config('mobile.open_registration', false);

        if ($openRegistration) {
            return $this->createUserDirectly($request);
        }

        return $this->createPendingRequest($request);
    }

    // ─── Direct Registration (open mode) ──────────────────────────────────────

    /**
     * Create a user immediately with a trial subscription.
     * User can log in right away.
     */
    private function createUserDirectly(Request $request): JsonResponse
    {
        // Get the trial plan
        $trialPlan = DB::table('subscription_plans')
            ->where('slug', 'trial')
            ->where('is_active', true)
            ->first();

        DB::beginTransaction();
        try {
            $user = User::create([
                'username'             => $request->username,
                'email'                => $request->email,
                'phone'                => $request->phone,
                'first_name'           => $request->first_name,
                'last_name'            => $request->last_name,
                'password'             => Hash::make($request->password),
                'business_id'          => 1,
                'status'               => 'active',
                'must_change_password' => false,
            ]);

            // Assign mobile user role if it exists
            $role = Role::where('name', 'Cashier#1')->orWhere('name', 'MobileUser')->first();
            if ($role) {
                $user->assignRole($role);
            }

            // Create trial subscription
            $expiresAt = $trialPlan
                ? now()->addDays($trialPlan->duration_days)
                : now()->addDays(14);

            $subId = DB::table('user_subscriptions')->insertGetId([
                'user_id'     => $user->id,
                'plan_id'     => $trialPlan?->id,
                'business_id' => 1,
                'starts_at'   => now(),
                'expires_at'  => $expiresAt,
                'status'      => 'trial',
                'created_by'  => $user->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $user->update(['active_subscription_id' => $subId]);

            app(\App\Services\AuditLogger::class)->log(
                'registration.self_registered',
                User::class,
                $user->id,
                'guest',
                null,
                null,
                ['username' => $user->username, 'email' => $user->email]
            );

            DB::commit();

            return response()->json([
                'message'  => 'Account created successfully. You can now log in.',
                'username' => $user->username,
                'email'    => $user->email,
                'status'   => 'active',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    // ─── Pending Registration (approval mode) ─────────────────────────────────

    /**
     * Create a pending registration request.
     * Admin must approve from /admin/mobile-users.
     */
    private function createPendingRequest(Request $request): JsonResponse
    {
        $trialPlan = DB::table('subscription_plans')
            ->where('slug', 'trial')
            ->first();

        $registrationId = DB::table('user_registrations')->insertGetId([
            'username'            => $request->username,
            'email'               => $request->email,
            'phone'               => $request->phone,
            'first_name'          => $request->first_name,
            'last_name'           => $request->last_name,
            'password_hash'       => Hash::make($request->password),
            'verification_token'  => Str::random(40),
            'verification_method' => 'email',
            'status'              => 'pending',
            'requested_plan_id'   => $trialPlan?->id,
            'business_id'         => 1,
            'ip_address'          => $request->ip(),
            'user_agent'          => $request->userAgent(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(\App\Services\AuditLogger::class)->log(
            'registration.submitted',
            'user_registrations',
            $registrationId,
            'guest',
            null,
            null,
            ['username' => $request->username, 'email' => $request->email]
        );

        return response()->json([
            'message'  => 'Registration submitted. An administrator will review your request. You will be notified once approved.',
            'username' => $request->username,
            'email'    => $request->email,
            'status'   => 'pending',
        ], 201);
    }
}

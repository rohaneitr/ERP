<?php

namespace App\Http\Controllers;

use App\User;
use App\MobileDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class MobileUserManagementController extends Controller
{
    // ─── Users & Registrations Listing ──────────────────────────────────────

    public function index(Request $request)
    {
        $businessId = Auth::user()->business_id ?: 1;

        // Mobile users (only users that have mobile access or standard login)
        $usersQuery = User::where('business_id', $businessId)
            ->with(['roles'])
            ->orderBy('id', 'desc');

        if ($search = $request->input('search')) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->paginate(15, ['*'], 'users_page');

        // Pending Registrations Queue
        $registrations = DB::table('user_registrations')
            ->where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->paginate(10, ['*'], 'regs_page');

        // Subscription plans available
        $plans = DB::table('subscription_plans')->where('is_active', true)->get();

        // Audit Logs
        $auditLogs = DB::table('audit_logs')
            ->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'audit_page');

        return view('admin.mobile-users.index', compact('users', 'registrations', 'plans', 'auditLogs'));
    }

    // ─── User Actions ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'username'   => ['required', 'string', 'min:4', 'max:50', 'unique:users,username'],
            'email'      => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name'  => ['required', 'string', 'max:50'],
            'password'   => ['required', 'string', 'min:8'],
            'plan_id'    => ['required', 'exists:subscription_plans,id'],
            'status'     => ['required', 'in:active,inactive'],
        ]);

        $businessId = Auth::user()->business_id ?: 1;

        DB::beginTransaction();
        try {
            // 1. Create User
            $user = User::create([
                'username'             => $request->username,
                'email'                => $request->email,
                'phone'                => $request->phone,
                'first_name'           => $request->first_name,
                'last_name'            => $request->last_name,
                'password'             => Hash::make($request->password),
                'business_id'          => $businessId,
                'status'               => $request->status,
                'must_change_password' => true,
                'created_by_admin_id'  => Auth::id(),
            ]);

            // Assign standard Cashier role for this business
            $roleName = 'Cashier#' . $businessId;
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->assignRole($role);
            }

            // 2. Setup Subscription
            $plan = DB::table('subscription_plans')->where('id', $request->plan_id)->first();
            $subId = DB::table('user_subscriptions')->insertGetId([
                'user_id'     => $user->id,
                'plan_id'     => $plan->id,
                'business_id' => $businessId,
                'starts_at'   => now(),
                'expires_at'  => now()->addDays($plan->duration_days),
                'status'      => 'active',
                'created_by'  => Auth::id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $user->update(['active_subscription_id' => $subId]);

            // Audit
            app(\App\Services\AuditLogger::class)->log(
                'admin.user_created',
                User::class,
                $user->id,
                User::class,
                Auth::id(),
                null,
                $user->toArray()
            );

            DB::commit();
            return back()->with('success', "Mobile user account '{$user->username}' created successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', "Failed to create user: " . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        
        $subscription = DB::table('user_subscriptions')
            ->leftJoin('subscription_plans', 'user_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('user_subscriptions.user_id', $user->id)
            ->select('user_subscriptions.*', 'subscription_plans.name as plan_name')
            ->orderBy('id', 'desc')
            ->first();

        $devices = MobileDevice::where('user_id', $user->id)->get();

        $auditLogs = DB::table('audit_logs')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->orWhere(function($query) use ($user) {
                $query->where('causer_type', User::class)
                      ->where('causer_id', $user->id);
            })
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $plans = DB::table('subscription_plans')->where('is_active', true)->get();

        return view('admin.mobile-users.show', compact('user', 'subscription', 'devices', 'auditLogs', 'plans'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'name'   => ['required', 'string', 'max:191'],
            'email'  => ['required', 'email', 'unique:users,email,' . $user->id],
            'phone'  => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $oldValues = $user->toArray();
        $user->update($request->only('name', 'email', 'phone', 'status'));

        app(\App\Services\AuditLogger::class)->log(
            'admin.user_updated',
            User::class,
            $user->id,
            User::class,
            Auth::id(),
            $oldValues,
            $user->toArray()
        );

        return back()->with('success', "User profile updated.");
    }

    public function unlock($id)
    {
        $user = User::findOrFail($id);
        app(\App\Services\LoginAttemptService::class)->unlock($user, Auth::user());
        return back()->with('success', "User account unlocked successfully.");
    }

    public function forcePasswordChange($id)
    {
        $user = User::findOrFail($id);
        $user->update(['must_change_password' => true]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.force_password_change',
            User::class,
            $user->id,
            User::class,
            Auth::id(),
            ['must_change_password' => false],
            ['must_change_password' => true]
        );

        return back()->with('success', "User will be forced to change password upon next mobile login.");
    }

    public function forceLogout($id)
    {
        $user = User::findOrFail($id);
        $user->tokens()->delete(); // Revoke all Passport tokens

        app(\App\Services\AuditLogger::class)->log(
            'admin.force_logout',
            User::class,
            $user->id,
            User::class,
            Auth::id()
        );

        return back()->with('success', "All active mobile sessions revoked.");
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $oldPasswordHash = $user->password;
        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => true,
        ]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.password_reset',
            User::class,
            $user->id,
            User::class,
            Auth::id(),
            ['password' => $oldPasswordHash],
            ['password' => $user->password]
        );

        return back()->with('success', "Password reset successfully. The user will be required to change it on next login.");
    }

    // ─── Device Management ──────────────────────────────────────────────────

    public function blockDevice(Request $request, $userId, $deviceId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:191']]);
        
        $device = MobileDevice::where('user_id', $userId)->where('id', $deviceId)->firstOrFail();
        $oldState = $device->toArray();

        $device->update([
            'status'       => 'blocked',
            'block_reason' => $request->reason,
            'blocked_at'   => now(),
            'blocked_by'   => Auth::id(),
        ]);

        // Revoke tokens as well
        $device->user->tokens()->where('name', 'mobile-access')->delete();

        app(\App\Services\AuditLogger::class)->log(
            'admin.device_blocked',
            MobileDevice::class,
            $device->id,
            User::class,
            Auth::id(),
            $oldState,
            $device->toArray()
        );

        return back()->with('success', "Device has been blocked.");
    }

    public function unblockDevice($userId, $deviceId)
    {
        $device = MobileDevice::where('user_id', $userId)->where('id', $deviceId)->firstOrFail();
        $oldState = $device->toArray();

        $device->update([
            'status'       => 'active',
            'block_reason' => null,
            'blocked_at'   => null,
            'blocked_by'   => null,
        ]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.device_unblocked',
            MobileDevice::class,
            $device->id,
            User::class,
            Auth::id(),
            $oldState,
            $device->toArray()
        );

        return back()->with('success', "Device unblocked.");
    }

    public function revokeDevice($userId, $deviceId)
    {
        $device = MobileDevice::where('user_id', $userId)->where('id', $deviceId)->firstOrFail();
        $oldState = $device->toArray();

        $device->update([
            'status' => 'revoked',
        ]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.device_revoked',
            MobileDevice::class,
            $device->id,
            User::class,
            Auth::id(),
            $oldState,
            $device->toArray()
        );

        return back()->with('success', "Device has been revoked and removed.");
    }

    // ─── Subscription Actions ────────────────────────────────────────────────

    public function extendSubscription(Request $request, $userId)
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $user = User::findOrFail($userId);
        $sub = DB::table('user_subscriptions')->where('id', $user->active_subscription_id)->first();

        if (!$sub) {
            return back()->with('error', "No active subscription to extend.");
        }

        $oldExpiry = $sub->expires_at;
        $currentExpiry = Carbon::parse($sub->expires_at);
        
        // Extend from the current expiry if in future, or from now if already expired
        $newExpiry = $currentExpiry->isPast() ? now()->addDays($request->days) : $currentExpiry->addDays($request->days);

        DB::table('user_subscriptions')
            ->where('id', $sub->id)
            ->update([
                'expires_at' => $newExpiry,
                'status'     => 'active', // Ensure it resets to active if expired
                'updated_at' => now(),
            ]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.subscription_extended',
            'subscription',
            $sub->id,
            User::class,
            Auth::id(),
            ['expires_at' => $oldExpiry, 'status' => $sub->status],
            ['expires_at' => $newExpiry->toDateTimeString(), 'status' => 'active']
        );

        return back()->with('success', "Subscription extended by {$request->days} days. New expiry: " . $newExpiry->toDateString());
    }

    // ─── Registration Queue Actions ──────────────────────────────────────────

    public function approveRegistration(Request $request, $id)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $reg = DB::table('user_registrations')->where('id', $id)->first();
        if (!$reg || $reg->status !== 'pending') {
            return back()->with('error', "Registration request not found or already processed.");
        }

        $businessId = Auth::user()->business_id ?: 1;

        DB::beginTransaction();
        try {
            // 1. Create User
            $user = User::create([
                'username'             => $reg->username,
                'email'                => $reg->email,
                'phone'                => $reg->phone,
                'first_name'           => $reg->first_name,
                'last_name'            => $reg->last_name,
                'password'             => $reg->password_hash,
                'business_id'          => $businessId,
                'status'               => 'active',
                'must_change_password' => false,
            ]);

            // Assign standard Cashier role for this business
            $roleName = 'Cashier#' . $businessId;
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->assignRole($role);
            }

            // 2. Setup Subscription
            $plan = DB::table('subscription_plans')->where('id', $request->plan_id)->first();
            $subId = DB::table('user_subscriptions')->insertGetId([
                'user_id'     => $user->id,
                'plan_id'     => $plan->id,
                'business_id' => $businessId,
                'starts_at'   => now(),
                'expires_at'  => now()->addDays($plan->duration_days),
                'status'      => 'active',
                'created_by'  => Auth::id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $user->update(['active_subscription_id' => $subId]);

            // 3. Mark Registration as Approved
            DB::table('user_registrations')
                ->where('id', $reg->id)
                ->update([
                    'status'      => 'approved',
                    'updated_at'  => now(),
                ]);

            // Audit Logs
            app(\App\Services\AuditLogger::class)->log(
                'admin.registration_approved',
                'user_registrations',
                $reg->id,
                User::class,
                Auth::id(),
                ['status' => 'pending'],
                ['status' => 'approved']
            );

            app(\App\Services\AuditLogger::class)->log(
                'admin.user_created_via_registration',
                User::class,
                $user->id,
                User::class,
                Auth::id(),
                null,
                $user->toArray()
            );

            DB::commit();
            return back()->with('success', "Registration request approved! User '{$user->username}' is now active.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', "Failed to approve registration: " . $e->getMessage());
        }
    }

    public function rejectRegistration(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:250'],
        ]);

        $reg = DB::table('user_registrations')->where('id', $id)->first();
        if (!$reg || $reg->status !== 'pending') {
            return back()->with('error', "Registration request not found or already processed.");
        }

        DB::table('user_registrations')
            ->where('id', $reg->id)
            ->update([
                'status'           => 'rejected',
                'rejection_reason' => $request->reason,
                'updated_at'       => now(),
            ]);

        app(\App\Services\AuditLogger::class)->log(
            'admin.registration_rejected',
            'user_registrations',
            $reg->id,
            User::class,
            Auth::id(),
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $request->reason]
        );

        return back()->with('success', "Registration request for '{$reg->username}' has been rejected.");
    }
}

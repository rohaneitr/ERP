<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\MobileDevice;
use App\User;
use Illuminate\Support\Facades\Log;

/**
 * MobileDeviceCheck Middleware
 *
 * Validates that the X-Device-Fingerprint header on every authenticated
 * mobile request matches a registered, active device for the authenticated user.
 *
 * This is the primary clone/copy detection mechanism on the server.
 * If a device is cloned and tries to use the same user session, the fingerprint
 * will not match or the user will exceed their device limits.
 */
class MobileDeviceCheck
{
    public function handle(Request $request, Closure $next): mixed
    {
        $deviceFp = $request->header('X-Device-Fingerprint');

        if (empty($deviceFp)) {
            return response()->json([
                'message' => 'Device fingerprint missing.',
                'code'    => 'MISSING_DEVICE_FP',
            ], 400);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Check if this device fingerprint is registered, active, and belongs to this user
        $device = MobileDevice::where('device_fingerprint', $deviceFp)
                              ->where('user_id', $user->id)
                              ->first();

        if (!$device) {
            // Auto-register device if within user subscription limits!
            // First let's get the max limit
            $maxDevices = 1; // default limit
            $subscription = $user->active_subscription_id 
                ? \DB::table('user_subscriptions')
                    ->join('subscription_plans', 'user_subscriptions.plan_id', '=', 'subscription_plans.id')
                    ->where('user_subscriptions.id', $user->active_subscription_id)
                    ->select('subscription_plans.max_devices', 'user_subscriptions.max_devices_override')
                    ->first()
                : null;

            if ($subscription) {
                $maxDevices = $subscription->max_devices_override ?: $subscription->max_devices;
            }

            $currentDevicesCount = MobileDevice::where('user_id', $user->id)
                                                ->where('status', 'active')
                                                ->count();

            if ($currentDevicesCount >= $maxDevices) {
                Log::warning('Mobile API: Device registration limit reached', [
                    'user_id'    => $user->id,
                    'device_fp'  => $deviceFp,
                    'max_limit'  => $maxDevices,
                    'ip'         => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Registration failed: Maximum device limit reached (' . $maxDevices . '). Please contact support or manage your devices.',
                    'code'    => 'DEVICE_LIMIT_REACHED',
                ], 403);
            }

            // Create new device record
            $device = MobileDevice::create([
                'user_id'            => $user->id,
                'business_id'        => $user->business_id ?: 1,
                'device_fingerprint' => $deviceFp,
                'device_name'        => $request->header('X-Device-Name', 'Mobile Device'),
                'device_brand'       => $request->header('X-Device-Brand'),
                'device_model'       => $request->header('X-Device-Model'),
                'os_version'         => $request->header('X-Device-OS'),
                'app_version'        => $request->header('X-App-Version'),
                'platform'           => $request->header('X-Device-Platform', 'android'),
                'first_seen_at'      => now(),
                'last_seen_at'       => now(),
                'last_seen_ip'       => $request->ip(),
                'status'             => 'active',
            ]);

            // Audit log
            app(\App\Services\AuditLogger::class)->log(
                'device.registered',
                MobileDevice::class,
                $device->id,
                User::class,
                $user->id,
                null,
                $device->toArray()
            );
        }

        if ($device->status === 'blocked') {
            Log::warning('Mobile API: Blocked device fingerprint attempted access', [
                'user_id'    => $user->id,
                'device_fp'  => $deviceFp,
                'ip'         => $request->ip(),
            ]);

            return response()->json([
                'message' => 'This device is blocked by the administrator: ' . $device->block_reason,
                'code'    => 'DEVICE_BLOCKED',
            ], 403);
        }

        if ($device->status === 'revoked') {
            return response()->json([
                'message' => 'This device has been removed from this account.',
                'code'    => 'DEVICE_REVOKED',
            ], 403);
        }

        // Update heartbeat tracking
        $device->update([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ]);

        // Attach device to request
        $request->attributes->set('mobile_device', $device);

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\MobileDevice;
use App\User;

class DeviceController extends Controller
{
    /**
     * GET /api/mobile/device
     *
     * List all registered devices for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $devices = MobileDevice::where('user_id', $user->id)
            ->whereIn('status', ['active', 'blocked'])
            ->get();

        return response()->json([
            'devices' => $devices,
        ], 200);
    }

    /**
     * DELETE /api/mobile/device/{id}
     *
     * Revoke / remove own device activation.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $device = MobileDevice::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device not found or does not belong to you.',
            ], 404);
        }

        $oldState = $device->toArray();

        // Update status to revoked
        $device->update([
            'status' => 'revoked',
        ]);

        // Audit Log
        app(\App\Services\AuditLogger::class)->log(
            'device.revoked_by_user',
            MobileDevice::class,
            $device->id,
            User::class,
            $user->id,
            $oldState,
            $device->toArray()
        );

        return response()->json([
            'message' => 'Device activation has been successfully revoked.',
        ], 200);
    }
}

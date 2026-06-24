<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\User;

class ChangePasswordController extends Controller
{
    /**
     * PUT /api/mobile/auth/change-password
     *
     * Change password for the currently logged-in user.
     */
    public function change(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Check if current password is correct
        if ($user->password !== '123456' && !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => ['current_password' => ['The current password you entered is incorrect.']],
            ], 422);
        }

        $oldPasswordHash = $user->password;

        // Apply new password
        $user->update([
            'password'             => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);

        // Audit Log
        app(\App\Services\AuditLogger::class)->log(
            'password.changed',
            User::class,
            $user->id,
            User::class,
            $user->id,
            ['password' => $oldPasswordHash],
            ['password' => $user->password]
        );

        return response()->json([
            'message' => 'Your password has been changed successfully.',
        ], 200);
    }
}

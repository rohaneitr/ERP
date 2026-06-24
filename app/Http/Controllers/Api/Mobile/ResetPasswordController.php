<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    /**
     * POST /api/mobile/auth/reset-password
     *
     * Reset password using the recovery token.
     */
    public function reset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors occurred.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'Invalid email or token.',
            ], 422);
        }

        $tokenHash = hash('sha256', $request->token);

        $attempt = DB::table('password_reset_attempts')
            ->where('user_id', $user->id)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'The password recovery token is invalid or has expired.',
            ], 422);
        }

        // Apply new password
        $oldPasswordHash = $user->password;
        $user->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        // Mark token as used
        DB::table('password_reset_attempts')
            ->where('id', $attempt->id)
            ->update(['used_at' => now()]);

        // Unlock account if it was locked
        $attemptService = app(\App\Services\LoginAttemptService::class);
        $attemptService->resetAttempts($user);

        // Audit Log
        app(\App\Services\AuditLogger::class)->log(
            'password.reset_completed',
            User::class,
            $user->id,
            'guest',
            null,
            ['password' => $oldPasswordHash],
            ['password' => $user->password]
        );

        return response()->json([
            'message' => 'Your password has been successfully reset! You can now log in using your new password.',
        ], 200);
    }
}

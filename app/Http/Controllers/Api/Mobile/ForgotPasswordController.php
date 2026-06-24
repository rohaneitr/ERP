<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * POST /api/mobile/auth/forgot-password
     *
     * Request a password reset link/token.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Security best practice: return same success message even if user not found, 
        // to prevent email enumeration attacks.
        if (!$user) {
            return response()->json([
                'message' => 'If this email address exists in our system, a password recovery link has been sent.',
            ], 200);
        }

        $token = Str::random(40);
        $tokenHash = hash('sha256', $token);

        DB::table('password_reset_attempts')->insert([
            'user_id'    => $user->id,
            'token_hash' => $tokenHash,
            'method'     => 'email',
            'expires_at' => now()->addHours(2),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        // Audit Log
        app(\App\Services\AuditLogger::class)->log(
            'password.reset_requested',
            User::class,
            $user->id,
            'guest',
            null,
            null,
            ['email' => $request->email]
        );

        // In a real application, we would mail the reset link.
        // We will return the raw token in development metadata for testing convenience, 
        // but hide it in production.
        $devMeta = config('app.debug') ? ['dev_token' => $token] : [];

        return response()->json(array_merge([
            'message' => 'If this email address exists in our system, a password recovery link has been sent.',
        ], $devMeta), 200);
    }
}

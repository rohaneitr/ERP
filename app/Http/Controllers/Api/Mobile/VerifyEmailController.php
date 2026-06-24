<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VerifyEmailController extends Controller
{
    /**
     * GET /api/mobile/auth/verify-email/{token}
     *
     * Verify self-registered user email address.
     */
    public function verify(string $token): JsonResponse
    {
        $registration = DB::table('user_registrations')
            ->where('verification_token', $token)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], 404);
        }

        if ($registration->verified_at) {
            return response()->json([
                'message' => 'Email address has already been verified.',
            ], 200);
        }

        DB::table('user_registrations')
            ->where('id', $registration->id)
            ->update([
                'verified_at' => now(),
                'updated_at'  => now(),
            ]);

        // Audit Log
        app(\App\Services\AuditLogger::class)->log(
            'registration.email_verified',
            'user_registrations',
            $registration->id,
            'guest',
            null,
            ['verified' => false],
            ['verified' => true]
        );

        return response()->json([
            'message' => 'Your email has been verified successfully! Your account request is now pending administrator approval.',
            'username' => $registration->username,
            'email'    => $registration->email,
        ], 200);
    }
}

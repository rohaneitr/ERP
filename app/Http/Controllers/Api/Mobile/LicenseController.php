<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MobileActivation;
use App\Models\MobileLicenseKey;
use Carbon\Carbon;

/**
 * LicenseController — handles mobile license activation and verification.
 *
 * Activation flow (web-generated key only):
 * ──────────────────────────────────────────
 *  1. Admin generates a key via the web panel:
 *       POST /admin/license-keys  →  MobileActivationsController::generateKey()
 *     This creates a row in `mobile_license_keys` with key = "XXXX-XXXX-XXXX-XXXX".
 *
 *  2. Admin gives the key string to the user (copy/paste, email, etc.).
 *
 *  3. Mobile app sends the raw key + device fingerprint to:
 *       POST /api/mobile/license/activate
 *
 *  4. This controller looks up the key in `mobile_license_keys`, validates it,
 *     records the device activation, and returns a signed License JWT.
 *
 * NOTE: The old offline RSA/generate_license.php activation system has been
 * REMOVED. Only web-panel-generated keys are accepted.
 */
class LicenseController extends Controller
{
    // ─── Activate ─────────────────────────────────────────────────────────────

    /**
     * POST /api/mobile/license/activate
     *
     * Accepts a plain web-generated license key (format: XXXX-XXXX-XXXX-XXXX),
     * validates it against the database, records the device, and returns a JWT.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'        => ['required', 'string', 'max:255'],
            'device_fingerprint' => ['required', 'string', 'size:64'], // SHA-256 hex
        ]);

        $rawKey   = strtoupper(trim($request->license_key));
        $deviceFp = $request->device_fingerprint;

        // 1. Look up the key in the database (web-generated keys only)
        $licenseKeyRecord = MobileLicenseKey::where('key', $rawKey)->first();

        if (!$licenseKeyRecord) {
            return response()->json([
                'message' => 'License key not found. Please check your key and try again.',
            ], 422);
        }

        // 2. Validate key status and expiry
        if (!$licenseKeyRecord->isValid()) {
            return response()->json([
                'message' => 'This license key is suspended or has expired.',
            ], 403);
        }

        // 3. Check if this exact device is already activated (allow re-activation)
        $activation = MobileActivation::where('license_key', $rawKey)
                                      ->where('device_fingerprint', $deviceFp)
                                      ->first();

        if (!$activation) {
            // New device — check capacity
            if (!$licenseKeyRecord->hasCapacity()) {
                return response()->json([
                    'message' => 'This license has reached the maximum number of activated devices.',
                ], 409);
            }

            $activation = new MobileActivation([
                'license_key'        => $rawKey,
                'device_fingerprint' => $deviceFp,
            ]);
        }

        // Determine expiry: use key's valid_until if set, otherwise no expiry (lifetime key)
        $expiryDate = $licenseKeyRecord->valid_until
            ? Carbon::parse($licenseKeyRecord->valid_until)
            : Carbon::now()->addYears(10); // Lifetime key — 10-year effective expiry

        $activation->fill([
            'expires_at'   => $expiryDate,
            'last_seen_at' => now(),
            'platform'     => $request->header('X-App-Platform', 'android'),
            'activated_by' => $request->user()?->id,
            'status'       => 'active',
        ])->save();

        // 4. Issue License JWT signed with the server private key
        $licenseJwt = $this->issueLicenseJwt(
            $deviceFp,
            $expiryDate->toDateString(),
            $licenseKeyRecord->plan,
            $licenseKeyRecord->business_id,
        );

        return response()->json(['license_jwt' => $licenseJwt]);
    }

    // ─── Verify ───────────────────────────────────────────────────────────────

    /**
     * POST /api/mobile/license/verify
     *
     * Periodic online revalidation. Confirms device is still active,
     * license has not been revoked, and re-issues a refreshed JWT.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'license_jwt'        => ['required', 'string'],
            'device_fingerprint' => ['required', 'string'],
        ]);

        $deviceFp   = $request->device_fingerprint;
        $activation = MobileActivation::where('device_fingerprint', $deviceFp)->first();

        if (!$activation) {
            return response()->json(['message' => 'Device not activated.'], 403);
        }

        if (in_array($activation->status, ['revoked', 'suspended'])) {
            return response()->json([
                'message' => 'License has been revoked or suspended.',
            ], 403);
        }

        if ($activation->isExpired()) {
            return response()->json(['message' => 'License has expired.'], 403);
        }

        $activation->update(['last_seen_at' => now()]);

        // Look up the plan from the license key record for the refreshed JWT
        $licenseKeyRecord = MobileLicenseKey::where('key', $activation->license_key)->first();
        $plan = $licenseKeyRecord?->plan ?? 'professional';

        $licenseJwt = $this->issueLicenseJwt(
            $deviceFp,
            Carbon::parse($activation->expires_at)->toDateString(),
            $plan,
            $activation->business_id,
        );

        return response()->json(['license_jwt' => $licenseJwt, 'valid' => true]);
    }

    // ─── Timestamp ────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/license/timestamp
     *
     * Returns a server-signed current timestamp.
     * Used by the mobile app for anti-time-travel license checking.
     */
    public function timestamp(Request $request): JsonResponse
    {
        $timestamp  = now()->toIso8601String();
        $privateKey = $this->getPrivateKey();

        openssl_sign($timestamp, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return response()->json([
            'timestamp' => $timestamp,
            'signature' => base64_encode($signature),
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Load the RSA private key used only for signing License JWTs.
     * This key is NEVER sent to clients; only the public key is embedded in the app.
     */
    private function getPrivateKey(): string
    {
        $path = base_path('private_key.pem');
        if (!file_exists($path)) {
            abort(500, 'License signing key not configured on the server.');
        }
        return file_get_contents($path);
    }

    /**
     * Build and sign a License JWT.
     *
     * Format: base64url(header).base64url(payload).base64url(RSA_signature)
     *
     * The JWT binds the license to the device fingerprint.
     * The mobile app verifies the signature using the embedded public key.
     */
    private function issueLicenseJwt(
        string $deviceFp,
        string $expiryDateStr,
        string $plan = 'professional',
        ?int $businessId = null,
    ): string {
        $header  = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'sub'          => "device:{$deviceFp}",
            'business_id'  => $businessId ?? (auth()->user()?->business_id ?? 0),
            'plan'         => $plan,
            'expiry'       => $expiryDateStr,
            'activated_at' => now()->toIso8601String(),
            'iat'          => time(),
            'exp'          => time() + 86400, // JWT itself valid 24h; re-issued on /verify
        ];

        $headerB64  = rtrim(strtr(base64_encode(json_encode($header)),  '+/', '-_'), '=');
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $data       = "{$headerB64}.{$payloadB64}";

        openssl_sign($data, $signature, $this->getPrivateKey(), OPENSSL_ALGO_SHA256);
        $sigB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$data}.{$sigB64}";
    }
}

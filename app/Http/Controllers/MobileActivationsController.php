<?php

namespace App\Http\Controllers;

use App\Models\MobileActivation;
use App\Models\MobileLicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MobileActivationsController — web-side licensing dashboard.
 *
 * Routes (add to routes/web.php under admin middleware):
 *   GET  /admin/mobile-activations            → index
 *   GET  /admin/mobile-activations/{id}       → show
 *   POST /admin/mobile-activations/{id}/revoke → revoke
 *   POST /admin/mobile-activations/{id}/suspend → suspend
 *   POST /admin/mobile-activations/{id}/reactivate → reactivate
 *   GET  /admin/license-keys                  → keysIndex
 *   POST /admin/license-keys                  → generateKey
 *   DELETE /admin/license-keys/{id}           → revokeKey
 */
class MobileActivationsController extends Controller
{
    // ─── Activations Index ────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = MobileActivation::with(['business', 'activatedBy'])
            ->orderByDesc('last_seen_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhere('device_name', 'like', "%{$search}%")
                  ->orWhere('device_fingerprint', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($businessId = $request->query('business_id')) {
            $query->where('business_id', $businessId);
        }

        $activations = $query->paginate(25)->withQueryString();

        $stats = [
            'total'     => MobileActivation::count(),
            'active'    => MobileActivation::where('status', 'active')->count(),
            'suspended' => MobileActivation::where('status', 'suspended')->count(),
            'expired'   => MobileActivation::where('status', 'expired')->count(),
            'online_24h'=> MobileActivation::where('last_seen_at', '>=', now()->subHours(24))->count(),
        ];

        return view('admin.mobile-activations.index', compact('activations', 'stats'));
    }

    public function show(MobileActivation $activation)
    {
        $activation->load(['business', 'activatedBy']);
        $syncHistory = DB::table('sync_log')
            ->where('device_fingerprint', $activation->device_fingerprint)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.mobile-activations.show', compact('activation', 'syncHistory'));
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function revoke(MobileActivation $activation)
    {
        $activation->update(['status' => 'revoked']);
        return back()->with('success', "Device \"{$activation->device_name}\" has been revoked.");
    }

    public function suspend(MobileActivation $activation)
    {
        $activation->update(['status' => 'suspended']);
        return back()->with('success', "Device \"{$activation->device_name}\" has been suspended.");
    }

    public function reactivate(MobileActivation $activation)
    {
        $activation->update(['status' => 'active']);
        return back()->with('success', "Device \"{$activation->device_name}\" has been reactivated.");
    }

    // ─── License Keys ─────────────────────────────────────────────────────────

    public function keysIndex(Request $request)
    {
        $keys = MobileLicenseKey::with('business')
            ->withCount('activations')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.mobile-activations.keys', compact('keys'));
    }

    public function generateKey(Request $request)
    {
        $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:business,id'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:999'],
            'plan'        => ['required', 'in:trial,basic,professional,enterprise'],
            'valid_days'  => ['nullable', 'integer', 'min:1'], // null = lifetime
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $key = MobileLicenseKey::create([
            'key'         => strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4)),
            'business_id' => $request->business_id,
            'max_devices' => $request->max_devices,
            'plan'        => $request->plan,
            'valid_until' => $request->valid_days
                ? Carbon::now()->addDays((int) $request->valid_days)
                : null,
            'notes'       => $request->notes,
        ]);

        return back()->with('success', "License key generated: {$key->key}");
    }

    public function revokeKey(MobileLicenseKey $licenseKey)
    {
        $licenseKey->update(['status' => 'suspended']);
        // Also suspend all associated activations
        MobileActivation::where('license_key', $licenseKey->key)->update(['status' => 'suspended']);

        return back()->with('success', "License key {$licenseKey->key} suspended.");
    }
}

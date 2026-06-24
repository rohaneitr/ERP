<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    protected $sensitiveKeys = [
        'password',
        'password_hash',
        'confirm_password',
        'token',
        'access_token',
        'refresh_token',
        'client_secret',
        'secret',
    ];

    /**
     * Log an administrative or security event.
     */
    public function log(
        string $eventType,
        ?string $subjectType = null,
        ?int $subjectId = null,
        $causerType = null,
        ?int $causerId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): void {
        // Automatically determine causer if not explicitly specified
        if ($causerType === null && $causerId === null) {
            if (Auth::guard('api')->check()) {
                $user = Auth::guard('api')->user();
                $causerType = \App\User::class;
                $causerId = $user->id;
            } elseif (Auth::check()) {
                $user = Auth::user();
                $causerType = \App\User::class;
                $causerId = $user->id;
            } else {
                $causerType = 'guest';
                $causerId = null;
            }
        }

        // Sanitize values
        $oldValuesSanitized = $oldValues ? $this->sanitize($oldValues) : null;
        $newValuesSanitized = $newValues ? $this->sanitize($newValues) : null;

        DB::table('audit_logs')->insert([
            'event_type'        => $eventType,
            'subject_type'      => $subjectType,
            'subject_id'        => $subjectId,
            'causer_type'       => $causerType,
            'causer_id'         => $causerId,
            'causer_ip'         => request()->ip(),
            'causer_user_agent' => request()->userAgent(),
            'old_values'        => $oldValuesSanitized ? json_encode($oldValuesSanitized) : null,
            'new_values'        => $newValuesSanitized ? json_encode($newValuesSanitized) : null,
            'metadata'          => $metadata ? json_encode($metadata) : null,
            'created_at'        => now(),
        ]);
    }

    /**
     * Recursively sanitize sensitive keys in data array.
     */
    protected function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $this->sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}

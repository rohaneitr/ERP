<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\DB;

class LoginAttemptService
{
    protected $maxAttempts = 5;
    protected $lockoutDurationMinutes = 15;

    /**
     * Record a login attempt.
     */
    public function recordAttempt(string $identifier, bool $successful, ?string $failureReason = null): void
    {
        DB::table('login_attempts')->insert([
            'identifier'     => $identifier,
            'ip_address'     => request()->ip(),
            'successful'     => $successful,
            'failure_reason' => $failureReason,
            'attempted_at'   => now(),
        ]);
    }

    /**
     * Check if a user is currently locked out.
     */
    public function isLockedOut(User $user): bool
    {
        if ($user->locked_until && $user->locked_until > now()) {
            return true;
        }

        // Auto-unlock if lockout time has passed
        if ($user->locked_until && $user->locked_until <= now()) {
            $this->unlock($user);
        }

        return false;
    }

    /**
     * Handle a failed login attempt for a user.
     */
    public function handleFailedAttempt(User $user, ?string $reason = null): ?\DateTime
    {
        $user->increment('login_attempts_count');

        if ($user->login_attempts_count >= $this->maxAttempts) {
            $lockedUntil = now()->addMinutes($this->lockoutDurationMinutes);
            
            $user->update([
                'locked_until' => $lockedUntil,
            ]);

            // Record lockout
            DB::table('account_lockouts')->insert([
                'user_id'      => $user->id,
                'locked_at'    => now(),
                'locked_until' => $lockedUntil,
                'reason'       => $reason ?: 'Too many failed login attempts.',
                'created_at'   => now(),
            ]);

            // Immutable Audit Log
            app(AuditLogger::class)->log(
                'user.lockout',
                User::class,
                $user->id,
                'system',
                null,
                null,
                ['attempts' => $user->login_attempts_count],
                ['locked_until' => $lockedUntil->toDateTimeString(), 'reason' => $reason ?: 'Too many failed login attempts']
            );

            return $lockedUntil->toDate();
        }

        return null;
    }

    /**
     * Reset login attempts on successful authentication.
     */
    public function resetAttempts(User $user): void
    {
        if ($user->login_attempts_count > 0 || $user->locked_until) {
            $user->update([
                'login_attempts_count' => 0,
                'locked_until'         => null,
            ]);
        }
    }

    /**
     * Unlock a user's account.
     */
    public function unlock(User $user, ?User $admin = null): void
    {
        $user->update([
            'login_attempts_count' => 0,
            'locked_until'         => null,
        ]);

        DB::table('account_lockouts')
            ->where('user_id', $user->id)
            ->whereNull('unlocked_at')
            ->update([
                'unlocked_at' => now(),
                'unlocked_by' => $admin ? $admin->id : null,
            ]);

        // Audit Log
        app(AuditLogger::class)->log(
            'user.unlock',
            User::class,
            $user->id,
            $admin ? User::class : 'system',
            $admin ? $admin->id : null,
            $admin ? ['name' => $admin->name] : null,
            ['locked' => true],
            ['locked' => false]
        );
    }
}

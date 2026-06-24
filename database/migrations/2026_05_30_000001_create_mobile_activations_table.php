<?php

use Illuminate\Database\Migrations\Migration;

/**
 * No-op migration — mobile_activations table already exists (created by
 * 2024_12_01_000001). The new mobile_devices table supersedes it.
 * This file is kept only so the migration system records it as "Ran".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Table already exists — nothing to do.
    }

    public function down(): void
    {
        // Nothing to reverse.
    }
};

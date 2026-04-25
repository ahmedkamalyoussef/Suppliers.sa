<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE supplier_profiles MODIFY service_distance VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE supplier_profiles ALTER COLUMN service_distance TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE supplier_profiles ALTER COLUMN service_distance DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite: recreate table approach would be needed; skipping to avoid destructive ops
            // Keep no-op to avoid data loss in dev sqlite setups
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE supplier_profiles MODIFY service_distance DECIMAL(8,2) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE supplier_profiles ALTER COLUMN service_distance TYPE NUMERIC(8,2)');
            DB::statement('ALTER TABLE supplier_profiles ALTER COLUMN service_distance DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // No-op (see up)
        }
    }
};

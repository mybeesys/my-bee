<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `clients` MODIFY `phone` VARCHAR(255) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clients ALTER COLUMN phone DROP NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `clients` MODIFY `phone` VARCHAR(255) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clients ALTER COLUMN phone SET NOT NULL');
        }
    }
};

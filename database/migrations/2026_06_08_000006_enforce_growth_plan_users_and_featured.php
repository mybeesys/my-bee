<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->where('code', Plan::CODE_FREE)->update([
            'max_allowed_users' => 1,
            'is_featured' => false,
        ]);

        Plan::query()->where('code', Plan::CODE_BUSINESS)->update([
            'max_allowed_users' => 1,
            'is_featured' => false,
            'enable_roles' => true,
        ]);

        Plan::query()->where('code', Plan::CODE_COMPLETE)->update([
            'max_allowed_users' => 2,
            'is_featured' => true,
            'enable_roles' => true,
        ]);
    }

    public function down(): void
    {
        // No rollback — canonical plan rules should remain enforced.
    }
};

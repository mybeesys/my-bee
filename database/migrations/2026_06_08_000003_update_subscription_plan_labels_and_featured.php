<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->where('code', Plan::CODE_BUSINESS)->update([
            'is_featured' => false,
            'max_allowed_users' => 1,
            'enable_roles' => true,
        ]);

        Plan::query()->where('code', Plan::CODE_COMPLETE)->update([
            'is_featured' => true,
            'max_allowed_users' => 2,
            'enable_roles' => true,
        ]);
    }

    public function down(): void
    {
        Plan::query()->where('code', Plan::CODE_BUSINESS)->update([
            'is_featured' => true,
            'max_allowed_users' => 1,
        ]);

        Plan::query()->where('code', Plan::CODE_COMPLETE)->update([
            'is_featured' => false,
            'max_allowed_users' => -1,
        ]);
    }
};

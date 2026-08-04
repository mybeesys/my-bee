<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->where('code', Plan::CODE_FREE)->update([
            'restrict_account_after_days' => 15,
            'max_allowed_users' => 1,
        ]);

        Plan::query()->where('code', Plan::CODE_BUSINESS)->update([
            'max_allowed_users' => 1,
        ]);

        Plan::query()->where('code', Plan::CODE_COMPLETE)->update([
            'max_allowed_users' => 2,
        ]);
    }

    public function down(): void
    {
        Plan::query()->whereIn('code', [
            Plan::CODE_FREE,
            Plan::CODE_BUSINESS,
            Plan::CODE_COMPLETE,
        ])->update([
            'restrict_account_after_days' => -1,
            'max_allowed_users' => -1,
        ]);
    }
};

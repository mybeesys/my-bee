<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('uid', 32)->nullable()->unique()->after('id');
        });

        foreach (\App\Models\Subscription::query()->whereNull('uid')->cursor() as $subscription) {
            do {
                $uid = Str::upper(Str::random(12));
            } while (\App\Models\Subscription::query()->where('uid', $uid)->exists());

            $subscription->update(['uid' => $uid]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};

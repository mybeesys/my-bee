<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePlan(Plan::CODE_FREE, [
            'ar' => 'مجاني',
            'en' => 'Free',
        ]);

        $this->renamePlan(Plan::CODE_BUSINESS, [
            'ar' => 'انطلاقة',
            'en' => 'Launch',
        ]);

        $this->renamePlan(Plan::CODE_COMPLETE, [
            'ar' => 'نمو',
            'en' => 'Growth',
        ]);
    }

    public function down(): void
    {
        $this->renamePlan(Plan::CODE_FREE, [
            'ar' => 'مجانية',
            'en' => 'Free',
        ]);

        $this->renamePlan(Plan::CODE_BUSINESS, [
            'ar' => 'احترافية',
            'en' => 'Professional',
        ]);

        $this->renamePlan(Plan::CODE_COMPLETE, [
            'ar' => 'متكاملة',
            'en' => 'Complete',
        ]);
    }

    /** @param array{ar: string, en: string} $names */
    private function renamePlan(string $code, array $names): void
    {
        $plan = Plan::query()->where('code', $code)->first();

        if (! $plan) {
            return;
        }

        $plan->setTranslations('name', $names);
        $plan->save();
    }
};

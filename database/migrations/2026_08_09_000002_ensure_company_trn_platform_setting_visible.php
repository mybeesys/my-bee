<?php

use App\Models\Setting;
use App\Services\CacheService;
use App\Services\SettingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = new SettingService(null);

        $service->createOrUpdate(
            'company.trn',
            ['en' => 'Tax registration number', 'ar' => 'الرقم الضريبي'],
            Setting::query()->whereNull('tenant_id')->where('key', 'company.trn')->value('value'),
            'text',
            false,
            $service->rulesForString(false),
            'general',
            [],
            false,
            'General',
            null,
            null,
            3,
            2,
        );

        $sorts = [
            'company.name' => 1,
            'company.address' => 2,
            'company.trn' => 3,
            'company.contact.phone' => 4,
            'company.contact.mobile' => 5,
            'company.contact.email' => 6,
        ];

        foreach ($sorts as $key => $sort) {
            Setting::query()
                ->whereNull('tenant_id')
                ->where('key', $key)
                ->update([
                    'sort' => $sort,
                    'tab' => 'General',
                    'visible_in_user_friendly_settings' => true,
                ]);
        }

        CacheService::instance()->forget('settings');
        CacheService::instance()->forget('platform_settings');
    }

    public function down(): void
    {
        // No-op: keep company.trn once provisioned.
    }
};

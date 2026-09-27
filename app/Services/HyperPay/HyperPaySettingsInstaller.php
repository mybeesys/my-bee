<?php

namespace App\Services\HyperPay;

use App\Models\Setting;
use App\Services\CacheService;
use App\Services\SettingService;

class HyperPaySettingsInstaller
{
    public static function install(): void
    {
        $service = new SettingService(null);

        $rows = [
            [
                'key' => 'hyperpay.enabled',
                'display_name' => ['en' => 'Enable HyperPay card payments', 'ar' => 'تفعيل الدفع بالبطاقة عبر HyperPay'],
                'value' => config('hyperpay.enabled') ? '1' : '0',
                'type' => 'toggle',
                'rules' => $service->rulesForBoolean(false),
                'helper' => 'When enabled, paid plans can be activated automatically after a successful card payment.',
                'sort' => 1,
            ],
            [
                'key' => 'hyperpay.mode',
                'display_name' => ['en' => 'Environment', 'ar' => 'بيئة العمل'],
                'value' => (string) config('hyperpay.mode', 'test'),
                'type' => 'options',
                'options' => [
                    'test' => 'Test',
                    'live' => 'Live',
                ],
                'rules' => $service->rulesForString(true, 10),
                'helper' => 'Use Test with the HyperPay sandbox. Switch to Live only after go-live credentials are issued.',
                'sort' => 2,
            ],
            [
                'key' => 'hyperpay.test_base_url',
                'display_name' => ['en' => 'Test server URL', 'ar' => 'رابط خادم الاختبار'],
                'value' => (string) config('hyperpay.test_base_url'),
                'type' => 'text',
                'rules' => $service->rulesForURL(true),
                'helper' => 'https://eu-test.oppwa.com',
                'sort' => 3,
            ],
            [
                'key' => 'hyperpay.live_base_url',
                'display_name' => ['en' => 'Live server URL', 'ar' => 'رابط خادم الإنتاج'],
                'value' => (string) config('hyperpay.live_base_url'),
                'type' => 'text',
                'rules' => $service->rulesForURL(true),
                'helper' => 'https://eu-prod.oppwa.com',
                'sort' => 4,
            ],
            [
                'key' => 'hyperpay.access_token',
                'display_name' => ['en' => 'Access token', 'ar' => 'رمز الوصول'],
                'value' => (string) config('hyperpay.access_token'),
                'type' => 'text',
                'password' => true,
                'rules' => $service->rulesForText(false),
                'helper' => 'Bearer token from HyperPay. Leave blank when saving if you do not want to replace the stored token.',
                'sort' => 5,
            ],
            [
                'key' => 'hyperpay.entity_id',
                'display_name' => ['en' => 'Entity ID (MADA / Visa / Mastercard)', 'ar' => 'معرّف الكيان (مدى / فيزا / ماستركارد)'],
                'value' => (string) config('hyperpay.entity_id'),
                'type' => 'text',
                'rules' => $service->rulesForString(false, 64),
                'helper' => 'Single entity ID covering MADA, Visa, and Mastercard.',
                'sort' => 6,
            ],
            [
                'key' => 'hyperpay.currency',
                'display_name' => ['en' => 'Currency', 'ar' => 'العملة'],
                'value' => (string) config('hyperpay.currency', 'SAR'),
                'type' => 'text',
                'rules' => $service->rulesForString(true, 3),
                'helper' => 'SAR',
                'sort' => 7,
            ],
            [
                'key' => 'hyperpay.payment_type',
                'display_name' => ['en' => 'Payment type', 'ar' => 'نوع الدفع'],
                'value' => (string) config('hyperpay.payment_type', 'DB'),
                'type' => 'options',
                'options' => [
                    'DB' => 'DB — Debit / capture',
                    'PA' => 'PA — Pre-authorization',
                ],
                'rules' => $service->rulesForString(true, 4),
                'helper' => 'HyperPay specified DB (debit) for this integration.',
                'sort' => 8,
            ],
            [
                'key' => 'hyperpay.brands',
                'display_name' => ['en' => 'Card brands (MADA first)', 'ar' => 'طرق الدفع (مدى أولاً)'],
                'value' => (string) config('hyperpay.brands', 'MADA VISA MASTER'),
                'type' => 'text',
                'rules' => $service->rulesForString(true, 80),
                'helper' => 'Space-separated. MADA is always shown first on the checkout page.',
                'sort' => 9,
            ],
            [
                'key' => 'hyperpay.allow_manual_request',
                'display_name' => ['en' => 'Allow “send request” without card', 'ar' => 'السماح بطلب التجديد بدون بطاقة'],
                'value' => config('hyperpay.allow_manual_request') ? '1' : '0',
                'type' => 'toggle',
                'rules' => $service->rulesForBoolean(false),
                'helper' => 'Keep enabled if clients may still send a renewal request for manual collection.',
                'sort' => 10,
            ],
            [
                'key' => 'hyperpay.round_test_amounts',
                'display_name' => ['en' => 'Round test amounts to xx.00', 'ar' => 'تقريب مبالغ الاختبار إلى xx.00'],
                'value' => config('hyperpay.round_test_amounts') ? '1' : '0',
                'type' => 'toggle',
                'rules' => $service->rulesForBoolean(false),
                'helper' => 'Required by HyperPay test cards for a successful response. Ignored in Live.',
                'sort' => 11,
            ],
            [
                'key' => 'hyperpay.widget_locale',
                'display_name' => ['en' => 'Checkout widget language', 'ar' => 'لغة صفحة الدفع'],
                'value' => (string) config('hyperpay.widget_locale', 'ar'),
                'type' => 'options',
                'options' => [
                    'ar' => 'العربية',
                    'en' => 'English',
                ],
                'rules' => $service->rulesForString(true, 5),
                'sort' => 12,
            ],
            [
                'key' => 'hyperpay.merchant_portal_url',
                'display_name' => ['en' => 'Merchant portal URL', 'ar' => 'رابط لوحة التاجر'],
                'value' => (string) config('hyperpay.merchant_portal_url'),
                'type' => 'text',
                'rules' => $service->rulesForURL(false),
                'helper' => 'Used by the team to inspect transactions (not used in the API).',
                'sort' => 13,
            ],
            [
                'key' => 'hyperpay.extra_widget_js',
                'display_name' => ['en' => 'Extra widget scripts (MADA / SAMA)', 'ar' => 'سكربتات إضافية للويدجت (مدى / ساما)'],
                'value' => '',
                'type' => 'text-area',
                'rules' => $service->rulesForText(false),
                'helper' => 'Optional HTML/JS pasted after paymentWidgets.js (SAMA/MADA scripts from HyperPay).',
                'sort' => 14,
            ],
        ];

        foreach ($rows as $row) {
            if (self::exists($row['key'])) {
                continue;
            }

            $service->createOrUpdate(
                $row['key'],
                $row['display_name'],
                $row['value'],
                $row['type'],
                false,
                $row['rules'],
                'hyperpay',
                $row['options'] ?? [],
                $row['password'] ?? false,
                'HyperPay',
                null,
                $row['helper'] ?? null,
                $row['sort'],
                20,
                true,
            );
        }

        if (! self::exists('settings.tabs.hyperpay.icon')) {
            $service->createOrUpdate(
                'settings.tabs.hyperpay.icon',
                ['en' => 'HyperPay tab icon', 'ar' => 'أيقونة تبويب HyperPay'],
                'heroicon-o-credit-card',
                'text',
                false,
                $service->rulesForString(false),
                'settings tabs',
                [],
                false,
                null,
                null,
                null,
                111,
                111,
                false,
            );
        }

        if (! self::exists('settings.tabs.hyperpay.requires_special_access')) {
            $service->createOrUpdate(
                'settings.tabs.hyperpay.requires_special_access',
                ['en' => 'HyperPay tab special access', 'ar' => 'HyperPay tab special access'],
                false,
                'bool',
                false,
                $service->rulesForBoolean(),
                'settings tabs',
                [],
                false,
                null,
                null,
                null,
                110,
                1010,
                false,
            );
        }

        CacheService::instance()->forget('settings');
        CacheService::instance()->forget('platform_settings');
    }

    /**
     * @return array<string, string>
     */
    public static function testDefaults(): array
    {
        $defaults = config('hyperpay.test_defaults', []);

        return is_array($defaults) ? $defaults : [];
    }

    public static function applyTestDefaults(): void
    {
        self::install();

        foreach (self::testDefaults() as $suffix => $value) {
            $setting = Setting::query()
                ->whereNull('tenant_id')
                ->where('key', 'hyperpay.'.$suffix)
                ->first();

            if (! $setting) {
                continue;
            }

            $setting->update(['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        CacheService::instance()->forget('settings');
        CacheService::instance()->forget('platform_settings');
    }

    protected static function exists(string $key): bool
    {
        return Setting::query()
            ->whereNull('tenant_id')
            ->where('key', $key)
            ->exists();
    }
}

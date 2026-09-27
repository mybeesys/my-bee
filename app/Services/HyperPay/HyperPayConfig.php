<?php

namespace App\Services\HyperPay;

class HyperPayConfig
{
    public function __construct(protected array $overrides = [])
    {
    }

    public static function instance(array $overrides = []): self
    {
        return new self($overrides);
    }

    public function enabled(): bool
    {
        return $this->bool('enabled', true);
    }

    public function allowManualRequest(): bool
    {
        return $this->bool('allow_manual_request', true);
    }

    public function roundTestAmounts(): bool
    {
        return $this->bool('round_test_amounts', true);
    }

    public function widgetLocale(): string
    {
        $locale = strtolower($this->string('widget_locale', 'ar'));

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }

    public function extraWidgetJs(): string
    {
        return trim($this->string('extra_widget_js', ''));
    }

    public function merchantPortalUrl(): string
    {
        return $this->string('merchant_portal_url', 'https://gate2play.test.ctpe.info');
    }

    public function timeout(): int
    {
        $timeout = $this->configValue('timeout');

        return max(5, (int) ($timeout ?: 30));
    }

    public function isTest(): bool
    {
        return $this->string('mode', 'test') !== 'live';
    }

    public function baseUrl(): string
    {
        $url = $this->isTest()
            ? $this->string('test_base_url', 'https://eu-test.oppwa.com')
            : $this->string('live_base_url', 'https://eu-prod.oppwa.com');

        return rtrim($url, '/');
    }

    public function accessToken(): string
    {
        return $this->string('access_token');
    }

    public function entityId(): string
    {
        return $this->string('entity_id');
    }

    public function currency(): string
    {
        $currency = strtoupper($this->string('currency', 'SAR'));

        return $currency !== '' ? $currency : 'SAR';
    }

    public function paymentType(): string
    {
        $type = strtoupper($this->string('payment_type', 'DB'));

        return $type !== '' ? $type : 'DB';
    }

    public function brands(): string
    {
        $brands = strtoupper(trim($this->string('brands', 'MADA VISA MASTER')));

        if ($brands === '') {
            return 'MADA VISA MASTER';
        }

        $parts = preg_split('/[\s,]+/', $brands) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return 'MADA VISA MASTER';
        }

        if (! in_array('MADA', $parts, true)) {
            array_unshift($parts, 'MADA');
        } else {
            $parts = array_values(array_unique(array_merge(
                ['MADA'],
                array_filter($parts, fn (string $brand) => $brand !== 'MADA')
            )));
        }

        return implode(' ', $parts);
    }

    public function isConfigured(): bool
    {
        return $this->enabled()
            && $this->accessToken() !== ''
            && $this->entityId() !== ''
            && $this->baseUrl() !== '';
    }

    public function formatAmount(float $amount): string
    {
        if ($this->isTest() && $this->roundTestAmounts()) {
            return number_format((float) round($amount), 2, '.', '');
        }

        return number_format(round($amount, 2), 2, '.', '');
    }

    protected function string(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $this->overrides) && $this->overrides[$key] !== null) {
            return trim((string) $this->overrides[$key]);
        }

        $value = $this->settingValue($key);

        if ($value === null || $value === '') {
            $fromConfig = $this->configValue($key);

            if ($fromConfig !== null && $fromConfig !== '') {
                return trim((string) $fromConfig);
            }

            return trim($default);
        }

        return trim((string) $value);
    }

    protected function bool(string $key, bool $default): bool
    {
        if (array_key_exists($key, $this->overrides) && $this->overrides[$key] !== null) {
            return filter_var($this->overrides[$key], FILTER_VALIDATE_BOOLEAN);
        }

        $value = $this->settingValue($key);

        if ($value === null || $value === '') {
            $fromConfig = $this->configValue($key);

            if ($fromConfig !== null && $fromConfig !== '') {
                return filter_var($fromConfig, FILTER_VALIDATE_BOOLEAN);
            }

            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    protected function configValue(string $key): mixed
    {
        try {
            if (! function_exists('config') || ! function_exists('app') || ! app()->bound('config')) {
                return null;
            }

            return config('hyperpay.'.$key);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function settingValue(string $key): mixed
    {
        try {
            if (! function_exists('platform_setting') || ! function_exists('app') || ! app()->bound('config')) {
                return null;
            }

            return platform_setting('hyperpay.'.$key, null);
        } catch (\Throwable) {
            return null;
        }
    }
}

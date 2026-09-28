<?php

return [
    'enabled' => env('HYPERPAY_ENABLED', true),
    'mode' => env('HYPERPAY_MODE', 'test'),
    'test_base_url' => env('HYPERPAY_TEST_BASE_URL', 'https://eu-test.oppwa.com'),
    'live_base_url' => env('HYPERPAY_LIVE_BASE_URL', 'https://eu-prod.oppwa.com'),
    'access_token' => env('HYPERPAY_ACCESS_TOKEN'),
    'entity_id' => env('HYPERPAY_ENTITY_ID'),
    'currency' => env('HYPERPAY_CURRENCY', 'SAR'),
    'payment_type' => env('HYPERPAY_PAYMENT_TYPE', 'DB'),
    'brands' => env('HYPERPAY_BRANDS', 'MADA VISA MASTER'),
    'allow_manual_request' => env('HYPERPAY_ALLOW_MANUAL_REQUEST', false),
    'round_test_amounts' => env('HYPERPAY_ROUND_TEST_AMOUNTS', true),
    'widget_locale' => env('HYPERPAY_WIDGET_LOCALE', 'ar'),
    'merchant_portal_url' => env('HYPERPAY_MERCHANT_PORTAL_URL', 'https://gate2play.test.ctpe.info'),
    'timeout' => (int) env('HYPERPAY_TIMEOUT', 30),

    /*
    | Sandbox credentials issued by HyperPay for MyBee (SNB CRM:028879).
    | Used only by the admin “fill test defaults” action.
    */
    'test_defaults' => [
        'enabled' => '1',
        'mode' => 'test',
        'test_base_url' => 'https://eu-test.oppwa.com',
        'live_base_url' => 'https://eu-prod.oppwa.com',
        'access_token' => 'OGFjN2E0ZGFhMGFlNjg1MTAxYTBhZmUwYzU0YzBhYjl8elJkN1hxZD9Gek5TSG5rdUpaeHo=',
        'entity_id' => '8ac7a4c8a0ae67f201a0afe12c9e031f',
        'currency' => 'SAR',
        'payment_type' => 'DB',
        'brands' => 'MADA VISA MASTER',
        'allow_manual_request' => '1',
        'round_test_amounts' => '1',
        'widget_locale' => 'ar',
        'merchant_portal_url' => 'https://gate2play.test.ctpe.info',
        'extra_widget_js' => '<script type="text/javascript">var wpwlOptions = { paymentTarget: "_top" };</script>',
    ],
];

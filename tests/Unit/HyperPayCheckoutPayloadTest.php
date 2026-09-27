<?php

namespace Tests\Unit;

use App\Services\HyperPay\HyperPayCheckoutService;
use App\Services\HyperPay\HyperPayConfig;
use App\Services\HyperPay\HyperPayGateway;
use PHPUnit\Framework\TestCase;

class HyperPayCheckoutPayloadTest extends TestCase
{
    public function test_checkout_payload_includes_required_hyperpay_fields(): void
    {
        $config = new HyperPayConfig([
            'mode' => 'test',
            'entity_id' => 'entity-test',
            'currency' => 'SAR',
            'payment_type' => 'DB',
        ]);

        $service = new HyperPayCheckoutService($config, HyperPayGateway::make($config));

        $payload = $service->checkoutPayload('100.00', 'MB-1-TEST', [
            'given_name' => 'Karam',
            'surname' => 'Mohammad',
            'email' => 'karam@mybeesystem.com',
            'street1' => 'King Fahd Road',
            'city' => 'Riyadh',
            'state' => 'Riyadh',
            'country' => 'SA',
            'postcode' => '11564',
        ]);

        $this->assertSame('entity-test', $payload['entityId']);
        $this->assertSame('100.00', $payload['amount']);
        $this->assertSame('SAR', $payload['currency']);
        $this->assertSame('DB', $payload['paymentType']);
        $this->assertSame('EXTERNAL', $payload['testMode']);
        $this->assertSame('true', $payload['customParameters[3DS2_enrolled]']);
        $this->assertSame('challenge', $payload['customParameters[3DS2_flow]']);
        $this->assertSame('true', $payload['integrity']);
        $this->assertSame('karam@mybeesystem.com', $payload['customer.email']);
        $this->assertSame('SA', $payload['billing.country']);
    }

    public function test_live_payload_omits_test_only_parameters(): void
    {
        $config = new HyperPayConfig([
            'mode' => 'live',
            'entity_id' => 'entity-live',
            'currency' => 'SAR',
            'payment_type' => 'DB',
        ]);

        $service = new HyperPayCheckoutService($config, HyperPayGateway::make($config));

        $payload = $service->checkoutPayload('99.50', 'MB-2-LIVE', [
            'given_name' => 'Ahmad',
            'surname' => 'Qasem',
            'email' => 'ahmad@example.com',
            'street1' => 'Olaya',
            'city' => 'Riyadh',
            'state' => 'Riyadh',
            'country' => 'sa',
            'postcode' => '12214',
        ]);

        $this->assertArrayNotHasKey('testMode', $payload);
        $this->assertArrayNotHasKey('customParameters[3DS2_enrolled]', $payload);
        $this->assertSame('SA', $payload['billing.country']);
        $this->assertSame('99.50', $payload['amount']);
    }

    public function test_saudi_postcode_must_be_five_digits(): void
    {
        $config = new HyperPayConfig([
            'mode' => 'test',
            'entity_id' => 'entity-test',
        ]);
        $service = new HyperPayCheckoutService($config, HyperPayGateway::make($config));

        $this->expectException(\InvalidArgumentException::class);
        $service->assertBilling([
            'given_name' => 'MHd',
            'surname' => 'Amen',
            'email' => 'moaen042@gmail.com',
            'street1' => 'الشارع',
            'city' => 'المدينة',
            'state' => 'المنطقة',
            'country' => 'SA',
            'postcode' => 'الرمز البريدي',
        ]);
    }
}

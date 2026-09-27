<?php

namespace Tests\Unit;

use App\Services\HyperPay\HyperPayConfig;
use App\Services\HyperPay\HyperPayResult;
use PHPUnit\Framework\TestCase;

class HyperPayResultTest extends TestCase
{
    public function test_success_codes(): void
    {
        $this->assertTrue(HyperPayResult::isSuccessful('000.000.000'));
        $this->assertTrue(HyperPayResult::isSuccessful('000.100.110'));
        $this->assertTrue(HyperPayResult::isSuccessful('000.300.000'));
        $this->assertFalse(HyperPayResult::isSuccessful('800.400.500'));
        $this->assertFalse(HyperPayResult::isSuccessful(null));
    }

    public function test_pending_codes(): void
    {
        $this->assertTrue(HyperPayResult::isPending('000.200.000'));
        $this->assertFalse(HyperPayResult::isPending('000.000.000'));
    }

    public function test_test_amount_rounding(): void
    {
        $config = new HyperPayConfig([
            'mode' => 'test',
            'round_test_amounts' => true,
        ]);

        $this->assertSame('115.00', $config->formatAmount(114.7));

        $live = new HyperPayConfig([
            'mode' => 'live',
            'round_test_amounts' => true,
        ]);

        $this->assertSame('114.70', $live->formatAmount(114.7));
    }

    public function test_mada_is_always_first_brand(): void
    {
        $config = new HyperPayConfig([
            'brands' => 'VISA MASTER',
        ]);

        $this->assertSame('MADA VISA MASTER', $config->brands());
    }
}

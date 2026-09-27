<?php

namespace App\Services\HyperPay;

class HyperPayResult
{
    /**
     * OPPWA success codes (including 3-D Secure success).
     */
    public static function isSuccessful(?string $code): bool
    {
        if (! is_string($code) || $code === '') {
            return false;
        }

        return (bool) preg_match('/^(000\.000\.|000\.100\.1|000\.[36]|000\.400\.[01][12]0)/', $code);
    }

    public static function isPending(?string $code): bool
    {
        if (! is_string($code) || $code === '') {
            return false;
        }

        return (bool) preg_match('/^(000\.200|800\.400\.5|100\.400\.500)/', $code);
    }
}

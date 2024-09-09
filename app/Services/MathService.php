<?php


namespace App\Services;

use App\Models\TaxProfile;

class MathService
{
    public static function instance()
    {
        return new self();
    }

    public function getTax($total, $tax_percent, bool $prices_includes_taxes = false): float
    {
        if ($prices_includes_taxes) {
            $result = 1 + ($tax_percent / 100);
            $result = $total / $result;
            return $total - $result;
        } else {
            return $total * ($tax_percent / 100);
        }
    }

    public function getTaxFromTaxProfile($total, TaxProfile $taxProfile, bool $prices_includes_taxes = false): float
    {
        $tax_percent = (float)$taxProfile->total_percentages;
        if ($prices_includes_taxes) {
            $result = 1 + ($tax_percent / 100);
            $result = $total / $result;
            return $total - $result;
        } else {
            return $total * ($tax_percent / 100);
        }
    }
}

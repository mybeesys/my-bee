<?php


namespace App\Services;

use App\Models\TaxProfile;

class MathService
{
    public static function instance()
    {
        return new self();
    }

    public function getTax($total, $tax_percent): float
    {
//        =115/1.15*.15
        $percent = floatval("1.$tax_percent");
        return floatval(($total / $percent) * floatval("0.$tax_percent"));
    }

    public function getTaxFromTaxProfile($total, TaxProfile $taxProfile): float
    {
//        Excluding VAT from sale price:
//        =115/1.15*.15
        $tax_percent = $taxProfile->total_percentages;
        $percent = (float)$tax_percent;
        $x = 100 + $percent;
        return ($total * ($percent / $x));

//        $tax_percent = $taxProfile->total_percentages;
//        $percent = (float)"1.$tax_percent";
//        return ($total / $percent) * (float)"0.$tax_percent";
    }
}

<?php

namespace App\Models;

use App\Services\MathService;
use App\Services\PricingService;
use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceOffer extends BaseModel
{
    use HasFactory, HasPrefixedId;

    protected $guarded = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceOfferDetails::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Service::class, 'item');
    }

    public function additionalCosts(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AdditionalCost::class, 'item');
    }

    public function getItemsCost($withAdditionalCosts = false, $applyDiscount = false, $applyTaxes = false)
    {
        $items = 0;
        $extras = 0;
        $services = 0;
        $additionalCosts = 0;
        $taxes = 0;

        foreach ($this->details as $detail) {
            $subTotal = $detail->unit_price * $detail->qty;

            $extras += $this->extras_total;

            if ($applyDiscount) {
                $subTotal -= $detail->discount;
            }
            $items += $subTotal;
        }

        if ($withAdditionalCosts) {
            $additionalCosts = $this->getAdditionalCosts(true);
        }

        $services = $this->getServicesCost(true);

        if ($applyTaxes) {
            $taxes = $this->getTaxesAsAmount();
        }

        $taxes = $this->prices_includes_taxes ? 0 : $taxes;

        return $items + $extras + $additionalCosts + $services + $taxes;
    }

    public function getAdditionalCosts($withTaxes = false)
    {
        $total = 0;

        foreach ($this->additionalCosts as $additionalCost) {
            $cost = $additionalCost->cost;
            $tax = 0;
            if ($withTaxes and $additionalCost->tax_profile_data) {
                $total_percentages = collect($additionalCost->tax_profile_data['taxes'] ?? null)->sum('percent');
                if ($total_percentages > 0 and !$this->prices_includes_taxes){
                    $tax = MathService::instance()->getTax($cost, $total_percentages, $this->prices_includes_taxes);
                }
            }
            $total += $cost + $tax;
        }
        return $total;
    }

    public function getTaxesAsAmount(): float|int
    {
        $total = 0;

        foreach ($this->details as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) use($item) {
                    return collect($i['taxes'])->sum('percent');
                });
                $subTotal = $item->unit_price * $item->qty;
                $subTotal -= $item->discount;
                $subTotal += $this->extras_total;
                $total += MathService::instance()->getTax($subTotal, $total_percentages, $this->prices_includes_taxes);
            } else {
                $subTotal = $item->unit_price * $item->qty;
                $subTotal -= $item->discount;
                $subTotal += $this->extras_total;

                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($subTotal, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        foreach ($this->additionalCosts as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) {
                    return collect($i['taxes'])->sum('percent');
                });
                $total += MathService::instance()->getTax($item->cost, $total_percentages, $this->prices_includes_taxes);
            } else {
                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($item->cost, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        foreach ($this->services as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) {
                    return collect($i['taxes'])->sum('percent');
                });
                $total += MathService::instance()->getTax($item->price, $total_percentages, $this->prices_includes_taxes);
            } else {
                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($item->price, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        return $total;
    }

    public function getServicesCost($withTaxes = false)
    {
        $total = 0;

        foreach ($this->services as $service) {
            $price = $service->price;
            $tax = 0;
            if ($withTaxes and $service->tax_profile_data) {
                $total_percentages = collect($service->tax_profile_data['taxes'] ?? null)->sum('percent');
                if ($total_percentages > 0 and !$this->prices_includes_taxes){
                    $tax = MathService::instance()->getTax($price, $total_percentages, $this->prices_includes_taxes);
                }
            }
            $total += $price + $tax;
        }
        return $total;
    }

    public function getExtrasTotalAttribute()
    {
        $amount = 0;
        foreach ($this->details as $detail) {
            foreach ($detail->offerDetailsExtras as $offerDetailsExtra) {
                $amount += $offerDetailsExtra->unit_price * $detail->qty;
            }
        }
        return $amount;
    }

    public function getUrlAttribute()
    {
        return config('app.shop_url') . \Filament\Facades\Filament::getTenant()->slug . "/price-offers/" . $this->no;
    }
}

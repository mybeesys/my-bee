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
        $total = 0;
        $extras = 0;
        $services = 0;

        foreach ($this->details as $detail) {
            $subTotal = $detail->unit_price * $detail->qty;

            if ($detail->offerDetailsExtras)
                $extras += PricingService::instance()->getItemsPrices($detail->offerDetailsExtras->pluck('productExtra')) * $detail->qty;

//            $extras += $detail->offerDetailsExtras->sum('unit_price');

            if ($applyDiscount) {
                $subTotal -= $detail->discount;
            }

            $total += $subTotal;
        }

        if ($withAdditionalCosts) {
            $total += $this->getAdditionalCosts();
        }

        if ($applyTaxes) {
            $total += $this->getTaxesAsAmount();
        }

        $services += $this->getServicesCost(true);

        return $total + $services + $extras;
    }

    public function getAdditionalCosts()
    {
        $total = 0;
        foreach ($this->additionalCosts as $item) {
            $total += $item->cost;
        }
        return $total;
    }

    public function getTaxesAsAmount(): float|int
    {
        $total = 0;

        foreach ($this->details as $index => $detail) {

            if ($detail->tax_profile_data) {
                $total_percentages = collect($detail->tax_profile_data['taxes'] ?? [])->sum('percent');

                $subTotal = $detail->unit_price * $detail->qty;
                $subTotal -= $detail->discount;
                $total += MathService::instance()->getTax($subTotal, $total_percentages, $this->prices_includes_taxes);
//                $total += $subTotal * ($total_percentages / 100);

            }
        }

        return $total;
    }

    protected function getServicesCost($withTaxes = false)
    {
        $total = 0;

        foreach ($this->services as $service) {
            $total += $service->price;
            if ($withTaxes and $service->tax_profile_data) {
                $total_percentages = collect($service->tax_profile_data['taxes'] ?? null)->sum('percent');
                if ($total_percentages > 0)
                    $total += $service->price * ($total_percentages / 100);
            }
        }
        return $total;
    }

    public function getUrlAttribute()
    {
        return config('app.shop_url') . \Filament\Facades\Filament::getTenant()->slug . "/price-offers/" . $this->no;
    }
}

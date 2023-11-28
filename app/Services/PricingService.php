<?php


namespace App\Services;


use App\Models\Category;
use App\Models\ItemPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    protected $tenant_id, $getCurrencyFromSettings = true,
        $defaultCurrencyCode = "SAR", $exchangeRate = null, $validatePriceBiggerThanMainUnitPrice = true;

    public static function instance()
    {
        return new self();
    }

    public function tenant($tenant_id): self
    {
        $this->tenant_id = $tenant_id;
        return $this;
    }

    public function currencyFromSettings($getCurrencyFromSettings = true): self
    {
        $this->getCurrencyFromSettings = $getCurrencyFromSettings;
        return $this;
    }

    public function currencyCode($currencyCode = "SAR"): self
    {
        $this->defaultCurrencyCode = $currencyCode;
        return $this;
    }

    public function validatePriceHigherThanMainUnitPrice($value = true): self
    {
        $this->validatePriceBiggerThanMainUnitPrice = $value;
        return $this;
    }

    public function exchangeRate($exRate = null): self
    {
        $this->exchangeRate = $exRate;
        return $this;
    }

    protected function getCurrencyCode()
    {
        if ($this->getCurrencyFromSettings)
            return setting('main_currency', $this->defaultCurrencyCode);

        return $this->defaultCurrencyCode;
    }

    protected function getTenantId()
    {
        return $this->tenant_id ?? filament()->getTenant()->id;
    }

    protected function shouldAddNewPrice(Model $model, $unit_id, $unit_cost, $new_price): bool
    {
        if (!$new_price) {
            return false;
        }

        $lastPrice = $model->acc4->lastPrice;
        $main_unit_price = $model->acc4->prices->where('unit_id', $model->main_unit_id)->last()?->price;

        if ($this->validatePriceBiggerThanMainUnitPrice and $unit_id != $model->main_unit_id and $new_price < $main_unit_price) {
            return false;
        }

        if ($new_price == $lastPrice?->price and $unit_cost == $lastPrice?->unit_cost)
            return false;

        return true;
    }

    public function addPrice(Model $item, $unit_id, $cost, $price, $throwErrorOnTenantAbsence = true): ?Model
    {
        $item->loadMissing('acc4');

        $tenant_id = self::getTenantId();

        if ($throwErrorOnTenantAbsence and !$tenant_id) {
            throw new \Exception("Tenant not specified.");
        }

        if (!$this->shouldAddNewPrice($item, $unit_id, $cost, $price))
            return null;

        return ItemPrice::create([
            'tenant_id' => $tenant_id,
            'unit_id' => $unit_id,
            'acc4_code' => $item->acc4->code,
            'currency_iso_code' => self::getCurrencyCode(),
            'unit_cost' => $cost,
            'price' => $price,
            'date' => now(),
            'exchange_rate' => $this->exchangeRate,
        ]);
    }

    public function getLastPriceForUnit(Model $model, $unit_id): ?ItemPrice
    {
        $model->load(['acc4.item.prices']);

        return $model->acc4->prices->where('unit_id', $unit_id)->last();
    }
}

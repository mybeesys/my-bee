<?php


namespace App\Services;


use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\VariantLibraryOption;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;

class FilamentVariantBuilderService
{
    protected ?Product $product;
    protected Livewire $livewire;
    protected Collection $variantLibraryOptions;
    protected $tenant_id;

    public function __construct(?Product $product, $livewire)
    {
        $this->product = $product;
        $this->livewire = $livewire;
        $this->variantLibraryOptions = Cache::remember("variantLibraryOptions@" . \filament()->getTenant()->id, 60, function () {
            return VariantLibraryOption::all();
        });
        $this->tenant_id = filament()->getTenant()->id;
    }

    public static function instance($product, $livewire): self
    {
        return new self($product, $livewire);
    }

    public function buildOptions(): array
    {
//        if ($this->product)
//            return self::buildFromRecord();

        return $this->buildFromLivewire();
    }

    protected function buildFromLivewire(): array
    {
        $libraries = collect($this->livewire->data['variant_options'] ?? []);

        $values = array_filter($libraries->pluck('values')->toArray());

        $combinations = $this->combinations($values);

//        dd($libraries, $values, $combinations);

        $data = $this->buildFromRecord();

        $ignore_combinations = collect($data)->pluck('variant_library_options_ids')->toArray();

        foreach ($data as $key => $item) {
            //if item not i cmb it should be deleted

            $exists = collect($combinations)->filter(function ($combination) use ($item) {
                $array1 = is_array($combination) ? $combination : [$combination];;
                $array2 = $item['variant_library_options_ids'];
                return array_diff($array1, $array2) == array_diff($array2, $array1);
            })->first();

            if (!$exists) {
                $data[$key]['should_remove'] = true;
            }
        }

        foreach ($combinations as $combination) {

            $cmb = is_array($combination) ? $combination : [$combination];

            if (in_array($cmb, $ignore_combinations))
                continue;

            if (is_array($combination)) {
                $item = $this->makeOption($combination);
                $data = array_merge($data, $item);
            } else {
                $variantLibOption = $this->getVariantLibraryOptionByID($combination);

                $name_ar = $this->getProductName() . " - " . $variantLibOption->name_ar;
                $name_en = $this->getProductName() . " - " . $variantLibOption->name_en;
                $name = $this->getProductName() . " - " . $variantLibOption->name;

                $item = $this->buildItem(Str::uuid()->toString(), $name_ar, $name_en, $name, [$combination], false, self::getSku(), 0);
//                $item[Str::uuid()->toString()] = [
//                    'new_item' => $this->getExistingVariantByCombination([$combination]),
//                    'name_ar' => $this->getProductName() . " - " . $variantLibOption->name_ar,
//                    'name_en' => $this->getProductName() . " - " . $variantLibOption->name_en,
//                    'name' => $this->getProductName() . " - " . $variantLibOption->name,
//                    'tenant_id' => $this->tenant_id,
//                    'variant_library_options_ids' => [$combination],
//                    'warehouse_id' => self::getWarehouseId(),
//                    'unlimited_qty' => false,
//                    'sku' => random_int(111111111, 999999999),
//                    'qty' => 0,
//                ];
                $data = array_merge($data, $item);

            }
        }
//        foreach ($libraries as $library) {
//            $currentLibId = $library['variant_library_id'];
//            foreach ($library['values'] as $value) {
//                $targetLibs = $libraries->filter(function ($item) use ($currentLibId) {
//                    return $item['variant_library_id'] != $currentLibId;
//                });
//
//                $data = array_merge($data, $this->buildOption($value, $targetLibs));
//            }
//        }

        return $data;
    }

    public function buildFromRecord(): array
    {

        $libraries = collect($this->livewire->data['variant_options'] ?? []);

        $data = [];

        if ($this->product)
            foreach ($this->product->variants as $variant) {
                $item[Str::uuid()->toString()] = [
                    'new_item' => false,
                    'name_ar' => $variant->name_ar,
                    'name_en' => $variant->name_en,
                    'name' => $variant->name,
                    'tenant_id' => $this->tenant_id,
                    'variant_library_options_ids' => $variant->variant_library_options_ids,
                    'warehouse_id' => $variant->warehouse_id ?? self::getWarehouseId(),
                    'unlimited_qty' => $variant->unlimited_qty,
                    'sku' => $variant->sku,
                    'qty' => $variant->qty,
                    'unit_cost' => (is_number($variant->unit_cost) and $variant->unit_cost > 0) ? number_format($variant->unit_cost, currency_decimals(), '.', '') : null,
                    'price' => (is_number($variant->price) and $variant->price > 0) ? number_format($variant->price, currency_decimals(), '.', '') : null,
                    'discount_price' => (is_number($variant->discount_price) and $variant->discount_price > 0) ? number_format($variant->discount_price, currency_decimals(), '.', '') : null,
                ];
                $data = array_merge($data, $item);
            }

        return $data;
    }

    protected function getVariantLibraryOptionByID($id): VariantLibraryOption
    {
        $variantLibraryOption = $this->variantLibraryOptions->firstWhere('id', $id);

        if (!$variantLibraryOption)
            $variantLibraryOption = VariantLibraryOption::findOrFail($id);

        return $variantLibraryOption;
    }


    protected function makeOption(array $combinations): array
    {
        $names_ar = [$this->getProductName()];
        $names_en = [$this->getProductName()];

        foreach ($combinations as $option) {
            $variantLibraryOption = $this->getVariantLibraryOptionByID($option);
            $names_ar[] = $variantLibraryOption->name_ar;
            $names_en[] = $variantLibraryOption->name_en;
        }

        $name_ar = implode(' - ', $names_ar);
        $name_en = implode(' - ', $names_en);
        $name = implode(' - ', app()->getLocale() == "ar" ? $names_ar : $names_en);

        return $this->buildItem(Str::uuid()->toString(), $name_ar, $name_en, $name, $combinations, false, self::getSku(), 0);
    }

    protected function getProductName(): string
    {
        if ($this->product)
            return $this->product->name;

        return $this->livewire->data['name'] ?? "";
    }

    protected function getSku(): string
    {
        if ($this->livewire->data['sku'])
            return $this->livewire->data['sku'] . "." . random_int(111111111, 999999999);

        return random_int(100000000, 999999999);
    }

    protected function getWarehouseId(): ?int
    {
        return Warehouse::getMainWarehouse()?->id;

//        if ($this->product)
//            return $this->product->warehouse_id;
//
//        return $this->livewire->data['warehouse_id'] ?? Warehouse::getMainWarehouse()?->id;
    }

    protected function getExistingVariantByCombination($combinations): ?ProductVariant
    {
        if ($this->product) {
            $existItem = $this->product->variants->filter(function ($existItem) use ($combinations) {
//                dd($existItem, $existItem->variant_library_options_ids, $combinations, array_diff($existItem->variant_library_options_ids, $combinations), array_diff($combinations, $existItem->variant_library_options_ids), array_diff($existItem->variant_library_options_ids, $combinations) === array_diff($combinations, $existItem->variant_library_options_ids));

                $array1 = $existItem->variant_library_options_ids;
                $array2 = $combinations;

                $exist = array_diff($array1, $array2) == array_diff($array2, $array1);
//                fns()->sendWarning($existItem->id, $exist);
                return $exist;
//                if ($existItem->id == 7 and $array2 = [1])
//                    dd($array1, $array2, array_diff($array1, $array2) == array_diff($array2, $array1));
//                    return (serialize($array1) === serialize($array2));
////                return true;// array_diff($existItem->variant_library_options_ids, $combinations) == array_diff($combinations, $existItem->variant_library_options_ids);
            })->first();

//            dd($existItem);

            return $existItem;
        }
        return null;
    }

    protected function buildItem(string $uuid, string $name_ar, string $name_en, string $name,
                                 array  $combinations, bool $unlimited_qty, string $sku, $qty): array
    {

        $new_item = true;
        $unit_cost = null;
        $price = null;
        $discount_price = null;

        //$variantExists
        $variant = $this->getExistingVariantByCombination($combinations);

        if ($variant) {
            $new_item = false;
            $name_ar = $variant->name_ar;
            $name_en = $variant->name_en;
            $name = $variant->name;
            $unlimited_qty = $variant->unlimited_qty;
            $warehouse_id = $variant->warehouse_id ?? self::getWarehouseId();
            $sku = $variant->sku;
            $unit_cost = (is_number($variant->unit_cost) and $variant->unit_cost > 0) ? number_format($variant->unit_cost, currency_decimals(), '.', '') : null;
            $price = (is_number($variant->price) and $variant->price > 0) ? number_format($variant->price, currency_decimals(), '.', '') : null;
            $discount_price = (is_number($variant->discount_price) and $variant->discount_price > 0) ? number_format($variant->discount_price, currency_decimals(), '.', '') : null;
            $qty = $variant->qty;
        }
        $item[$uuid] = [
            'new_item' => $new_item,
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'name' => $name,
            'tenant_id' => $this->tenant_id,
            'variant_library_options_ids' => $combinations,
            'warehouse_id' => $warehouse_id ?? self::getWarehouseId(),
            'unlimited_qty' => $unlimited_qty,
            'sku' => $sku,
            'unit_cost' => $unit_cost,
            'price' => $price,
            'discount_price' => $discount_price,
            'qty' => $qty
        ];

//        if(count($combinations) > 1)
//            dd($combinations, $variant, $item);
//        dd($item);
        return $item;
    }

    protected function combinations($arrays, $i = 0)
    {
        if (!isset($arrays[$i])) {
            return array();
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = self::combinations($arrays, $i + 1);

        $result = array();

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ?
                    array_merge(array($v), $t) :
                    array($v, $t);
            }
        }

        return $result;
    }
}

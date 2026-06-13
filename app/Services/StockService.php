<?php


namespace App\Services;


use App\Models\Category;
use App\Models\Invoice;
use App\Models\ItemPrice;
use App\Models\ItemStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StockService
{
    protected $tenant_id;

    public static function instance()
    {
        return new self();
    }

    public function tenant($tenant_id): self
    {
        $this->tenant_id = $tenant_id;
        return $this;
    }

    public function hasStocks(Model $model): bool
    {
        return $model->stocks->isNotEmpty();
    }

    public function getAvailableStock(?Model $model): int
    {
        if (!$model)
            return 0;

        $negative_stock = -1 * abs($model->negative_stock);
        return $model->stocks->sum('available') + $negative_stock;
    }

    public function takeStockFromSalesInvoice(Invoice $invoice): array
    {
        if ($invoice->type != "sales") {
            throw new \Exception('Invalid invoice type');
        }

        $taken_from_stocks = [];

        $warehouses = Warehouse::orderBy('pulling_stock_priority')->get();

        $moveQty = false;

        foreach ($invoice->items as $invoiceItem) {
            if (count($invoiceItem->stocks ?? []) > 0)
                continue;

            $taken_qty = 0;
            $name = $invoiceItem->name;
            if ($invoiceItem->product_variant_id) {
                $item = ProductVariant::findOrFail($invoiceItem->product_variant_id);
                $itemStocks = ItemStock::where('item_id', $invoiceItem->product_variant_id)->where('item_type', ProductVariant::class)->get();
            } else {
                $item = Product::with(['availableStocks'])->find($invoiceItem->product_id);
                $itemStocks = $item->availableStocks->sortBy(function ($item) use ($warehouses) {
                    return array_search($item->warehouse_id, $warehouses->pluck('id')->toArray());
                });
            }

            $requestedQty = $invoiceItem->qty;

            $availableQty = $itemStocks->sum(function ($i) {
                return $i->available;
            });


            $tenant = get_tenant();

            if ($tenant->store_enable_stock_tracking){
                if ($requestedQty > $availableQty) {
                    throw new \Exception(__('fields.requested_qty_out_of_stock', ['item' => $name, 'qty' => $requestedQty]));
                }
            }else{
                $item->increment('negative_stock', $requestedQty);
            }

            $taken_from_stocks = [];

            //req = 15, ava = 10

            //f-loop taken_qty = 0: requested_qty = 22||| 15
            //s-
            if($tenant->store_enable_stock_tracking){
                foreach ($itemStocks as $stock) {
                    if ($taken_qty < $requestedQty) {
                        //0                    320           100                 320             -   0      ? 100
                        // 100                  320           300                 320                100     ? 300
                        $take_qty_from_stock = min(($requestedQty - $taken_qty), $stock->available);

                        if ($take_qty_from_stock > $stock->available)
                            throw new \Exception('Failed while decrementing stock');

                        if ($moveQty) {
                            ItemStock::where('id', $stock->id)->increment('qty_moved', $take_qty_from_stock);
                        } else {
                            ItemStock::where('id', $stock->id)->increment('qty_out', $take_qty_from_stock);
                        }

                        $taken_qty = $taken_qty + $take_qty_from_stock;

                        $taken_from_stocks[] = $stock->id;

                    }
                }

                $invoiceItem->update(['stocks' => $taken_from_stocks]);
            }

        }

        return $taken_from_stocks;
    }

}

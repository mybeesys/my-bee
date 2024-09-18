<?php

    namespace App\Filament\Tenant\Resources\StockMovementResource\Pages;

    use App\Filament\Tenant\Resources\StockMovementResource;
    use App\Models\ItemStock;
    use App\Models\Product;
    use App\Models\StockMovement;
    use Filament\Notifications\Notification;
    use Filament\Pages\Actions;
    use Filament\Resources\Pages\ManageRecords;
    use Illuminate\Support\Facades\DB;

    class ManageStockMovements extends ManageRecords
    {
        protected static string $resource = StockMovementResource::class;

        protected function getActions(): array
        {
            return [
                Actions\CreateAction::make()->action(function (array $data) {

                    $data['item_id'] = $data['product_id'];
                    $data['item_type'] = Product::class;
                    $data['date'] = now();
                    $data['user_id'] = auth()->id();
                    $data['target_warehouse_post_movement_qty'] = $data['target_warehouse_pre_movement_qty'] - $data['qty'];
                    $data['destination_warehouse_post_movement_qty'] = $data['destination_warehouse_pre_movement_qty'] + $data['qty'];

                    unset($data['product_id']);
                    unset($data['max_qty']);

                    try {
                        DB::beginTransaction();

                        $product = Product::find($data['item_id']);

                        $data['stocks'] = $product->takeStock($data['target_warehouse_id'], $data['qty'], true, true);

                        foreach ($data['stocks'] as $stock)
                        {
                            ItemStock::create(
                                [
                                    'type' => 'moved',
                                    'item_id' => $product->id,
                                    'item_type' => Product::class,
                                    'warehouse_id' => $data['destination_warehouse_id'],
                                    'stock_id' => $stock['stock_id'],
                                    'user_id' => auth()->id(),
                                    'date' => now(),
                                    'qty_in' => $stock['taken_from_stock'],
                                    'qty_out' => 0,
                                    'currency_iso_code' => setting('main_currency', 'SAR'),
                                    'unit_cost_sdg' => $stock['unit_cost_sdg'],
                                    'unit_cost_usd' => $stock['unit_cost_usd'],
                                ]
                            );
                        }


                        $data['stocks'] = collect($data['stocks'])->pluck('stock_id')->toArray();

//                        dd($data);

                        StockMovement::create($data);

                        DB::commit();

                        Notification::make()
                            ->title(__('fields.record_added_alert'))
                            ->success()
                            ->send();

                    } catch (\Exception $exception) {
                        DB::rollBack();
                        report($exception);
                        Notification::make()
                            ->title(__('fields.alert_info'))
                            ->body($exception->getMessage())
                            ->warning()
                            ->persistent()
                            ->send();
                        dd($exception);

                    }
                }),
            ];
        }
    }

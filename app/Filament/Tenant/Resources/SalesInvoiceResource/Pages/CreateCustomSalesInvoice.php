<?php

namespace App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;

use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\Acc4;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceAdditionalCost;
use App\Models\InvoiceItem;
use App\Models\ItemStock;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCustomSalesInvoice extends Page implements HasActions
{
    use InteractsWithForms, InteractsWithFormActions;

    protected static string $resource = SalesInvoiceResource::class;

    protected static string $view = 'filament.resources.sales-invoice-resource.pages.create-custom-sales-invoice';

    protected static ?string $title = "Add invoice";

    public $no, $date, $initial_invoice, $client_id, $exchange_rate, $items, $payments, $additional_costs, $total_price_sdg, $total_price_usd;


    public function mount(): void
    {
        static::authorizeResourceAccess();

        $this->form->fill([
            'no' => generate_invoice_no(),
            'date' => now(),
            'exchange_rate' => ex_rate(),
            'total_price_sdg' => 0,
            'total_price_usd' => 0,
            'items' => [
                [
                ],
            ],
            'additional_costs' => []
        ]);

        $unavailable = Product::doesntHave('lastPrice')->orDoesntHave('availableStocks')->count();

        Notification::make()
            ->title(__('fields.alert_info'))
            ->body(__('fields.unavailable_items_alert', [':count' => $unavailable, 'عدد:' => $unavailable]))
            ->warning()
            ->persistent()
            ->send();

    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema($this->getFormSchema())
        ];
    }

    public function getFormSchema(string $layout = Card::class): array
    {
        return [
            Group::make()
                ->schema([
                    $layout::make()
                        ->schema([
                            TextInput::make('no')
                                ->afterStateHydrated(fn(Set $set) => $set('no', generate_invoice_no()))
                                ->label(__('fields.invoice_no'))
                                ->disabled()
                                ->required(),

                            DatePicker::make('date')
                                ->label(__('fields.date'))
                                ->withoutSeconds()
                                ->minDate(now()->subDays(30))
                                ->maxDate(now())
                                ->default(now())
                                ->required()
                                ->displayFormat('d/m/Y'),

                            Select::make('client_id')
                                ->label(__('fields.client'))
                                ->searchable()
                                ->afterStateHydrated(function ($state) {
                                })
                                ->options(Client::dropdown(false)),


                            TextInput::make('exchange_rate')
                                ->reactive()
                                ->afterStateUpdated(function ($state) {
                                    if ($state) {
                                        foreach ($this->additional_costs as $key => $additional_cost) {
                                            $this->additional_costs[$key]['cost_usd'] = number_format($this->additional_costs[$key]['cost_sdg'] / $state, 2, '.');
                                        }
                                        $this->calculate();
                                    }
                                })
                                ->label(__('fields.exchange_rate'))
                                ->numeric()
                                ->required(),

                        ])->columns(4),


                    $layout::make()
                        ->schema([
                            Toggle::make('initial_invoice')
                                ->reactive()
                                ->label(__('fields.initial_invoice'))
                                ->inline(false)
                                ->helperText(function ($state) {
                                    if ($state)
                                        return __('fields.initial_invoice_statement');
                                }),
                        ]),

                    $layout::make()
                        ->schema([
                            Repeater::make('items')
                                ->label(__('fields.sales'))
                                ->schema([

                                    Hidden::make('stock_id'),

                                    Select::make('item_id')
                                        ->reactive()
                                        ->label(__('fields.product'))
                                        ->searchable()
                                        ->options(Product::asOptions(true, true))
                                        ->required()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            $set('warehouse_id', null);
                                            $set('qty', null);
                                            $set('cost_sdg', null);
                                            $set('cost_usd', null);
                                            $set('total_sdg', null);
                                            $set('total_usd', null);
                                            if ($state) {

                                                $item = Acc4::with('lastPrice')->find($state);

                                                if (!$item) {
                                                    Notification::make()
                                                        ->title("Item not found")
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }
                                                $lastPrice = $item->lastPrice;

                                                if (!$lastPrice) {
                                                    Notification::make()
                                                        ->title(__('fields.product_not_priced_alert'))
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                $qty = $get('qty');

                                                if (is_int($qty)) {
                                                    $price_sdg = number_format($lastPrice->sdg_price, 2, '.', '');
                                                    $price_usd = number_format($lastPrice->usd_price, 2, '.', '');

                                                    $set('cost_sdg', $price_sdg);
                                                    $set('cost_usd', $price_usd);
                                                }

                                            } else {
                                                $set('cost_sdg', null);
                                                $set('cost_usd', null);
                                            }

                                            $this->calculate();

                                        }),

                                    Select::make('warehouse_id')
                                        ->reactive()
                                        ->disabled(fn(Get $get) => $get('item_id') === null)
                                        ->label(__('fields.warehouse'))
                                        ->options(function (Get $get) {
                                            $item_id = $get('item_id');

                                            if (!$item_id)
                                                return [];

                                            $acc4 = Acc4::with('item')->find($item_id);

                                            $data = Warehouse::hasProduct($acc4->item->id)->pluck('name', 'id');

                                            if ($data->isEmpty())

                                                Notification::make()
                                                    ->title("No stock available in all warehouses")
                                                    ->warning()
                                                    ->send();

                                            return $data;
                                        })
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            $item_id = $get('item_id');

                                            if (!$state or !$item_id)
                                                return;
                                            $acc4 = Acc4::with('item', 'lastPrice')->find($item_id);

                                            $ava = ItemStock::whereItemType(Product::class)
                                                ->whereItemId($acc4->item->id)->whereWarehouseId($state)->get()->sum(function ($i) {
                                                    return $i->available;
                                                });


                                            $set('qty', $ava);

                                            $price_sdg = number_format($acc4->lastPrice->sdg_price, 2, '.', '');
                                            $price_usd = number_format($acc4->lastPrice->usd_price, 2, '.', '');

                                            $set('cost_sdg', $price_sdg);
                                            $set('cost_usd', $price_usd);

                                            $set('total_sdg', number_format($price_sdg * $ava, 2));

                                            $set('total_usd', number_format($price_usd * $ava, 2));

                                            $this->calculate();
                                        })
                                        ->required(),

                                    TextInput::make('qty')
                                        ->reactive()
                                        ->label(__('fields.qty'))
                                        ->disabled(fn(Get $get) => $get('warehouse_id') === null)
                                        ->numeric()
                                        ->minValue(1)
                                        ->afterStateUpdated(function (TextInput $component, Set $set, Get $get, $state) {
                                            if ($state) {

                                                $item_id = $get('item_id');
                                                $warehouse_id = $get('warehouse_id');

                                                if (!$item_id)
                                                    $component->state("");

                                                $acc4 = Acc4::with('item.availableStocks')->find($item_id);

                                                $available_in_warehouse = ItemStock::whereItemType(Product::class)
                                                    ->whereItemId($acc4->item->id)->whereWarehouseId($warehouse_id)->get()->sum(function ($i) {
                                                        return $i->available;
                                                    });


                                                $component->maxValue(1);
                                                $component->maxValue($available_in_warehouse);

                                                if ($state > $available_in_warehouse) {
                                                    $component->state($available_in_warehouse);
                                                    $state = $available_in_warehouse;
                                                }


                                                $item = Acc4::with('lastPrice')->find($get('item_id'));

                                                if (!$item) {
                                                    Notification::make()
                                                        ->title("Item not found")
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                $lastPrice = $item->lastPrice;

                                                if (!$lastPrice) {
                                                    Notification::make()
                                                        ->title(__('fields.product_not_priced_alert'))
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                $price_sdg = number_format($lastPrice->sdg_price, 2, '.', '');
                                                $price_usd = number_format($lastPrice->usd_price, 2, '.', '');

                                                $set('cost_sdg', $price_sdg);
                                                $set('cost_usd', $price_usd);

                                                $set('total_sdg', number_format($price_sdg * $state, 2));

                                                $set('total_usd', number_format(($price_sdg * $state) / $this->exchange_rate, 2));

                                            }

                                            $this->calculate();

                                        })
                                        ->required(),
                                    TextInput::make('cost_sdg')
                                        ->disabled(1)
                                        ->reactive()
                                        ->label(__('fields.unit_cost_sdg'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(9000000)
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {

                                                $set('cost_usd', number_format($state / $this->exchange_rate, 2, '.', ''));

                                                //update total_sdg

                                                $qty = $get('qty');
                                                $unit_cost_sdg = $state;
                                                $unit_cost_usd = $get('cost_usd');

                                                if ($qty and is_numeric($qty) and $qty > 0) {
                                                    $set('total_sdg', number_format($qty * $unit_cost_sdg, 2, '.', ''));

                                                    $set('total_usd', number_format($qty * $unit_cost_usd, 2, '.', ''));
                                                }
                                            }
                                            $this->calculate();
                                        })
                                        ->required(),
                                    TextInput::make('cost_usd')
                                        ->disabled(1)
                                        ->reactive()
                                        ->label(__('fields.unit_cost_usd'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(9000000)
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {

                                                $set('cost_sdg', number_format($state * $this->exchange_rate, 2, '.', ''));

                                                //update total_usd

                                                $qty = $get('qty');
                                                $unit_cost_usd = $state;
                                                $unit_cost_sdg = $get('cost_sdg');

                                                if ($qty and is_numeric($qty) and $qty > 0) {
                                                    $set('total_usd', number_format($qty * $unit_cost_usd, 2, '.', ''));

                                                    $set('total_sdg', number_format(($state * $this->exchange_rate) * $qty, 2, '.', ''));
                                                }
                                            }
                                            $this->calculate();
                                        })
                                        ->required(),
                                    TextInput::make('total_sdg')
                                        ->reactive()
                                        ->disabled(true)
                                        ->dehydrated(false)
                                        ->label(__('fields.total_sdg')),
                                    TextInput::make('total_usd')
                                        ->disabled(true)
                                        ->dehydrated(false)
                                        ->label(__('fields.total_usd')),
                                ])
                                ->createItemButtonLabel(__('fields.add'))
                                ->grid(1)
                                ->collapsible()
                                ->defaultItems(1)
                                ->columns(4),
                        ]),


                    $layout::make()
                        ->schema([
                            Repeater::make('additional_costs')
                                ->label(__('fields.additional_costs'))
                                ->schema([
                                    TextInput::make('statement')
                                        ->label(__('fields.statement'))
                                        ->required()
                                        ->columnSpan(2),
                                    TextInput::make('cost_sdg')
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {
                                                $set('cost_usd', number_format($state / $this->exchange_rate, 2, '.', ''));
                                            }
                                        })
                                        ->reactive()
                                        ->label(__('fields.cost_sdg'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(9000000)
                                        ->required(),
                                    TextInput::make('cost_usd')
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state and (is_numeric($state) or is_float($state)) and $state > 0) {
                                                $set('cost_sdg', number_format($state * $this->exchange_rate, 2, '.', ''));
                                            }
                                        })
                                        ->reactive()
                                        ->label(__('fields.cost_usd'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(9000000)
                                        ->required(),
                                ])
                                ->createItemButtonLabel(__('fields.add'))
                                ->grid(1)
                                ->collapsible()
                                ->defaultItems(1)
                                ->columns(4),
                        ]),
                    $layout::make()
                        ->schema([
                            TextInput::make('total_price_sdg')
                                ->reactive()
                                ->extraAttributes(['class' => 'text-success-700'])
                                ->dehydrated(false)
                                ->default('0')
                                ->disabled()
                                ->label(__('fields.total_price_sdg')),
                            TextInput::make('total_price_usd')
                                ->reactive()
                                ->extraAttributes(['class' => 'text-success-700'])
                                ->dehydrated(false)
                                ->default('0')
                                ->disabled()
                                ->label(__('fields.total_price_usd')),
                        ])->columns(2),

                ])->columnSpan([
                    'sm' => 3,
                ]),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label(__('fields.save'))
                ->action(function () {

                    $total_price_sdg = Str::replace(['SDG', ' ', ',', '.'], [''], $this->total_price_sdg);
                    $total_price_usd = Str::replace(['USD', ' ', ',', '.'], [''], $this->total_price_usd);

                    $this->validate([
                        'no' => ['required'],
                        'date' => ['required'],
                        'client_id' => ['nullable', 'exists:clients,id'],
                        'exchange_rate' => ['required', 'min:1', 'max:5000000'],
                        'items' => ['required', 'array', 'min:1'],
                        'additional_costs' => ['sometimes', 'array'],

                    ], ['items.required' => __('fields.please_add_purchases')], [$this->no, $this->date, $this->client_id, $this->exchange_rate, $this->items, $this->additional_costs]);


                    $item_pass = true;

                    foreach ($this->items as $item) {

                        if ($item['item_id'] == null)
                            $item_pass = false;

                        if ($item['warehouse_id'] == null)
                            $item_pass = false;

                        if ($item['qty'] == null)
                            $item_pass = false;

                        if ($item['cost_sdg'] == null)
                            $item_pass = false;

                        if ($item['cost_usd'] == null)
                            $item_pass = false;

//                        if (intval($total_price_sdg / $this->exchange_rate) != intval($total_price_usd)) {
//                            dd(intval($total_price_sdg / $this->exchange_rate) , intval($total_price_usd));
//                            Filament::notify('danger', 'Invalid operation, please reload the page and try again.');
//                            $item_pass = false;
//                            return;
//                        }
                    }


                    foreach ($this->additional_costs as $item) {
                        if ($item['cost_sdg'] == null or $item['cost_sdg'] < 1)
                            $item_pass = false;

                        if ($item['cost_usd'] == null)
                            $item_pass = false;

                        if ($item['statement'] == null or $item['cost_sdg'] < 1)
                            $item_pass = false;
                    }


                    if (!$item_pass) {
                        Notification::make()
                            ->title(__('fields.all_transaction_fields_required'))
                            ->warning()
                            ->send();
                        return;
                    }

                    try {
                        DB::beginTransaction();

                        $purchases_amount_sdg = 0;

                        foreach ($this->items as $item) {
                            $purchases_amount_sdg += $item['qty'] * $item['cost_sdg'];
                        }


                        $ann_client = Client::with('acc4')->firstWhere('name', 'Unknown client');

                        if ($this->client_id == null)
                            abort_if(null == $ann_client, 404, "Failed to process invoice for anonymous client, main account is missing");


                        $inv = Invoice::create(
                            [
                                'no' => generate_invoice_no(),
                                'type' => 'sales',
                                'for' => 'client',
                                'user_id' => auth()->id(),
                                'client_id' => $this->client_id ?? $ann_client->id,
                                'invoice_status_id' => $this->initial_invoice ? sales_invoice_status_initial() : sales_invoice_status_pending_payment(),
                                'date' => $this->date,
                                'exchange_rate' => $this->exchange_rate,
                            ]
                        );
                        foreach ($this->items as $item) {

                            $product = Acc4::with('item.availableStocks')->find($item['item_id'])->item;

//                                $availableQty = $product->availableStocks->sum(function ($i) {
//                                    return $i->available;
//                                });
//
//
//                                if ($availableQty < $item['qty']) {
//                                    $qty = $item['qty'];
//                                    throw new \Exception("Requested quantity ($qty) is unavailable");
//                                }
//
//                                $stocks_ids = null;
//
//                                if($this->initial_invoice == false)
//                                {
//                                    $stocks_ids = $product->takeStock($item['warehouse_id'], $item['qty']);
//                                }

                            //validate available and throw exception if not
                            $rs = $product->hasAvailableQty($item['qty'], $item['warehouse_id'], true);

                            InvoiceItem::create(
                                [
                                    'invoice_id' => $inv->id,
                                    'product_id' => $product->id,
                                    'warehouse_id' => $item['warehouse_id'],
                                    'qty' => $item['qty'],
                                    'price_sdg' => $item['cost_sdg'],
                                    'price_usd' => $item['cost_usd'],
                                    'stocks' => null,
                                    'exchange_rate' => $this->exchange_rate,
                                ]
                            );
                        }


                        foreach ($this->additional_costs as $item) {
                            InvoiceAdditionalCost::create(
                                [
                                    'invoice_id' => $inv->id,
                                    'statement' => $item['statement'],
                                    'cost_sdg' => $item['cost_sdg'],
                                    'cost_usd' => $item['cost_usd'],
                                ]
                            );
                        }

                        DB::commit();

                        Notification::make()
                            ->title(__('fields.invoice_saved'))
                            ->success()
                            ->send();

                        $this->redirect(SalesInvoiceResource::getUrl());

                    } catch (\Exception $exception) {
                        DB::rollBack();
                        Notification::make()
                            ->title($exception->getMessage())
                            ->success()
                            ->send();
                    }

                }),
        ];

    }

    public function calculate()
    {
        $total_purchases_sdg = 0;

        foreach ($this->items as $item) {
            if ($item['qty'] ?? null and $item['cost_sdg'] ?? null) {
                $total_purchases_sdg += $item['qty'] * $item['cost_sdg'];
            }
        }

        $this->total_price_sdg = number_format($total_purchases_sdg, 2) . ' SDG';
        $this->total_price_usd = number_format($total_purchases_sdg / $this->exchange_rate, 2) . ' USD';

    }
}

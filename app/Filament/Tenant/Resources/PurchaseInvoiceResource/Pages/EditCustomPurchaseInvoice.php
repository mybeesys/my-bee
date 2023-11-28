<?php

    namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

    use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
    use App\Models\Acc4;
    use App\Models\Invoice;
    use App\Models\InvoiceAdditionalCost;
    use App\Models\InvoiceItem;
    use App\Models\Product;
    use App\Models\Supplier;
    use App\Models\Warehouse;
    use Filament\Actions\Action;
    use Filament\Actions\Contracts\HasActions;
    use Filament\Forms\Components\Card;
    use Filament\Forms\Components\DatePicker;
    use Filament\Forms\Components\Group;
    use Filament\Forms\Components\Repeater;
    use Filament\Forms\Components\Select;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Get;
    use Filament\Forms\Set;
    use Filament\Notifications\Notification;
    use Filament\Pages\Concerns\InteractsWithFormActions;
    use Filament\Resources\Pages\Page;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Str;

    class EditCustomPurchaseInvoice extends Page implements HasActions
    {
        use InteractsWithForms, InteractsWithFormActions;

        protected static string $resource = PurchaseInvoiceResource::class;

        protected static string $view = 'filament.resources.purchase-invoice-resource.pages.create-custom-purchase-invoice';
        protected static ?string $title = 'تعديل الفاتورة';

        public $no, $date, $supplier_id, $exchange_rate, $items, $additional_costs, $total_price_sdg, $total_price_usd;

        public $invoice_id, $invoice, $locked = false, $invoice_locked_statement;


        public function mount(): void
        {
            static::authorizeResourceAccess();

            $this->invoice_id = \Route::current()->parameter('record');

            $this->invoice = Invoice::findOrFail($this->invoice_id);

            $this->invoice->loadMissing([
                'invoiceStatus',
                'items.product.acc4',
                'items.warehouse',
                'stocks.warehouse',
                'payments',
                'client',
                'representative',
                'supplier',
                'user',
                'reviewedBy',
                'additionalCosts',
            ]);

            $this->no = $this->invoice->no;
            $this->date = $this->invoice->date;
            $this->supplier_id = $this->invoice->supplier_id;
            $this->exchange_rate = $this->invoice->exchange_rate;
            $this->total_price_sdg = number_format($this->invoice->getItemsCost('SDG'), 2) . ' SDG';
            $this->total_price_usd = number_format($this->invoice->getItemsCost('USD'), 2) . ' USD';


            foreach ($this->invoice->items as $item) {

//                dd($item);
                $data = [
                    'item_id' => $item->product->acc4->code,
                    'warehouse_id' => $item->warehouse_id,
                    'expiration_date' => $item->expiration_date,
                    'qty' => $item->qty,
                    'cost_sdg' => number_format($item->price_sdg, 0, '.', ''),
                    'cost_usd' => number_format($item->price_usd, 2, '.', ''),
                    'total_sdg' => number_format($item->price_sdg * $item->qty, 2, '.', ''),
                    'total_usd' => number_format($item->price_usd * $item->qty, 2, '.', ''),
                ];

                $this->items[Str::random(9)] = $data;
            }


            foreach ($this->invoice->additionalCosts as $additionalCost) {
                $data = [
                    'statement' => $additionalCost->statement,
                    'cost_sdg' => $additionalCost->cost_sdg,
                    'cost_usd' => $additionalCost->cost_usd,
                ];
                $this->additional_costs[Str::random(9)] = $data;
            }

            $this->form->fill([
                'locked' => $this->invoice->locked_at == null ? false : true,
                'invoice_locked_statement' => __('fields.invoice_locked_statement'),
                'no' => $this->no,
                'date' => $this->date,
                'supplier_id' => $this->supplier_id,
                'exchange_rate' => $this->exchange_rate,
                'total_price_sdg' => $this->total_price_sdg,
                'total_price_usd' => $this->total_price_usd,
                'items' => $this->items,
                'additional_costs' => $this->additional_costs
            ]);

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
                            ->visible(function () {
                                return $this->locked;
                            })
                            ->schema([
                                TextInput::make('invoice_locked_statement')
                                    ->dehydrated(false)
                                    ->label(__('fields.invoice_locked'))
                                    ->extraAttributes(['class' => 'text-danger-700'])
                                    ->disabled()
                                    ->required(),
                            ]),
                        $layout::make()
                            ->disabled(function () {
                                return $this->locked;
                            })
                            ->schema([
                                TextInput::make('no')
                                    ->disabled(1)
                                    ->afterStateHydrated(fn(Set $set) => $set('no', generate_invoice_no()))
                                    ->label('رقم الفاتورة')
                                    ->disabled()
                                    ->required(),

                                DatePicker::make('date')
                                    ->label('التاريخ')
                                    ->withoutSeconds()
                                    ->minDate(now()->subDays(30))
                                    ->maxDate(now())
                                    ->default(now())
                                    ->required()
                                    ->displayFormat('d/m/Y'),

                                Select::make('supplier_id')
                                    ->label(__('fields.supplier'))
                                    ->searchable()
                                    ->afterStateHydrated(function ($state) {
                                        if (Supplier::count() == 0)
                                            Notification::make()
                                                ->title(__('fields.alert_info'))
                                                ->body(__('fields.please_add_supplier_to_create_purchases_invoice'))
                                                ->warning()
                                                ->persistent()
                                                ->send();
                                    })
                                    ->options(Supplier::pluck('name', 'id'))
                                    ->required(),

                                TextInput::make('exchange_rate')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state) {
                                        if ($state) {
//                                            $this->items = [[]];
//                                            $this->additional_costs = [[]];

                                            foreach ($this->items as $key => $item)
                                            {
                                                $this->items[$key]['cost_usd'] = number_format($this->items[$key]['cost_sdg'] / $state, 2, '.');
                                                $this->items[$key]['total_usd'] = number_format($this->items[$key]['cost_usd'] * $this->items[$key]['qty'], 2, '.');
                                            }

                                            foreach ($this->additional_costs as $key => $additional_cost)
                                            {
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
                                Repeater::make('items')
                                    ->disabled(function () {
                                        return $this->locked;
                                    })
                                    ->label(__('fields.purchases'))
                                    ->schema([
                                        Select::make('item_id')
                                            ->label(__('fields.product'))
                                            ->searchable()
                                            ->options(Acc4::asOptions(Product::class))
                                            ->required(),

                                        Select::make('warehouse_id')
                                            ->label(__('fields.warehouse'))
                                            ->searchable()
                                            ->options(Warehouse::pluck('name', 'id'))
                                            ->required(),

                                        TextInput::make('qty')
                                            ->reactive()
                                            ->label(__('fields.qty'))
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(9000000)
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                if ($state) {

                                                    $qty = $get('qty');
                                                    $unit_cost_sdg = $get('cost_sdg');
                                                    $unit_cost_usd = $get('cost_usd');

                                                    if ($qty and is_numeric($qty) and $qty > 0) {
                                                        if ($unit_cost_sdg and (is_numeric($unit_cost_sdg) or is_float($unit_cost_sdg)) and $unit_cost_sdg > 0) {
                                                            $set('total_sdg', number_format($qty * $unit_cost_sdg, 2, '.', ''));
                                                        }

                                                        if ($unit_cost_sdg and (is_numeric($unit_cost_sdg) or is_float($unit_cost_sdg)) and $unit_cost_sdg > 0) {
                                                            $set('total_usd', number_format($qty * $unit_cost_usd, 2, '.', ''));
                                                        }
                                                    }
                                                }

                                                $this->calculate();

                                            })->required(),

                                        DatePicker::make('expiration_date')
                                            ->label(__('fields.expiration_date'))
                                            ->withoutSeconds()
                                            ->minDate(now())
                                            ->maxDate(now()->addYears(20))
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('cost_sdg')
                                            ->reactive()
                                            ->label(__('fields.unit_cost_sdg'))
                                            ->numeric()
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
                                    ->disabled(function () {
                                        return $this->locked;
                                    })
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
                Action::make('save')
                    ->label(__('fields.save'))
                    ->visible(function () {
                        return !$this->locked;
                    })
                    ->action(function () {

                        if ($this->locked)
                            return;

                        $total_price_sdg = Str::replace(['SDG', ' ', ',', '.'], [''], $this->total_price_sdg);
                        $total_price_usd = Str::replace(['USD', ' ', ',', '.'], [''], $this->total_price_usd);

                        $this->validate([
                            'no' => ['required'],
                            'date' => ['required'],
                            'supplier_id' => ['required'],
                            'exchange_rate' => ['required', 'min:1', 'max:5000000'],
                            'items' => ['required', 'array', 'min:1'],
                            'additional_costs' => ['sometimes', 'array'],

                        ], ['items.required' => __('fields.please_add_purchases')], [$this->no, $this->date, $this->supplier_id, $this->exchange_rate, $this->items, $this->additional_costs]);


                        $item_pass = true;

                        foreach ($this->items as $item) {

                            if ($item['qty'] == null)
                                $item_pass = false;

                            if ($item['warehouse_id'] == null)
                                $item_pass = false;

                            if ($item['item_id'] == null)
                                $item_pass = false;

                            if ($item['cost_sdg'] == null)
                                $item_pass = false;

                            if ($item['cost_usd'] == null)
                                $item_pass = false;

//                            if (intval($total_price_sdg / $this->exchange_rate) != $total_price_usd) {
//                                dd(intval($total_price_sdg / $this->exchange_rate) , $total_price_usd);
//                                $item_pass = false;
//                                return;
//                            }
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

                        if ($this->invoice->locked_at) {
                            Notification::make()
                                ->title(__('fields.invoice_locked_statement'))
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

                            $inv = Invoice::findOrFail($this->invoice_id);

                            $inv->items()->delete();
                            $inv->additionalCosts()->delete();

                            $ex_rate = $this->exchange_rate;

                            $inv->update(
                                [
                                    'type' => 'purchases',
                                    'for' => 'supplier',
//                                    'user_id' => auth()->id(),
                                    'supplier_id' => $this->supplier_id,
                                    'invoice_status_id' => purchase_invoice_status_pending(),
                                    'date' => $this->date,
                                    'exchange_rate' => $this->exchange_rate,
                                ]
                            );
                            foreach ($this->items as $item) {

                                $product = Acc4::with('item')->find($item['item_id'])->item;

                                InvoiceItem::create(
                                    [
                                        'invoice_id' => $inv->id,
                                        'product_id' => $product->id,
                                        'qty' => $item['qty'],
                                        'warehouse_id' => $item['warehouse_id'],
                                        'price_sdg' => $item['cost_sdg'],
                                        'price_usd' => $item['cost_usd'],
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

//                            PurchaseInvoiceResource::getUrl();
                            $this->redirect(PurchaseInvoiceResource::getUrl());

                        } catch (\Exception $exception) {
                            DB::rollBack();
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

<?php

    namespace App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages;

    use App\Filament\Tenant\Resources\ReceiptVoucherResource;
    use App\Models\Acc4;
    use App\Models\Client;
    use App\Models\Currency;
    use App\Models\Driver;
    use App\Models\Invoice;
    use App\Models\InvoiceAdditionalCost;
    use App\Models\InvoicePayment;
    use App\Models\Order;
    use App\Models\Product;
    use App\Models\ReceiptVoucher;
    use App\Services\AccountingService;
    use Filament\Actions\Contracts\HasActions;
    use Filament\Forms\Components\Card;
    use Filament\Forms\Components\DatePicker;
    use Filament\Forms\Components\FileUpload;
    use Filament\Forms\Components\Group;
    use Filament\Forms\Components\Repeater;
    use Filament\Forms\Components\Select;
    use Filament\Forms\Components\Textarea;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Get;
    use Filament\Forms\Set;
    use Filament\Notifications\Notification;
    use Filament\Pages\Concerns\InteractsWithFormActions;
    use Filament\Resources\Pages\Page;
    use Illuminate\Support\Facades\DB;

    class CreateCustomReceiptVoucher extends Page implements HasActions
    {
        use InteractsWithForms, InteractsWithFormActions;

        protected static string $resource = ReceiptVoucherResource::class;

        protected static string $view = 'filament.resources.receipt-voucher-resource.pages.create-custom-receipt-voucher';

        public $no, $date, $account_id, $invoice_id, $order_id, $order_no, $delivery, $delivery_extra, $client_id, $driver_id, $payments, $total_price_sdg, $total_price_usd;

        public $invoice_total_sdg, $invoice_total_usd, $invoice_paid_sdg, $invoice_paid_usd;

        public $invoice, $order;

        public function getTitle(): string
        {
            return __('fields.receipt_voucher');
        }

        public function mount(): void
        {
            static::authorizeResourceAccess();

            $invoice_id = request('invoice_id', null);
            $order_id = request('order_id', null);

            $delivery_sdg = 0;
            $delivery_usd = 0;

            if ($order_id) {
                $order = Order::with([])->whereId($order_id)->firstOrFail();

                $this->order = $order;
                $this->order_id = $order_id;
                $this->order_no = $order->no;
                $this->delivery = number_format($order->delivery, 0, '', '');
                $this->delivery_extra = 0;
                $delivery_sdg = floatval($this->delivery) + floatval($this->delivery_extra);
            }

            if ($invoice_id) {
                $invoice = Invoice::with(['items', 'payments'])->whereId($invoice_id)->firstOrFail();

                if ($order_id) {
                    $delivery_usd = $delivery_sdg / $invoice->exchange_rate;
                }

                $this->invoice = $invoice;
                $this->client_id = $invoice->client_id;
                $this->invoice_id = $invoice->id;
                $this->invoice = $invoice;
                $this->invoice_total_sdg = number_format($invoice->getItemsCost('SDG') + $delivery_sdg, 2);
                $this->invoice_total_usd = number_format($invoice->getItemsCost('USD') + $delivery_usd, 2);
                $this->invoice_paid_sdg = number_format($invoice->total_paid['sdg'], 2);
                $this->invoice_paid_usd = number_format($invoice->total_paid['usd'], 2);
            }

            $this->form->fill([
                'no' => generate_receipt_voucher(),
                'date' => now(),
                'total_price_sdg' => 0,
                'total_price_usd' => 0,
                'payments' => [
                    [
                    ],
                ],
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
                            ->schema([
                                TextInput::make('no')
//                                    ->afterStateHydrated(fn(Set $set) => $set('no', generate_receipt_voucher()))
                                    ->label(__('fields.voucher_no'))
                                    ->disabled()
                                    ->required(),


                                DatePicker::make('date')
                                    ->label(__('fields.date'))
                                    ->withoutSeconds()
                                    ->minDate(now()->subDays(30))
                                    ->maxDate(now())
                                    ->default(now())
                                    ->displayFormat('d/m/Y'),

                                Select::make('client_id')
                                    ->reactive()
                                    ->searchable()
                                    ->label(__('fields.client'))
                                    ->options(Client::dropdown(false))
                                    ->afterStateUpdated(function ($state, Set $set) {

                                        $set('invoice_id', null);

                                        if ($state) {
                                            $invoices = Invoice::where('client_id', $state)->count();

                                            if ($invoices == 0)
                                                Notification::make()
                                                    ->title("لا توجد فواتير لهذا العميل")
                                                    ->warning()
                                                    ->send();
                                        }
                                    })
                                    ->required(),

                                Select::make('invoice_id')
                                    ->reactive()
                                    ->disabled(fn(Get $get) => $get('client_id') == null)
                                    ->label(__('fields.invoice_no'))
                                    ->options(function (Get $get) {
                                        $client_id = $get('client_id');

                                        if ($client_id) {
                                            return Invoice::dropdownUnpaid($client_id, false);
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $invoice = Invoice::with('items', 'payments')->find($state);

                                            $set('invoice_total_sdg', number_format($invoice->getItemsCost('SDG'), 2));
                                            $set('invoice_total_usd', number_format($invoice->getItemsCost('USD'), 2));
                                            $set('invoice_paid_sdg', number_format($invoice->total_paid['sdg'], 2));
                                            $set('invoice_paid_usd', number_format($invoice->total_paid['usd'], 2));
                                        }
                                    })
                                    ->required(),

                                Select::make('account_id')
                                    ->reactive()
                                    ->label(__('fields.account'))
                                    ->hint(function (Get $get) {
                                        $acc_id = $get('account_id');

                                        if ($acc_id)
                                            return Acc4::find($acc_id)->acc4_code;

                                        return null;
                                    })
                                    ->options(Acc4::whereIn('code', [120100001, 120100002])->pluck('name', 'code'))
                                    ->required(),


                                TextInput::make('order_no')
                                    ->visible(function () {
                                        return $this->order_id != null;
                                    })
                                    ->afterStateHydrated(fn(Set $set) => $set('order_no', $this->order_no))
                                    ->label(__('fields.order_no'))
                                    ->disabled()
                                    ->required(),

                                Select::make('driver_id')
                                    ->visible(function () {
                                        return $this->order_id != null;
                                    })
                                    ->label(__('fields.driver'))
                                    ->required()
                                    ->searchable()
                                    ->options(Driver::dropdown()),

                                TextInput::make('delivery')
                                    ->visible(function () {
                                        return $this->order_id != null;
                                    })
                                    ->label(__('fields.delivery_price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(50000)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        if ($state != null and (is_number($state) or is_float($state))) {
                                            $delivery_extra = $get('delivery_extra') ?? 0;
                                            $state = $state + $delivery_extra;
                                            $usd = 0;

                                            if ($state > 0) {
                                                $usd = $state / ex_rate();
                                            }
                                            $set('invoice_total_sdg', number_format($this->invoice->getItemsCost('SDG') + $state, 2));
                                            $set('invoice_total_usd', number_format($this->invoice->getItemsCost('USD') + $usd, 2));
                                            $set('invoice_paid_sdg', number_format($this->invoice->total_paid['sdg'], 2));
                                            $set('invoice_paid_usd', number_format($this->invoice->total_paid['usd'], 2));
                                        }
                                    })
                                    ->required(),

                                TextInput::make('delivery_extra')
                                    ->visible(function () {
                                        return $this->order_id != null;
                                    })
                                    ->label(__('fields.additional_delivery_price'))
                                    ->minValue(0)
                                    ->maxValue(50000)
                                    ->required(),

                            ])->columns(5),

                        $layout::make()
                            ->visible(fn(Get $get) => $get('invoice_id') != null)
                            ->schema([
                                TextInput::make('invoice_total_sdg')->label(__('fields.invoice_total_sdg'))->dehydrated(false)->disabled(),
                                TextInput::make('invoice_total_usd')->label(__('fields.invoice_total_usd'))->dehydrated(false)->disabled(),
                                TextInput::make('invoice_paid_sdg')->label(__('fields.invoice_paid_sdg'))->dehydrated(false)->disabled(),
                                TextInput::make('invoice_paid_usd')->label(__('fields.invoice_paid_usd'))->dehydrated(false)->disabled(),

                            ])->columns(4),

                        $layout::make()
                            ->schema([

                                Repeater::make('payments')
                                    ->label(__('fields.payments'))
                                    ->schema([

                                        Select::make('payment_method')
                                            ->label(__('fields.payment_method'))
                                            ->reactive()
                                            ->options([
                                                'cash' => __('fields.cash'),
                                                'mbok' => __('fields.mbok'),
                                                'fawry' => __('fields.fawry'),
                                                'bank-transfer' => __('fields.bank_transfer'),
                                                'cheque' => __('fields.cheque'),
                                            ])
                                            ->required(),

                                        TextInput::make('cheque_holder_name')
                                            ->label(__('fields.cheque_holder_name'))
                                            ->visible(fn(Get $get) => $get('payment_method') == 'cheque')
                                            ->helperText(__('fields.cheque'))
                                            ->required(),

                                        Select::make('cheque_status')
                                            ->label(__('fields.cheque_status'))
                                            ->visible(fn(Get $get) => $get('payment_method') == 'cheque')
                                            ->helperText(__('fields.cheque'))
                                            ->options([
                                                'not-collected' => __('fields.cheque_not_collected'),
                                                'collected' => __('fields.cheque_collected'),
                                            ])
                                            ->required(),

                                        TextInput::make('bank_transfer_reference_no')
                                            ->label(__('fields.ref_no'))
                                            ->visible(fn(Get $get) => $get('payment_method') != null and $get('payment_method') != "cash")
                                            ->helperText(__('fields.transaction_no_for_bank_transfer_or_cheque_no'))
                                            ->required(),


                                        Select::make('currency_id')
                                            ->disabled(fn(Get $get) => $get('payment_method') == null)
                                            ->label(__('fields.currency'))
                                            ->reactive()
                                            ->options(Currency::pluck('name', 'id'))
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                if ($state == 2) {
                                                    $set('amount_sdg', $get('amount') * $get('exchange_rate'));
                                                }
                                            })
                                            ->required(),


                                        TextInput::make('amount')
                                            ->disabled(fn(Get $get) => $get('payment_method') == null)
                                            ->label(__('fields.amount_money'))
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                if ($get('currency_id') == 2) {
                                                    $set('amount_sdg', $state * $get('exchange_rate'));
                                                }
                                            })
                                            ->helperText(function (Get $get) {
                                                $currency_id = $get('currency_id');
                                                $amount = (float)$get('amount');

                                                if ($currency_id == 1 and (is_float($amount) or is_int($amount))) {
                                                    return \Tafqeet::inArabic($amount, 'egp');

                                                } else if ($currency_id == 2 and (is_float($amount) or is_int($amount))) {
                                                    return \Tafqeet::inArabic($amount, 'usd');
                                                }

                                                return null;
                                            })
                                            ->numeric()
                                            ->minValue(1)
                                            ->required(),

                                        TextInput::make('exchange_rate')
                                            ->reactive()
                                            ->visible(fn(Get $get) => $get('currency_id') == 2)
                                            ->label(__('fields.exchange_rate'))
                                            ->numeric()
                                            ->afterStateHydrated(function (TextInput $component, $state) {
                                                $value = setting('finance.sdg.usd.exchange_rate', 0);
                                                $component->state($value);
                                            })
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $set('amount_sdg', $get('amount') * $state);
                                            })
                                            ->required(),

                                        TextInput::make('amount_sdg')
                                            ->disabled(fn(Get $get) => $get('payment_method') == null)
                                            ->reactive()
                                            ->dehydrated(0)
                                            ->disabled(1)
                                            ->visible(fn(Get $get) => $get('currency_id') == 2)
                                            ->label(__('fields.amount_sdg'))
                                            ->helperText(function ($state) {
                                                $state = (float)$state;

                                                if (is_numeric($state) and (is_float($state) or is_int($state))) {
                                                    return \Tafqeet::inArabic($state, 'egp');
                                                }

                                                return null;
                                            })
                                            ->required(),

                                        Textarea::make('statement')
                                            ->disabled(fn(Get $get) => $get('payment_method') == null)
                                            ->label(__('fields.statement'))
                                            ->required(),

                                        FileUpload::make('files')
                                            ->disabled(fn(Get $get) => $get('payment_method') == null)
                                            ->label(__('fields.attach_document'))
                                            ->image()
                                            ->enableReordering()
                                            ->enableOpen()
                                            ->enableDownload()
                                            ->multiple()
                                            ->maxSize(2048)
                                            ->directory('receipt-vouchers'),

                                    ])
                                    ->createItemButtonLabel(__('fields.add'))
                                    ->grid(1)
                                    ->collapsible()
                                    ->defaultItems(1)
                                    ->columns(5),

                            ]),


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
                    ->requiresConfirmation()
                    ->action(function () {
//
//                        $total_price_sdg = Str::replace(['SDG', ' ', ',', '.'], [''], $this->total_price_sdg);
//                        $total_price_usd = Str::replace(['USD', ' ', ',', '.'], [''], $this->total_price_usd);

                        $this->validate([
                            'no' => ['required'],
                            'date' => ['required'],
                            'client_id' => ['required', 'exists:clients,id'],
                            'invoice_id' => ['required', 'exists:invoices,id'],
                            'account_id' => ['required'],
                            'payments' => ['required', 'array', 'min:1'],

                        ], ['items.required' => __('fields.please_add_payments')], [$this->no, $this->date, $this->client_id, $this->invoice_id, $this->payments]);

                        $result = $this->validatePaymentItems($this->payments);



                        if (count($result['errors']) > 0) {
                            Notification::make()
                                ->title(__($result['errors'][0]))
                                ->warning()
                                ->send();
                            return;
                        }


                        try {
                            DB::beginTransaction();

                            $total_paid_sdg = 0;

                            foreach ($this->payments as $item) {
                                if ($item['currency_id'] == 1) {
                                    $total_paid_sdg += $item['amount'];
                                } else {
                                    $total_paid_sdg += floatval($item['amount'] / $item['exchange_rate']);
                                }
                            }

                            $invoice = Invoice::with(['items', 'payments', 'client.acc4'])->find($this->invoice_id);

                            if ($invoice->paid)
                                throw new \Exception("Invoice already paid");


                            $delivery = ($this->delivery ?? 0) + ($this->delivery_extra ?? 0);

                            if (($this->delivery ?? 0) > 0) {

                                InvoiceAdditionalCost::create(
                                    [
                                        'invoice_id' => $invoice->id,
                                        'statement' => "Delivery fees",
                                        'cost_sdg' => $this->delivery,
                                        'cost_usd' => $this->delivery / $invoice->exchange_rate,
                                    ]
                                );

                                $invoice->load('additionalCosts');
                                $invoice->refresh();
                            }

                            if (($this->delivery_extra ?? 0) > 0) {

                                InvoiceAdditionalCost::create(
                                    [
                                        'invoice_id' => $invoice->id,
                                        'statement' => "Extra delivery fees",
                                        'cost_sdg' => $this->delivery_extra,
                                        'cost_usd' => $this->delivery_extra / $invoice->exchange_rate,
                                    ]
                                );

                                $invoice->load('additionalCosts');
                                $invoice->refresh();
                            }

                            if (($total_paid_sdg + $invoice->total_paid['sdg']) > $invoice->getItemsCost('SDG')) {
                                $t = ($total_paid_sdg + $invoice->total_paid['sdg']);
                                $inv_total = $invoice->getItemsCost('SDG');
                                throw new \Exception("Payments amount does not match remaining invoice amount, $t, $inv_total");
                            }

                            if (($total_paid_sdg + $invoice->total_paid['sdg']) == ($invoice->getItemsCost('SDG'))) {
                                $invoice->update(
                                    [
                                        'locked_at' => now(),
                                        'invoice_status_id' => sales_invoice_status_payment_completed()
                                    ]);
                            }

                            foreach ($invoice->items as $item) {
                                $product = Product::with('availableStocks')->find($item->product_id);

                                if ($item->stocks == null) // if not decremented
                                {
                                    $stocks_ids = $product->takeStock($item['warehouse_id'], $item['qty']);

                                    $item->update(
                                        [
                                            'stocks' => $stocks_ids,
                                        ]
                                    );
                                }

                            }

                            $general_op = make_general_voucher_op();

                            $rv = ReceiptVoucher::create(
                                [
                                    'op_id' => $general_op->id,
                                    'invoice_id' => $invoice->id,
                                    'no' => generate_receipt_voucher(),
                                    'date' => now(),
                                    'user_id' => auth()->id(),
                                    'files' => [],
                                ]
                            );
                            $accService = new AccountingService();

                            foreach ($this->payments as $item) {


                                InvoicePayment::create(
                                    [
                                        'method' => $item['payment_method'],
                                        'invoice_id' => $invoice->id,
                                        'currency_id' => $item['currency_id'],
                                        'date' => now(),
                                        'amount' => $item['amount'],
                                        'exchange_rate' => $item['exchange_rate'] ?? ex_rate(),
                                        'bank_transfer_reference_no' => $item['bank_transfer_reference_no'] ?? null,
                                        'cheque_holder_name' => $item['cheque_holder_name'] ?? null,
                                        'cheque_status' => $item['cheque_status'] ?? null,
                                        'user_id' => auth()->id(),
                                        'statement' => $item['statement'],
                                    ]
                                );

                                $op = null;

                                switch ($item['payment_method']) {
                                    case "cash" :
                                        $op = make_voucher_op('cash-receipt-voucher');
                                        break;

                                    case "mbok" or "fawry" or "bank-transfer" :
                                        $op = make_voucher_op('bank-transfer-receipt-voucher');
                                        break;

                                    case "cheque" :
                                        $op = make_voucher_op('cheque-receipt-voucher');
                                        break;
                                    default:
                                        {
                                            $name = $item['payment_method'];
                                            throw new \Exception("Unable to generate voucher op for method $name ");
                                        }
                                }

                                if ($this->order_id) {

                                    $invoice->update(['driver_id' => $this->driver_id]);

                                    $invoice->refresh();

                                    $order = Order::find($this->order_id);

                                    $order->update(
                                        [
                                            'status' => $invoice->locked_at == null ? "pending" : "delivered",
                                            'paid_date' => now(),
                                            'paid_amount' => $invoice->total_paid['sdg'],
                                            'delivery' => $this->delivery,
                                            'delivery_extra' => $this->delivery_extra
                                        ]);
                                }

                                $accService
                                    ->setUp(
                                        $op->id,
                                        now(),
                                        $item['currency_id'],
                                        generate_double_entry_transaction_id(),
                                        $item['amount'],
                                        $item['exchange_rate'],
                                        $item['statement'],
                                        $item['statement'],
                                        $invoice->id,
                                    )->make($invoice->client->acc4->code, $this->account_id)
                                    ->finish();
                            }

                            if(($this->delivery ?? 0) > 0) {
                                $accService
                                    ->setUp(
                                        $op->id,
                                        now(),
                                        1,
                                        generate_double_entry_transaction_id(),
                                        $this->delivery,
                                        $invoice->exchange_rate,
                                        "Delivery fees",
                                        "Delivery fees",
                                        $invoice->id,
                                    )->make(122400004, $this->order->driver->acc4->code)
                                    ->finish();
                            }



                            if(($this->delivery_extra ?? 0) > 0) {
                                $accService
                                    ->setUp(
                                        $op->id,
                                        now(),
                                        1,
                                        generate_double_entry_transaction_id(),
                                        $this->delivery_extra,
                                        $invoice->exchange_rate,
                                        "Extra delivery fees",
                                        "Extra delivery fees",
                                        $invoice->id,
                                    )->make(122400005, $this->order->driver->acc4->code)
                                    ->finish();
                            }

                            DB::commit();

                            Notification::make()
                                ->title(__('fields.record_added_alert'))
                                ->warning()
                                ->send();

                            $this->redirect(ReceiptVoucherResource::getUrl());

                        } catch (\Exception $exception) {
                            DB::rollBack();
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
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

        public function validatePaymentItems(array $paymentItems): array
        {

            $result = [
                'errors' => [],
            ];

            if (count($paymentItems) == 0)
                $result['errors'][] = __('fields.validation.payments.required');

            foreach ($paymentItems as $item) {
                switch ($item['payment_method']) {
                    case "cash":
                        {
                            if ($item['amount'] == null) {
                                $result['errors'][] = __('fields.validation.amount_money.required');
                            }
                            if ($item['currency_id'] == null) {
                                $result['errors'][] = __('fields.validation.currency.required');
                            }

                            if ($item['statement'] == null) {
                                $result['errors'][] = __('fields.validation.statement.required');
                            }

                            break;
                        }

                    case "cheque":
                        {

                            if ($item['cheque_holder_name'] == null) {
                                $result['errors'][] = __('fields.validation.cheque_holder_name.required');
                            }

                            if ($item['cheque_status'] == null) {
                                $result['errors'][] = __('fields.validation.cheque_status.required');
                            }

                            if ($item['bank_transfer_reference_no'] == null) {
                                $result['errors'][] = __('fields.validation.ref_no.required');
                            }

                            if ($item['currency_id'] == null) {
                                $result['errors'][] = __('fields.validation.currency.required');
                            }

                            if ($item['amount'] == null) {
                                $result['errors'][] = __('fields.validation.amount_money.required');
                            }

                            if ($item['statement'] == null) {
                                $result['errors'][] = __('fields.validation.statement.required');
                            }

                            break;
                        }
                    case "mbok" or "fawry" or "bank-transfer":
                        {

                            if ($item['bank_transfer_reference_no'] == null) {
                                $result['errors'][] = __('fields.validation.ref_no.required');
                            }

                            if ($item['currency_id'] == null) {
                                $result['errors'][] = __('fields.validation.currency.required');
                            }

                            if ($item['amount'] == null) {
                                $result['errors'][] = __('fields.validation.amount_money.required');
                            }

                            if ($item['statement'] == null) {
                                $result['errors'][] = __('fields.validation.statement.required');
                            }

                            break;
                        }
                    default:
                        $result['errors'][] = __('fields.validation.payment_method.required');

                }
            }

            return $result;
        }
    }

<?php

    namespace App\Filament\Tenant\Resources\JournalEntryResource\Pages;

    use Alkoumi\LaravelArabicTafqeet\Tafqeet;
    use App\Filament\Tenant\Resources\JournalEntryResource;
    use App\Models\Acc4;
    use App\Models\Currency;
    use App\Models\CashDet;
    use App\Models\Op;
    use Filament\Actions\Concerns\InteractsWithActions;
    use Filament\Facades\Filament;
    use Filament\Forms\Components\Card;
    use Filament\Forms\Components\ColorPicker;
    use Filament\Forms\Components\DatePicker;
    use Filament\Forms\Components\Grid;
    use Filament\Forms\Components\Group;
    use Filament\Forms\Components\Placeholder;
    use Filament\Forms\Components\Repeater;
    use Filament\Forms\Components\Select;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Contracts\HasForms;
    use Filament\Notifications\Notification;

    use Filament\Resources\Pages\Page;

    class CreateCustomJournalEntry extends Page implements HasForms
    {
        use InteractsWithActions;

        protected static string $resource = JournalEntryResource::class;

        protected static string $view = 'filament.resources.journal-entry-resource.pages.create-custom-journal-entry';
        protected static ?string $title = 'إضافة قيد';

        public $op_no, $date, $entries, $total_amount_in_sdg, $total_amount_out_sdg,
            $total_amount_in_usd, $total_amount_out_usd, $status, $balanced;


        public function mount(): void
        {
            static::authorizeResourceAccess();

            $this->form->fill([
                'op_no' => generate_op(),
                'date' => now(),
                'total_amount_in_sdg' => 0,
                'total_amount_out_sdg' => 0,
                'total_amount_in_usd' => 0,
                'total_amount_out_usd' => 0,
                'status' => 'القيد غير متزن',
                'balanced' => false,
                'entries' => [
                    [
                    ],
                ]
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
                                TextInput::make('op_no')
                                    ->afterStateHydrated(fn(\Closure $set) => $set('op_no', generate_op()))
                                    ->label('رقم القيد')
                                    ->disabled()
                                    ->required(),

                                DatePicker::make('date')
                                    ->label('التاريخ')
                                    ->withoutSeconds()
                                    ->minDate(now()->subDays(30))
                                    ->maxDate(now())
                                    ->default(now())
                                    ->displayFormat('d/m/Y')
                                    ->required(),
                            ])->columns(2),


                        $layout::make()
                            ->schema([
                                Repeater::make('entries')
                                    ->label('المعاملات')
                                    ->schema([
                                        Select::make('credit_account')
                                            ->reactive()
                                            ->label(__('fields.the_credited_account'))
                                            ->searchable()
                                            ->options(Acc4::get()->pluck('name', 'code'))
                                            ->required(),
                                        Select::make('debit_account')
                                            ->reactive()
                                            ->label(__('fields.the_debited_account'))
                                            ->searchable()
                                            ->options(Acc4::get()->pluck('name', 'code'))
                                            ->required(),
                                        Select::make('currency')
                                            ->reactive()
                                            ->label(__('fields.currency'))
                                            ->options(Currency::pluck('name', 'id'))
                                            ->required(),


                                        TextInput::make('amount')
                                            ->label(__('fields.amount_money'))
                                            ->default(0)
                                            ->numeric()
                                            ->minValue(1)
                                            ->reactive()
                                            ->afterStateUpdated(function (\Closure $get, \Closure $set, $state) {
                                                $this->calculateAmount();
                                            })
                                            ->required(),

                                        TextInput::make('statement')
                                            ->columnSpan(2)
                                            ->label(__('fields.statement'))
                                            ->required(),

                                    ])
                                    ->defaultItems(1)
                                    ->createItemButtonLabel(__('fields.add_transaction'))
                                    ->grid(1)
                                    ->columns(6)
                            ]),

                    ])->columnSpan([
                        'sm' => 3,
                    ]),
            ];
        }

        protected function getFormActions(): array
        {
            return [
                Action::make('create')
                    ->label('إضافة')
                    ->action(function () {

                        $this->validate([
                            'op_no' => ['required'],
                            'date' => ['required'],
                            'entries' => ['required', 'array', 'min:1'],

                        ], ['entries.required' => __('fields.please_add_transaction')], [$this->op_no, $this->date, $this->entries]);


                        $entries_pass = true;

                        foreach ($this->entries as $entry) {

                            if ($entry['credit_account'] == null)
                                $entries_pass = false;

                            if ($entry['debit_account'] == null)
                                $entries_pass = false;

                            if ($entry['credit_account'] != null and $entry['debit_account'] and $entry['credit_account'] == $entry['debit_account'])
                            {
                                $entries_pass = false;
                                Filament::notify('warning', 'credit account cannot be the same as debit account');
                                return;
                            }

                            if ($entry['currency'] == null)
                                $entries_pass = false;

                            if ($entry['amount'] == null or $entry['amount'] == 0)
                            {
                                $entries_pass = false;
                            }

                            if ($entry['amount'] < 0)
                            {
                                Filament::notify('danger', __('fields.amount_money_invalid'));
                                $entries_pass = false;
                            }

                            if ($entry['statement'] == null)
                                $entries_pass = false;
                        }

                        if (!$entries_pass) {
                            Filament::notify('warning', __('fields.all_transaction_fields_required'));
                            return;
                        }

//                        $isBalanced = ($this->total_amount_in_sdg > 0 and $this->total_amount_out_sdg > 0 and $this->total_amount_in_sdg == $this->total_amount_out_sdg);
//
//
//                        if (!$isBalanced) {
//                            Filament::notify('danger', "القيد غير متزن");
//                            return;
//                        }

                        try {
                            \DB::beginTransaction();

                            $op = Op::create(
                                [
                                    'type' => "general-voucher", //قيد عام
                                    'user_id' => auth()->id(),
                                    'no' => $this->op_no,
                                    'payment_voucher_no' => null,
                                    'date' => $this->date,
                                    'locked_at' => null,
                                    'submitted_at' => null,
                                    'files' => null,
                                ]
                            );

                            $ex_rate = setting('finance.sdg.usd.exchange_rate', 0);
                            foreach ($this->entries as $entry) {

                                $transaction_id = generate_double_entry_transaction_id();

                                $credit = CashDet::makeTransaction(
                                    $op->id,
                                    $entry['currency'],
                                    $transaction_id,
                                    Acc4::find($entry['credit_account'])->code,
                                    0,
                                    $entry['amount'],
                                    $this->date,
                                    $entry['statement'],
                                    $ex_rate,
                                    null
                                );
                                $debit = CashDet::makeTransaction(
                                    $op->id,
                                    $entry['currency'],
                                    $transaction_id,
                                    Acc4::find($entry['debit_account'])->code,
                                    $entry['amount'],
                                    0,
                                    $this->date,
                                    $entry['statement'],
                                    $ex_rate,
                                    null
                                );
                            }

                            \DB::commit();

                            Filament::notify('success', __('fields.journal_entry_saved'));

                            $this->redirect(admin_panel_url().'/admin/finance/journal-entries');

                        } catch (\Exception $exception) {
                            \DB::rollBack();
                            Filament::notify('danger', $exception->getMessage());

                        }

                    }),

//                Action::make('status')
//                    ->label('معلومات')
//                    ->visible(function () {
//                        return !$this->balanced and ($this->total_amount_out_sdg > 0 and $this->total_amount_in_sdg > 0);
//                    })
//                    ->action(function () {
//                        if ($this->total_amount_in_sdg < $this->total_amount_out_sdg) {
//                            Filament::notify('warning', 'الدائن (جنية سوداني) اقل من المدين');
//                        }
//
//                        if ($this->total_amount_out_sdg < $this->total_amount_in_sdg) {
//                            Filament::notify('warning', 'المدين (جنية سوداني) اقل من الدائن');
//                        }
//
//                        if ($this->total_amount_in_usd < $this->total_amount_out_usd) {
//                            Filament::notify('warning', 'الدائن (دولار أمريكي) اقل من المدين');
//                        }
//
//                        if ($this->total_amount_out_usd < $this->total_amount_in_usd) {
//                            Filament::notify('warning', 'المدين (دولار أمريكي) اقل من الدائن');
//                        }
//
//                        if ($this->entries == null or count($this->entries) == 0) {
//                            Filament::notify('warning', 'الرجاء إدخال معاملة واحدة علي الاقل');
//                        }
//
//                    }),
            ];

        }

        public function sumAmountOut($currency_id)
        {
            $amount = 0;

            foreach ($this->entries as $entry) {
                if ($entry['currency'] == $currency_id)
                    $amount += is_numeric($entry['amount_out']) ? $entry['amount_out'] : 0;
            }

            return $amount;
        }

        public function currencyUsed($currencyId): bool
        {
            return collect($this->entries)->firstWhere('currency', $currencyId) == null ? false : true;
        }

        public function calculateAmount($increased = true)
        {
        }
    }

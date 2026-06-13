<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\Acc4;
use App\Models\AdditionalCostType;
use App\Models\Invoice;
use App\Models\ServiceType;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Js;
use App\Services\InvoicePaymentTermsService;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

trait InvoiceDocumentFormLayout
{
    protected static function invoiceExtrasTabs(
        Form $form,
        string $linesKey,
        ?callable $recalculate = null,
        bool $includeServices = true,
    ): Forms\Components\Tabs {
        $recalculate ??= fn ($livewire) => static::updateInvoicePropertiesFromLivewire($livewire);

        $disabled = $form->getRecord()?->locked_at !== null;

        $tabs = [
            Forms\Components\Tabs\Tab::make('discounts')
                ->label(__('fields.discounts'))
                ->disabled($disabled)
                ->schema(static::invoiceDiscountsTabSchema($linesKey, $recalculate))
                ->columns(5),

            Forms\Components\Tabs\Tab::make('payments')
                ->label(__('fields.payments'))
                ->visible(fn (Get $get) => filled($get('payment_terms')) && ($get('payment_terms') ?? 'credit') === 'credit')
                ->schema(static::invoicePaymentsTabSchema($form))
                ->columns(3),
        ];

        if ($includeServices) {
            $tabs[] = Forms\Components\Tabs\Tab::make('services')
                ->label(__('fields.services'))
                ->disabled($disabled)
                ->schema(static::invoiceServicesTabSchema($recalculate))
                ->columns(7);
        }

        $tabs[] = Forms\Components\Tabs\Tab::make('additional_costs')
            ->label(__('fields.additional_costs'))
            ->disabled($disabled)
            ->schema(static::invoiceAdditionalCostsTabSchema($recalculate))
            ->columns(7);

        return Forms\Components\Tabs::make('invoice_extras_tabs')
            ->activeTab(1)
            ->extraAttributes(['class' => 'invoice-extras-tabs'])
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * @param  array<int|string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    protected static function invoiceLinesFromState(array $state): array
    {
        if (method_exists(static::class, 'inlineProductLinesFromState')) {
            return static::inlineProductLinesFromState($state);
        }

        return collect($state)
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function invoiceDiscountsTabSchema(string $linesKey, callable $recalculate): array
    {
        return [
            Forms\Components\Hidden::make('discount_option')->default('per-item'),

            Forms\Components\Toggle::make('discount_option_overall')
                ->dehydrated(false)
                ->label(__('fields.discount_per_invoice'))
                ->live()
                ->default(false)
                ->afterStateUpdated(function ($state, Set $set, $livewire) use ($linesKey, $recalculate) {
                    $set('total_purchases_post_discount', null);
                    $set('total_invoice_post_discount', null);

                    if ($state) {
                        $set('discount_option', 'overall');
                    } else {
                        $set('discount_option', 'per-item');
                        $set('discount_method', null);
                        $set('discount_amount', null);
                        $set('discount_percent', null);
                        $newItems = [];

                        $lines = static::invoiceLinesFromState($livewire->data[$linesKey] ?? []);

                        foreach ($lines as $item) {
                            $item['discount'] = 0;
                            $newItems[] = $item;
                        }

                        $livewire->data[$linesKey] = $newItems;
                    }

                    $recalculate($livewire);
                }),

            Forms\Components\Radio::make('discount_method')
                ->visible(fn (Get $get) => $get('discount_option_overall') === true)
                ->required()
                ->label(__('fields.discount_method'))
                ->live()
                ->afterStateUpdated(function ($state, Set $set, $livewire) use ($recalculate) {
                    if ($state == 'amount') {
                        $set('discount_percent', null);
                    }

                    if ($state == 'percent') {
                        $set('discount_amount', null);
                    }

                    $recalculate($livewire);
                })
                ->options([
                    'amount' => __('fields.discount_by_amount'),
                    'percent' => __('fields.discount_by_percent'),
                ]),

            Forms\Components\TextInput::make('discount_amount')
                ->visible(fn (Get $get) => $get('discount_option_overall') == true and $get('discount_method') == 'amount')
                ->label(__('fields.discount_amount'))
                ->numeric()
                ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                ->required()
                ->live(true)
                ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                ->currency(),

            Forms\Components\TextInput::make('discount_percent')
                ->visible(fn (Get $get) => $get('discount_option_overall') == true and $get('discount_method') == 'percent')
                ->label(__('fields.discount_percent'))
                ->numeric()
                ->extraInputAttributes(['min' => 1, 'max' => 100])
                ->suffix('%')
                ->live(true)
                ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                ->required(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function invoiceServicesTabSchema(callable $recalculate): array
    {
        return [
            Forms\Components\Repeater::make('services')
                ->label('')
                ->relationship('services')
                ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                ->afterStateHydrated(fn ($livewire) => $recalculate($livewire))
                ->schema([
                    hidden_tenant_id_field(),
                    Forms\Components\Hidden::make('tax_profile_data'),
                    Forms\Components\Select::make('service_type_id')
                        ->label(__('fields.service_type'))
                        ->required()
                        ->options(ServiceType::pluck('name', 'id'))
                        ->createOptionForm([
                            Forms\Components\Section::make(__('fields.service_types'))
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('fields.name'))
                                        ->required()
                                        ->autofocus()
                                        ->rules([new UniqueTenantItemRule(ServiceType::class, 'name')]),
                                ]),
                        ])
                        ->createOptionUsing(function ($data) {
                            $model = new ServiceType();
                            $model->tenant_id = filament()->getTenant()->id;
                            $model->name = $data['name'];
                            $model->save();

                            return $model->id;
                        })
                        ->createOptionAction(
                            fn (Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                        )
                        ->searchable(),
                    Forms\Components\Select::make('tax_profile_id')
                        ->live()
                        ->label(__('fields.tax'))
                        ->placeholder(__('fields.not_subject_to_tax'))
                        ->options(\App\Models\TaxProfile::asOptions())
                        ->createOptionForm(\App\Filament\Tenant\Resources\TaxProfileResource::getSchemaForCreateOption())
                        ->createOptionUsing(function ($data) {
                            $data['tenant_id'] = filament()->getTenant()->id;
                            $model = \App\Models\TaxProfile::create(\Illuminate\Support\Arr::except($data, ['taxes']));
                            foreach ($data['taxes'] as $tax) {
                                $model->taxes()->create([
                                    'tenant_id' => $data['tenant_id'],
                                    'tax_profile_id' => $model->id,
                                    'description' => $tax['description'],
                                    'percent' => $tax['percent'],
                                ]);
                            }

                            return $model->id;
                        })
                        ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                        ->createOptionAction(
                            fn (Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                        ),
                    Forms\Components\TextInput::make('price')
                        ->live(true)
                        ->label(__('fields.price'))
                        ->numeric()
                        ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                        ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                        ->currency()
                        ->required(),
                    Forms\Components\TextInput::make('tax')
                        ->label(__('fields.tax'))
                        ->dehydrated(false)
                        ->readOnly(),
                    Forms\Components\TextInput::make('total')
                        ->label(__('fields.total'))
                        ->dehydrated(false)
                        ->readOnly(),
                    Forms\Components\TextInput::make('description')
                        ->label(__('fields.description'))
                        ->required()
                        ->columnSpan(2),
                ])
                ->addActionLabel(__('fields.add'))
                ->grid(1)
                ->defaultItems(0)
                ->columns(7)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function invoiceAdditionalCostsTabSchema(callable $recalculate): array
    {
        return [
            Forms\Components\Repeater::make('additional_costs')
                ->label('')
                ->relationship('additionalCosts')
                ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                ->afterStateHydrated(fn ($livewire) => $recalculate($livewire))
                ->schema([
                    hidden_tenant_id_field(),
                    Forms\Components\Hidden::make('meta'),
                    Forms\Components\Hidden::make('tax_profile_data'),
                    Forms\Components\Select::make('additional_cost_type_id')
                        ->label(__('fields.invoice_additional_cost_type'))
                        ->required()
                        ->options(AdditionalCostType::pluck('name', 'id'))
                        ->createOptionForm([
                            Forms\Components\Section::make(__('fields.invoice_additional_cost_type'))
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('fields.name'))
                                        ->required()
                                        ->autofocus()
                                        ->rules([new UniqueTenantItemRule(AdditionalCostType::class, 'name')]),
                                ]),
                        ])
                        ->createOptionUsing(function ($data) {
                            $model = new AdditionalCostType();
                            $model->tenant_id = filament()->getTenant()->id;
                            $model->name = $data['name'];
                            $model->save();

                            return $model->id;
                        })
                        ->createOptionAction(
                            fn (Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                        )
                        ->searchable(),
                    Forms\Components\Select::make('tax_profile_id')
                        ->live()
                        ->label(__('fields.tax'))
                        ->placeholder(__('fields.not_subject_to_tax'))
                        ->options(\App\Models\TaxProfile::asOptions())
                        ->createOptionForm(\App\Filament\Tenant\Resources\TaxProfileResource::getSchemaForCreateOption())
                        ->createOptionUsing(function ($data) {
                            $data['tenant_id'] = filament()->getTenant()->id;
                            $model = \App\Models\TaxProfile::create(\Illuminate\Support\Arr::except($data, ['taxes']));
                            foreach ($data['taxes'] as $tax) {
                                $model->taxes()->create([
                                    'tenant_id' => $data['tenant_id'],
                                    'tax_profile_id' => $model->id,
                                    'description' => $tax['description'],
                                    'percent' => $tax['percent'],
                                ]);
                            }

                            return $model->id;
                        })
                        ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                        ->createOptionAction(
                            fn (Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                        ),
                    Forms\Components\TextInput::make('cost')
                        ->live(true)
                        ->label(__('fields.cost'))
                        ->numeric()
                        ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX])
                        ->afterStateUpdated(fn ($livewire) => $recalculate($livewire))
                        ->currency()
                        ->required(),
                    Forms\Components\TextInput::make('tax')
                        ->label(__('fields.tax'))
                        ->dehydrated(false)
                        ->readOnly(),
                    Forms\Components\TextInput::make('total')
                        ->label(__('fields.total'))
                        ->dehydrated(false)
                        ->readOnly(),
                    Forms\Components\TextInput::make('statement')
                        ->label(__('fields.statement'))
                        ->required()
                        ->columnSpan(2),
                ])
                ->addActionLabel(__('fields.add'))
                ->grid(1)
                ->defaultItems(0)
                ->columns(7)
                ->columnSpanFull(),
        ];
    }

    protected static function invoiceTotalsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make()
            ->extraAttributes(['class' => 'invoice-totals-panel'])
            ->schema([
                Forms\Components\Placeholder::make('invoice_totals_summary')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->content(fn ($livewire) => static::renderInvoiceTotalsCards($livewire)),
            ]);
    }

    protected static function renderInvoiceTotalsCards($livewire): HtmlString
    {
        $currency = app()->getLocale() === 'ar'
            ? 'ريال'
            : (main_currency_native_symbol() ?: main_currency_iso_code());

        $cards = [
            [
                'value' => $livewire->data['total_invoice_pre_discount_pre_tax'] ?? '0',
                'label' => __('fields.total_before_vat'),
                'modifier' => '',
            ],
            [
                'value' => $livewire->data['total_discount'] ?? '0',
                'label' => __('fields.discount'),
                'modifier' => 'invoice-totals-panel__card--discount',
            ],
            [
                'value' => $livewire->data['total_invoice_post_discount'] ?? '0',
                'label' => __('fields.total_invoice_net_post_discount'),
                'modifier' => '',
            ],
            [
                'value' => $livewire->data['total_taxes'] ?? '0',
                'label' => __('fields.vat'),
                'modifier' => '',
            ],
            [
                'value' => $livewire->data['total_invoice_with_taxes'] ?? '0',
                'label' => __('fields.line_amount'),
                'modifier' => 'invoice-totals-panel__card--amount',
            ],
        ];

        $html = '<div class="invoice-totals-panel__wrap"><div class="invoice-totals-panel__grid">';

        foreach ($cards as $card) {
            $html .= sprintf(
                '<div class="invoice-totals-panel__card %s">
                    <div class="invoice-totals-panel__value">%s <span class="invoice-totals-panel__currency">%s</span></div>
                    <div class="invoice-totals-panel__label">%s</div>
                </div>',
                e($card['modifier']),
                e($card['value']),
                e($currency),
                e($card['label']),
            );
        }

        $html .= '</div>';

        $words = numbers_to_words($livewire->data['total_invoice_with_taxes'] ?? 0);

        if (filled($words)) {
            $html .= '<div class="invoice-totals-panel__words">' . e($words) . '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function invoiceLinesToolbar(bool $showSearchHint = true, bool $showPricesToggle = true): Forms\Components\Grid
    {
        $schema = [];

        if ($showSearchHint) {
            $schema[] = Forms\Components\Placeholder::make('lines_search_hint')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<span class="invoice-lines-toolbar__hint">' . e(__('fields.invoice_lines_search_hint')) . '</span>'
                ));
        }

        if ($showPricesToggle) {
            $schema[] = Forms\Components\Toggle::make('prices_includes_taxes')
                ->default(true)
                ->label(__('fields.prices_includes_taxes'))
                ->inline(true)
                ->extraFieldWrapperAttributes(['class' => 'invoice-lines-toolbar__toggle'])
                ->live()
                ->afterStateUpdated(fn ($livewire) => static::updateInvoicePropertiesFromLivewire($livewire));
        }

        $columns = ($showSearchHint && $showPricesToggle) ? 2 : 1;

        return Forms\Components\Grid::make($columns)
            ->extraAttributes(['class' => 'invoice-lines-toolbar fi-fo-grid'])
            ->schema($schema);
    }

    protected static function invoiceLinesAddAction(Forms\Components\Actions\Action $action): Forms\Components\Actions\Action
    {
        return $action
            ->link()
            ->color('primary')
            ->icon('heroicon-m-plus')
            ->extraAttributes(['class' => 'invoice-lines-table__add-link']);
    }

    protected static function invoicePaymentTermsSelect(Form $form): Forms\Components\Select
    {
        return Forms\Components\Select::make('payment_terms')
            ->label(__('fields.payment_terms'))
            ->options([
                'cash' => __('fields.payment_terms_cash'),
                'credit' => __('fields.payment_terms_credit'),
            ])
            ->default('credit')
            ->native(false)
            ->live()
            ->disabled($form->getRecord()?->locked_at !== null);
    }

    protected static function invoicePaymentsTabSchema(Form $form): array
    {
        return [
            Forms\Components\Group::make()
                ->statePath('credit_payment_ui')
                ->dehydrated(false)
                ->schema(static::invoicePaymentsFieldsSchema())
                ->columns(3)
                ->columnSpanFull(),
        ];
    }

    protected static function invoicePaymentsFieldsSchema(): array
    {
        return [
            Forms\Components\Select::make('credit_payment_account')
                ->label(__('fields.account'))
                ->dehydrated(false)
                ->disabled(false)
                ->default('120100001')
                ->helperText(__('fields.payment_collection_account_hint'))
                ->options(fn () => Acc4::query()
                    ->whereIn('code', [120100001])
                    ->orWhereIn('acc3_code', [1227])
                    ->pluck('name', 'code'))
                ->searchable(),

            Forms\Components\TextInput::make('credit_payment_amount')
                ->label(__('fields.paid_amount'))
                ->dehydrated(false)
                ->disabled(false)
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->live()
                ->maxValue(fn ($livewire) => static::invoiceCreditPaymentMaxAmount($livewire))
                ->currency(),

            Forms\Components\DatePicker::make('credit_payment_date')
                ->label(__('fields.payment_date'))
                ->dehydrated(false)
                ->disabled(false)
                ->default(now())
                ->maxDate(now())
                ->minDate(now()->subDays(90))
                ->displayFormat('d/m/Y'),

            Forms\Components\Textarea::make('credit_payment_statement')
                ->label(__('fields.additional_notes'))
                ->dehydrated(false)
                ->disabled(false)
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('credit_payment_remaining')
                ->label(__('fields.unpaid_amount'))
                ->content(fn (Get $get, $livewire) => new HtmlString(
                    static::renderInvoiceCreditRemaining(
                        $livewire,
                        (float) (static::creditPaymentUiValue($livewire, $get, 'credit_payment_amount') ?? 0),
                    )
                ))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('credit_payment_status')
                ->label(__('fields.settlement_status'))
                ->content(fn (Get $get, $livewire) => new HtmlString(
                    '<span class="text-sm font-medium">' . e(static::invoiceCreditSettlementLabel(
                        $livewire,
                        (float) (static::creditPaymentUiValue($livewire, $get, 'credit_payment_amount') ?? 0),
                    )) . '</span>'
                ))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('credit_payment_history')
                ->label(__('fields.payments'))
                ->visible(fn ($livewire) => $livewire->record?->exists
                    && static::invoiceHasRecordedPayments($livewire->record))
                ->content(fn ($livewire) => new HtmlString(
                    static::renderInvoiceCreditPaymentHistory($livewire->record)
                ))
                ->columnSpanFull(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('register_credit_payment')
                    ->label(__('fields.register_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->action(fn ($livewire) => $livewire->registerCreditPayment())
                    ->visible(fn (Get $get, $livewire) => $livewire->record?->locked_at !== null
                        && (float) (static::creditPaymentUiValue($livewire, $get, 'credit_payment_amount')) > 0
                        && (float) $livewire->record->total_unpaid > 0),
            ])->columnSpanFull(),
        ];
    }

    protected static function creditPaymentUiValue($livewire, ?Get $get, string $key): mixed
    {
        if ($get) {
            $value = $get($key);

            if ($value !== null) {
                return $value;
            }
        }

        return $livewire->data['credit_payment_ui'][$key]
            ?? $livewire->data[$key]
            ?? null;
    }

    protected static function invoiceCreditPaymentMaxAmount($livewire): float
    {
        if ($livewire->record?->exists) {
            return max(0, (float) $livewire->record->total_unpaid);
        }

        return max(0, static::invoiceDraftTotalFromLivewire($livewire));
    }

    protected static function invoiceDraftTotalFromLivewire($livewire): float
    {
        $raw = (string) ($livewire->data['total_invoice_with_taxes'] ?? '0');

        return (float) preg_replace('/[^\d.]/', '', $raw);
    }

    protected static function invoiceCreditRemainingAmount($livewire, float $enteredAmount): float
    {
        if ($livewire->record?->exists) {
            return max(0, (float) $livewire->record->total_unpaid - $enteredAmount);
        }

        return max(0, static::invoiceDraftTotalFromLivewire($livewire) - $enteredAmount);
    }

    protected static function renderInvoiceCreditRemaining($livewire, float $enteredAmount): string
    {
        $remaining = static::invoiceCreditRemainingAmount($livewire, $enteredAmount);
        $currency = main_currency_iso_code();

        return sprintf(
            '<span class="text-lg font-semibold text-danger-600">%s %s</span>',
            e(format_amount($remaining)),
            e($currency),
        );
    }

    protected static function invoiceCreditSettlementLabel($livewire, float $entered = 0): string
    {

        if ($livewire->record?->exists) {
            $invoice = $livewire->record;
            $invoice->loadMissing(['salesPayments', 'purchasePayments']);
            $total = $invoice->getItemsCost(true, true, true);
            $paid = $invoice->total_paid + $entered;

            if ($paid <= 0) {
                return __('fields.settlement_status_due');
            }

            if ($paid >= $total) {
                return __('fields.settlement_status_paid');
            }

            return __('fields.settlement_status_partial');
        }

        $total = static::invoiceDraftTotalFromLivewire($livewire);

        if ($entered <= 0) {
            return __('fields.settlement_status_due');
        }

        if ($entered >= $total && $total > 0) {
            return __('fields.settlement_status_paid');
        }

        return __('fields.settlement_status_partial');
    }

    protected static function invoiceHasRecordedPayments($invoice): bool
    {
        $invoice->loadMissing(['salesPayments', 'purchasePayments']);

        return $invoice->type === 'purchases'
            ? $invoice->purchasePayments->isNotEmpty()
            : $invoice->salesPayments->isNotEmpty();
    }

    protected static function renderInvoiceCreditPaymentHistory($invoice): string
    {
        $invoice->loadMissing(['salesPayments', 'purchasePayments']);
        $payments = $invoice->type === 'purchases' ? $invoice->purchasePayments : $invoice->salesPayments;
        $currency = main_currency_iso_code();

        if ($payments->isEmpty()) {
            return '';
        }

        $rows = '';

        foreach ($payments as $payment) {
            $rows .= sprintf(
                '<tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2">%s</td>
                    <td class="px-3 py-2">%s %s</td>
                    <td class="px-3 py-2">%s</td>
                </tr>',
                e($payment->date?->format('d/m/Y') ?? '—'),
                e($currency),
                e(format_amount($payment->amount)),
                e($payment->statement),
            );
        }

        return sprintf(
            '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-start">%s</th>
                            <th class="px-3 py-2 text-start">%s</th>
                            <th class="px-3 py-2 text-start">%s</th>
                        </tr>
                    </thead>
                    <tbody>%s</tbody>
                </table>
            </div>',
            e(__('fields.date')),
            e(__('fields.amount')),
            e(__('fields.statement')),
            $rows,
        );
    }

    public static function invoiceSettlementStatusTableColumn(): TextColumn
    {
        return TextColumn::make('settlement_status')
            ->label(__('fields.settlement_status'))
            ->badge()
            ->getStateUsing(fn (Invoice $record) => $record->payment_status)
            ->color(fn (Invoice $record) => match ($record->settlement_status_key) {
                'due' => 'gray',
                'partial' => 'warning',
                'cash', 'paid' => 'success',
                default => 'gray',
            });
    }

    public static function configureInvoiceTableActionGroup(ActionGroup $group): ActionGroup
    {
        return $group
            ->icon('heroicon-m-ellipsis-vertical')
            ->iconButton()
            ->color('primary')
            ->extraAttributes(['class' => 'document-list-row-actions']);
    }

    public static function shareInvoiceUrlTableAction(): Action
    {
        return Action::make('invoice_url')
            ->label(__('fields.invoice_url'))
            ->icon('heroicon-o-link')
            ->color(Color::Sky)
            ->visible(fn (Invoice $record) => filled($record->uid) && ! $record->temp)
            ->action(function (Invoice $record, $livewire) {
                $url = $record->url;

                $livewire->js('window.navigator.clipboard.writeText(' . Js::from($url) . ')');

                Notification::make()
                    ->title(__('fields.invoice_link_copied'))
                    ->body($url)
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('open')
                            ->label(__('fields.invoice_download_view_file'))
                            ->url($url, shouldOpenInNewTab: true),
                        \Filament\Notifications\Actions\Action::make('pdf')
                            ->label(__('fields.download_invoice'))
                            ->url($record->pdf_url, shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }
}

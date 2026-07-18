<?php

namespace App\Filament\Tenant\Concerns;

use App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages\CreateReceiptVoucher;
use App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages\EditReceiptVoucher;
use App\Models\Acc4;
use App\Models\Customer;
use App\Services\ReceiptVoucherAllocationService;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;

trait InteractsWithReceiptVoucherCustomerForm
{
    public static function usesCustomerAllocationForm(): bool
    {
        return true;
    }

    public static function refreshCustomerInvoiceLines(object $livewire, ?Set $set = null): void
    {
        $acc4Code = $livewire->data['acc4_code'] ?? null;

        if (! $acc4Code) {
            $livewire->data['customer_invoices'] = [];

            return;
        }

        $invoices = ReceiptVoucherAllocationService::instance()
            ->unpaidSalesInvoicesForAcc4Code((int) $acc4Code);

        $mode = $livewire->data['allocation_mode'] ?? 'fifo';
        $paidAmount = static::normalizeReceiptPaidAmount($livewire->data['paid_amount'] ?? 0);
        $selectedIds = collect($livewire->data['customer_invoices'] ?? [])
            ->filter(fn ($line) => ! empty($line['selected']))
            ->pluck('invoice_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($mode === 'selected' && $selectedIds === [] && filled($livewire->data['preselected_invoice_id'] ?? null)) {
            $selectedIds = [(int) $livewire->data['preselected_invoice_id']];
        }

        $lines = ReceiptVoucherAllocationService::instance()->buildInvoiceLineStates(
            $invoices,
            $mode,
            $selectedIds,
            $paidAmount,
        );

        $livewire->data['customer_invoices'] = $lines;

        if ($set && $paidAmount > 0) {
            $set('paid_amount', number_format($paidAmount, currency_decimals(), '.', ''));
        }
    }

    public static function normalizeReceiptPaidAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, currency_decimals());
        }

        if (! is_string($value)) {
            return 0;
        }

        $normalized = preg_replace('/[^\d.]/', '', $value);

        return round((float) $normalized, currency_decimals());
    }

    protected static function customerReceiptCreateSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make()
            ->visible(fn ($livewire, Get $get) => $livewire instanceof CreateReceiptVoucher && $get('for') === 'customer')
            ->schema([
                hidden_user_id_field(),

                Forms\Components\Hidden::make('for')->default('customer'),

                TextInput::make('no')
                    ->label(__('fields.voucher_no'))
                    ->readOnly()
                    ->required(),

                Forms\Components\Grid::make(2)->schema([
                    Select::make('debit_acc4_code')
                        ->label(__('fields.account'))
                        ->placeholder(__('fields.receipt_voucher_collection_account_hint'))
                        ->searchable()
                        ->live()
                        ->default(fn () => Acc4::defaultCollectionAccountCode())
                        ->options(fn () => Acc4::collectionAccountOptions())
                        ->required(),

                    Select::make('acc4_code')
                        ->label(__('fields.client'))
                        ->searchable()
                        ->live()
                        ->disabledOn(EditReceiptVoucher::class)
                        ->options(fn () => Acc4::asOptions(only_item_class: [Customer::class]))
                        ->afterStateUpdated(function ($state, $livewire, Set $set) {
                            $set('preselected_invoice_id', null);
                            static::refreshCustomerInvoiceLines($livewire, $set);
                        })
                        ->required(),

                    Forms\Components\DatePicker::make('date')
                        ->label(__('fields.date'))
                        ->required()
                        ->seconds(false)
                        ->minDate(now()->subDays(30))
                        ->maxDate(now())
                        ->default(now())
                        ->displayFormat('d/m/Y'),

                    TextInput::make('paid_amount')
                        ->label(__('fields.paid_amount'))
                        ->numeric()
                        ->minValue(0.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($livewire) => static::refreshCustomerInvoiceLines($livewire))
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label(__('fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

                Forms\Components\Radio::make('allocation_mode')
                    ->label(__('fields.receipt_voucher_allocation_mode'))
                    ->default('fifo')
                    ->live()
                    ->options([
                        'fifo' => __('fields.receipt_voucher_allocation_fifo'),
                        'selected' => __('fields.receipt_voucher_allocation_selected'),
                    ])
                    ->afterStateUpdated(fn ($livewire) => static::refreshCustomerInvoiceLines($livewire)),

                Forms\Components\Placeholder::make('customer_invoices_hint')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<p class="text-sm text-gray-500">' . e(__('fields.receipt_voucher_customer_invoices_hint')) . '</p>'
                    )),

                TableRepeater::make('customer_invoices')
                    ->label(__('fields.select_transactions'))
                    ->dehydrated(false)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->columnSpanFull()
                    ->headers([
                        Header::make('selected')
                            ->width('60px')
                            ->align(Alignment::Center)
                            ->label(''),

                        Header::make('no')
                            ->width('180px')
                            ->align(fn () => app()->getLocale() === 'ar' ? Alignment::Left : Alignment::Right)
                            ->label(__('fields.invoice_no')),

                        Header::make('date')
                            ->width('140px')
                            ->align(fn () => app()->getLocale() === 'ar' ? Alignment::Left : Alignment::Right)
                            ->label(__('fields.transaction_date')),

                        Header::make('invoice_total')
                            ->width('120px')
                            ->align(fn () => app()->getLocale() === 'ar' ? Alignment::Left : Alignment::Right)
                            ->label(__('fields.invoice_amount')),

                        Header::make('remaining')
                            ->width('120px')
                            ->align(fn () => app()->getLocale() === 'ar' ? Alignment::Left : Alignment::Right)
                            ->label(__('fields.remaining_amount')),

                        Header::make('allocated')
                            ->width('120px')
                            ->align(fn () => app()->getLocale() === 'ar' ? Alignment::Left : Alignment::Right)
                            ->label(__('fields.allocated_amount')),
                    ])
                    ->schema([
                        Forms\Components\Hidden::make('invoice_id'),
                        Forms\Components\Hidden::make('remaining_raw'),
                        Forms\Components\Hidden::make('allocated_raw'),

                        Forms\Components\Checkbox::make('selected')
                            ->label('')
                            ->live()
                            ->disabled(fn (Get $get) => ($get('../../allocation_mode') ?? 'fifo') === 'fifo')
                            ->afterStateUpdated(fn ($livewire) => static::refreshCustomerInvoiceLines($livewire)),

                        Forms\Components\TextInput::make('no')
                            ->label(__('fields.invoice_no'))
                            ->disabled(),

                        Forms\Components\TextInput::make('date')
                            ->label(__('fields.transaction_date'))
                            ->disabled(),

                        Forms\Components\TextInput::make('invoice_total')
                            ->label(__('fields.invoice_amount'))
                            ->disabled(),

                        Forms\Components\TextInput::make('remaining')
                            ->label(__('fields.remaining_amount'))
                            ->disabled(),

                        Forms\Components\TextInput::make('allocated')
                            ->label(__('fields.allocated_amount'))
                            ->disabled(),
                    ]),

                Forms\Components\Hidden::make('preselected_invoice_id')->dehydrated(false),
            ]);
    }
}

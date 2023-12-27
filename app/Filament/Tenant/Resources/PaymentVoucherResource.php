<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PaymentVoucherResource\Pages;
use App\Filament\Tenant\Resources\PaymentVoucherResource\RelationManagers;
use App\Models\Acc4;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentVoucherResource extends Resource
{
    protected static ?string $model = PaymentVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = "no";

    protected static ?string $slug = "finance/payment-vouchers";

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.finance');
    }

    public static function getLabel(): ?string
    {
        return __('fields.payment_voucher');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.payment_vouchers');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([

                    hidden_user_id_field(),

                    Forms\Components\Hidden::make('received_invoice_id')->dehydrated(false),

                    Forms\Components\Hidden::make('for')
                        ->default("supplier"),

                    TextInput::make('no')
                        ->label(__('fields.voucher_no'))
                        ->readOnly()
                        ->required(),

                    DatePicker::make('date')
                        ->label(__('fields.date'))
                        ->required()
                        ->seconds(false)
                        ->minDate(now()->subDays(30))
                        ->maxDate(now())
                        ->default(now())
                        ->displayFormat('d/m/Y'),

                    Select::make('supplier_id')
                        ->label(__('fields.supplier'))
                        ->visible(fn($livewire, $record) => self::shouldSupplierFieldBeVisible($livewire, $record))
                        ->disabled(fn($livewire, $record) => self::shouldSupplierFieldBeReadOnly($livewire, $record))
                        ->live()
                        ->searchable()
                        ->options(Supplier::pluck('name', 'id'))
                        ->afterStateUpdated(function ($state, Set $set, $record, $livewire) {

                            $set('invoice_id', null);

                            if ($state) {
                                $invoices = Invoice::where('supplier_id', $state)->count();

                                if ($invoices == 0)
                                    Notification::make()
                                        ->title("لا توجد فواتير لهذا المورد")
                                        ->warning()
                                        ->send();
                            }

                            self::updateInvoiceProperties($record?->invoice, $livewire);

                        })
                        ->required(),

                    Select::make('invoice_id')
                        ->live()
                        ->disabled(fn($livewire, $record) => self::shouldInvoiceFieldBeReadOnly($livewire, $record))
                        ->label(__('fields.invoice_no'))
                        ->options(function (Get $get) {
                            $supplier_id = $get('supplier_id');

                            if ($supplier_id) {
                                return Invoice::dropdownUnpaidForSupplier($supplier_id, true);
                            }
                            return [];
                        })
                        ->afterStateHydrated(function (Get $get, $record, $livewire, Select $component) {
                            $options = [];

                            $invoice = self::getInvoice($livewire, $record);
//                            $invoice = Invoice::find(request('invoice_id'));

                            if ($invoice) {
                                if ($invoice->for === "supplier") {
                                    $options = Invoice::dropdownUnpaidForSupplier($invoice->supplier_id, true);
                                }
                                $component->helperText($invoice->no);

                                self::updateInvoiceProperties($invoice, $livewire);

                            }

                            return $options;
                        })
                        ->afterStateUpdated(function (Set $set, $state, $livewire) {
                            if ($state) {
                                $invoice = Invoice::with('items', 'purchasePayments')->find($state);

                                self::updateInvoiceProperties($invoice, $livewire);
                            }
                        })
                        ->required(),

                    //for example, in case of purchase invoice, supplier code
                    Forms\Components\Hidden::make('debit_acc4_code'),

                    Select::make('credit_acc4_code')
                        ->live()
                        ->label(__('fields.account'))
                        ->disabled(fn($record, $livewire) => self::shouldAccountFieldBeReadOnly($livewire, $record))
                        ->hint(function (Get $get) {
                            $acc_id = $get('credit_acc4_code');

                            if ($acc_id)
                                return Acc4::find($acc_id)->acc4_code;

                            return null;
                        })
                        ->options(Acc4::whereIn('code', [120100001, 120100002])->pluck('name', 'code'))
                        ->required(),
                ])->columns(5),

                Forms\Components\Section::make([

                    TextInput::make('total_invoice')
                        ->label(__('fields.amount_money'))
                        ->readOnly()
                        ->currency()
                        ->dehydrated(false),

                    TextInput::make('total_paid_amount')
                        ->label(__('fields.paid_amount'))
                        ->readOnly()
                        ->currency()
                        ->dehydrated(false),

                    TextInput::make('total_unpaid_amount')
                        ->label(__('fields.unpaid_amount'))
                        ->readOnly()
                        ->currency()
                        ->dehydrated(false),

                ])
                    ->visible(fn($livewire, $record) => self::shouldInvoiceDetailsBeVisible($livewire, $record))
                    ->columns(3),


                Forms\Components\Section::make([

                    TableRepeater::make('payments')
                        ->required()
                        ->minItems(1)
                        ->label(__('fields.payments'))
                        ->relationship('payments')
                        ->addActionLabel(__('fields.add'))
                        ->defaultItems(1)
                        ->withoutHeader()
                        ->columnSpan('full')
                        ->columnWidths([
                            'amount' => '200px',
                            'date' => '200px',
                            'statement' => '400px',
                        ])
                        ->live(true)
                        ->afterStateHydrated(function ($record, $livewire, Set $set) {
                            self::updateInvoiceProperties($record?->invoice, $livewire);
                            self::updatePayments($record?->invoice, $livewire);
                        })
                        ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                            $data['amount'] = number_format($data['amount'] ?? 0, currency_decimals(), '.', '');
                            return $data;
                        })
                        ->afterStateUpdated(fn($record, $livewire, Set $set) => self::updatePayments($record?->invoice, $livewire))
                        ->deletable(function ($record) {
                            return $record == null;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function ($data, $state, $record, $livewire) {
                            $amount = collect($state)->sum('amount');
                            $invoice = self::getInvoice($livewire, null);

                            if ($amount > $invoice?->getItemsCost(true, true, true)) {
                                fns()->sendWarning(__("fields.payments_are_bigger_than_invoice_amount"));
                                throw new Halt();
                            }
                            return $data;
                        })
                        ->itemLabel(fn(array $state): ?string => $state['amount'] ?? null)
                        ->addable(function ($livewire, $record) {
                            $invoice = self::getInvoice($livewire, $record);

                            return $invoice?->total_unpaid > 0;
                        })
                        ->schema([

                            Forms\Components\Hidden::make('model_type')->default(Invoice::class),
                            Forms\Components\Hidden::make('model_id')->formatStateUsing(function (Get $get) {
                                return $get('data.invoice_id', true);
                            }),

                            Forms\Components\Hidden::make('method')->default('cash'),

                            hidden_tenant_id_field(),

                            hidden_user_id_field(),

                            hidden_main_currency_field(),

                            Forms\Components\Hidden::make('id')->default(null),

                            TextInput::make('amount')
                                ->label(__('fields.amount_money'))
                                ->numeric()
                                ->minValue(0.1)
                                ->maxValue(PHP_INT_MAX)
                                ->extraInputAttributes(['min' => 0.1, 'max' => PHP_INT_MAX])
                                ->disabled(fn($record) => $record !== null)
                                ->required(),

                            DatePicker::make('date')
                                ->label(__('fields.date'))
                                ->seconds(false)
                                ->minDate(now()->subDays(90))
                                ->maxDate(now())
                                ->default(now())
                                ->displayFormat('d/m/Y'),

                            TextInput::make('statement')
                                ->required()
                                ->label(__('fields.statement')),

                            SpatieMediaLibraryFileUpload::make('attachments')
                                ->label(__('fields.attachments'))
                                ->image()
                                ->reorderable()
                                ->openable()
                                ->downloadable()
                                ->multiple()
                                ->maxSize(2048)
                                ->disk('cdn')
                                ->previewable(false)
                                ->directory('receipt_voucher_payments'),

                        ]),
                ]),


                Forms\Components\Section::make([

                    TextInput::make('total_payments')
                        ->label(__('fields.total_payments'))
                        ->readOnly()
                        ->currency()
                        ->dehydrated(false),
                ])
                    ->columns(3),


            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice_no')),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice.no')
                    ->label(__('fields.invoice_no')),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('fields.supplier'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('fields.paid_amount'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->invoice->total_paid);
                    }),

                Tables\Columns\TextColumn::make('paid_amount_percent')
                    ->extraAttributes(function ($record) {
                        if (percent($record->invoice->total_paid, $record->invoice->getItemsCost(true, true, true)) > 0) {
                            return ['class' => 'text-success-700'];
                        }

                        return ['class' => 'text-danger-700'];
                    })
                    ->label(__('fields.paid_amount_percent'))
                    ->getStateUsing(function ($record) {
                        return format_amount(percent($record->invoice->total_paid, $record->invoice->getItemsCost(true, true, true))) . "%";
                    }),

                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->invoice->getItemsCost(true, true, true));
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                ])
            ])
            ->bulkActions([
            ]);
    }

    public static function shouldSupplierFieldBeVisible($livewire, $record): bool
    {
        return $livewire->data['for'] === "supplier" or $record?->invoice->for === "supplier";
    }

    public static function shouldSupplierFieldBeReadOnly($livewire, $record): bool
    {
        return self::isInvoiceReceived($livewire) or $record?->invoice != null;
    }


    public static function shouldAccountFieldBeReadOnly($livewire, $record): bool
    {
        return $record !== null;
    }

    public static function shouldInvoiceFieldBeReadOnly($livewire, $record): bool
    {
        return self::isInvoiceReceived($livewire) or $record?->invoice !== null;;
    }

    public static function shouldInvoiceDetailsBeVisible($livewire, $record): bool
    {
        return self::getInvoice($livewire, $record) !== null;
    }

    public static function getInvoice($livewire, $record): ?Invoice
    {
        if ($record)
            return $record->invoice;

        if ($livewire->data['received_invoice_id'])
            return Invoice::findOrFail($livewire->data['received_invoice_id']);

        return Invoice::find($livewire->data['invoice_id']);
    }

    public static function isInvoiceReceived($livewire): bool
    {
        return $livewire->data['received_invoice_id'] !== null;
    }

    public static function updateInvoiceProperties($invoice, $livewire)
    {
        if ($invoice) {
            $livewire->data['for'] = $invoice->for;
            $livewire->data['total_invoice'] = format_amount($invoice->getItemsCost(true, true, true));
            $livewire->data['total_paid_amount'] = format_amount($invoice->total_paid);
            $livewire->data['total_unpaid_amount'] = format_amount($invoice->total_unpaid);
        } else {
            if ($livewire->data['supplier_id'])
                $livewire->data['for'] = "supplier";

        }
    }

    public static function updatePayments($invoice, $livewire)
    {
        $total_payments = 0;

        foreach ($livewire->data['payments'] ?? [] as $item) {
            $amount = $item['amount'] ?? 0;

            if (is_number($amount))
                $total_payments += $amount;
        }

        $livewire->data['total_payments'] = format_amount($total_payments);

    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentVouchers::route('/'),
            'create' => Pages\CreatePaymentVoucher::route('/create'),
            'edit' => Pages\EditPaymentVoucher::route('/{record}/edit'),
        ];
    }
}

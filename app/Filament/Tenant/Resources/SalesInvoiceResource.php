<?php

    namespace App\Filament\Tenant\Resources;

    use App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;
    use App\Filament\Tenant\Resources\SalesInvoiceResource\RelationManagers;
    use App\Models\Invoice;
    use App\Models\InvoiceStatus;
    use App\Models\ReceiptVoucher;
    use Filament\Forms;
    use Filament\Resources\Resource;
    use Filament\Tables;
    use Illuminate\Database\Eloquent\Builder;

    class SalesInvoiceResource extends Resource
    {
        protected static ?string $model = Invoice::class;

        protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

        protected static ?int $navigationSort = 2;
        protected static ?string $slug = "invoices/sales";

        public static function getNavigationGroup(): ?string
        {
            return __('fields.invoices');
        }

        public static function getLabel(): ?string
        {
            return __('fields.sales_invoice');
        }


        public static function getPluralLabel(): ?string
        {
            return __('fields.sales_invoices');
        }

        public static function getNavigationBadge(): ?string
        {
            return Invoice::sales()->count();
        }

        public static function form(Forms\Form $form): Forms\Form
        {
            return $form
                ->schema(static::getFormSchema(Forms\Components\Card::class))
                ->columns([
                    'sm' => 3,
                    'lg' => null,
                ]);
        }

        public static function getFormSchema(string $layout = Forms\Components\Grid::class): array
        {
            return [
                Forms\Components\Group::make()
                    ->schema([
                    ]),
            ];
        }

        public static function table(Tables\Table $table): Tables\Table
        {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('no')
                        ->label(__('fields.invoice_no'))
                        ->searchable(),
                    Tables\Columns\TextColumn::make('invoice_status_id')
                        ->getStateUsing(function ($record) {
                            return $record->invoiceStatus->name;
                        })
                        ->label(__('fields.status'))
                        ->searchable(),

                    Tables\Columns\TextColumn::make('date')
                        ->label(__('fields.date'))
                        ->dateTime('M j, Y')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('client.name')
                        ->label(__('fields.client'))
                        ->searchable(),

                    Tables\Columns\TextColumn::make('paid_amount')
                        ->label(__('fields.paid_amount'))
                        ->getStateUsing(function ($record) {
                            return number_format($record->total_paid['sdg'], 2) . ' SDG';
                        }),

                    Tables\Columns\TextColumn::make('paid_amount_percent')
                        ->extraAttributes(function ($record) {
                            if (percent($record->total_paid['sdg'], $record->getItemsCost('SDG')) > 0) {
                                return ['class' => 'text-success-700'];
                            }

                            return ['class' => 'text-danger-700'];
                        })
                        ->label(__('fields.paid_amount_percent'))
                        ->getStateUsing(function ($record) {
                            return number_format(percent($record->total_paid['sdg'], $record->getItemsCost('SDG')), 2) . '%';
                        }),

                    Tables\Columns\TextColumn::make('invoice_total_sdg')
                        ->label(__('fields.invoice_total_sdg'))
                        ->getStateUsing(function ($record) {
                            return number_format($record->getItemsCost("SDG"), 2) . ' SDG';
                        }),

                    Tables\Columns\TextColumn::make('invoice_total_usd')
                        ->label(__('fields.invoice_total_usd'))
                        ->getStateUsing(function ($record) {
                            return number_format($record->getItemsCost("USD"), 2) . ' USD';
                        }),

                    Tables\Columns\TextColumn::make('exchange_rate')
                        ->label(__('fields.exchange_rate'))
                        ->searchable(),

                    Tables\Columns\TextColumn::make('additional_costs_total')
                        ->label(__('fields.additional_costs'))
                        ->getStateUsing(function ($record) {
                            return number_format($record->getAdditionalCosts("SDG"), 2) . ' SDG';
                        }),
                ])
                ->filters([
                    Tables\Filters\SelectFilter::make('invoice_status_id')
                        ->label(__('fields.status'))
                        ->options(InvoiceStatus::where('type', 'sales')->pluck('name', 'id'))
                ])
                ->actions([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\Action::make('complete_payment')
                            ->label(__('fields.complete_payment'))
                            ->icon('heroicon-o-pencil')
                            ->color('success')
                            ->visible(function ($record) {
                                return !$record->paid;
                            })
                            ->action(function (Invoice $record) {
                                if($record->payments->isEmpty())
                                {
                                    return redirect(ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->id]));
                                }

                                $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                                if($rv)
                                return redirect(ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id, 'rv' => $rv->id]));

                            }),

//                        Tables\Actions\Action::make('status')
//                            ->visible(function ($record) {
//                                return $record->locked_at == null;
//                            })
//                            ->color('success')
//                            ->icon('heroicon-o-pencil')
//                            ->label(__('fields.change_status'))
//                            ->modalWidth('lg')
//                            ->requiresConfirmation()
//                            ->form([
//                                Forms\Components\Card::make([
//                                    Forms\Components\Select::make('status')
//                                        ->reactive()
//                                        ->label(__('fields.status'))
//                                        ->options(InvoiceStatus::where('type', 'sales')->pluck('name', 'id'))
//                                        ->required()
//                                        ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
//                                            $component->state($record->invoice_status_id);
//                                        }),
//
//                                    Forms\Components\TextInput::make('info')
//                                        ->visible(fn(Forms\Get $get) => $get('status') == 5)
//                                        ->extraAttributes(['class' => 'text-danger-700'])
//                                        ->default(__('fields.invoice_will_be_locked_after_this_action'))
//                                        ->disabled(1)
//                                        ->label(__('fields.alert_info')),
//                                ])
//                            ])
//                            ->action(function ($record, array $data) {
//
//                                try {
//
//                                    DB::beginTransaction();
//
//                                    if ($data['status'] == 5) {
//                                        if (!can_lock_invoice())
//                                            Filament::notify('danger', __('fields.insufficient_permission'));
//
//                                        $record->markPaid();
//                                        $record->update(['invoice_status_id' => $data['status'], 'locked_at' => now()]);
//                                    } else {
//                                        $record->update(['invoice_status_id' => $data['status']]);
//                                    }
//
//                                    Notification::make()
//                                        ->title(__('fields.alert_info'))
//                                        ->body(__('fields.invoice_updated'))
//                                        ->warning()
//                                        ->persistent()
//                                        ->send();
//
//                                    DB::commit();
//
//                                } catch (\Exception $exception) {
//                                    DB::rollBack();
//                                    Filament::notify('danger', $exception->getMessage());
//                                }
//
//                            }),

                    ]),
                ])
                ->bulkActions([
                ]);
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
                'index' => \App\Filament\Tenant\Resources\SalesInvoiceResource\Pages\ListSalesInvoices::route('/'),
                'create' => \App\Filament\Tenant\Resources\SalesInvoiceResource\Pages\CreateCustomSalesInvoice::route('/create'),
                'edit' => \App\Filament\Tenant\Resources\SalesInvoiceResource\Pages\EditCustomSalesInvoice::route('/{record}/edit'),
            ];
        }

        public static function getEloquentQuery(): Builder
        {
            return parent::getEloquentQuery()
                ->sales()
                ->with(
                    [
                        'invoiceStatus',
                        'items',
                        'payments',
                        'client',
                        'receiptVoucher',
                        'representative',
                        'client',
                        'user',
                        'reviewedBy',
                        'additionalCosts',
                    ])->latest();
        }
    }

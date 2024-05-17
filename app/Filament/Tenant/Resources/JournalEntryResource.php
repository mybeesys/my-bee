<?php

    namespace App\Filament\Tenant\Resources;

    use App\Filament\Tenant\Resources\JournalEntryResource\Pages;
    use App\Filament\Tenant\Resources\JournalEntryResource\RelationManagers;
    use App\Models\Acc4;
    use App\Models\Currency;
    use App\Models\CashDet;
    use Filament\Facades\Filament;
    use Filament\Forms;
    use Filament\Resources\Resource;
    use Filament\Tables;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\SoftDeletingScope;

    class JournalEntryResource extends Resource
    {
        protected static ?string $model = CashDet::class;

        protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

        protected static ?string $recordTitleAttribute = "invoice_id";

        protected static ?string $slug = "transactions/journal-entries";

        protected static ?int $navigationSort = 3;

        public static function getNavigationGroup(): ?string
        {
            return __('fields.finance');
        }

        public static function getLabel(): ?string
        {
            return __('fields.journal_entry');
        }

        public static function getPluralLabel(): ?string
        {
            return __('fields.journal_entries');
        }

        public static function getNavigationBadge(): ?string
        {
            return static::getModel()::count();
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
                    ])->columnSpan([
                        'sm' => 3,
                    ]),
            ];
        }

        public static function table(Tables\Table $table): Tables\Table
        {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('operation.no')
                        ->toggleable()
                        ->searchable()
                        ->extraAttributes(['class' => 'text-danger-700'])
                        ->label(__('fields.voucher_no')),

                    Tables\Columns\TextColumn::make('date')
                        ->toggleable()
                        ->label(__('fields.date')),

                    Tables\Columns\TextColumn::make('account.name')
                        ->toggleable()
                        ->searchable()
                        ->label(__('fields.account')),

                    Tables\Columns\TextColumn::make('transaction_id')
                        ->toggleable()
                        ->searchable()
                        ->label(__('fields.transaction_id')),

                    Tables\Columns\TextColumn::make('currency.name')
                        ->toggleable()
                        ->searchable()
                        ->label(__('fields.currency')),

                    Tables\Columns\TextColumn::make('amount_in')
                        ->toggleable()
                        ->getStateUsing(function ($record) {
                            return number_format($record->amount_in, 2);
                        })
                        ->extraAttributes(['class' => 'text-success-700'])
                        ->label(__('fields.debit')),

                    Tables\Columns\TextColumn::make('amount_out')
                        ->toggleable()
                        ->getStateUsing(function ($record) {
                            return number_format($record->amount_out, 2);
                        })
                        ->extraAttributes(['class' => 'text-danger-700'])
                        ->label(__('fields.credit')),

                    Tables\Columns\TextColumn::make('statement')
                        ->getStateUsing(function ($record) {
                            return strip_tags($record->statement);
                        })
                        ->toggleable()
                        ->label(__('fields.statement')),

                    Tables\Columns\TextColumn::make('exchange_rate')
                        ->toggleable()
                        ->getStateUsing(function ($record) {
                            return number_format($record->exchange_rate, 2);
                        })
                        ->label(__('fields.exchange_rate')),

                    Tables\Columns\TextColumn::make('operation.files')
                        ->toggleable()
                        ->getStateUsing(function ($record) {
                            if (!$record->operation->files) return 0;
                            return count($record->operation->files);
                        })
                        ->label(__('fields.files')),

                    Tables\Columns\BooleanColumn::make('operation.locked_at')
                        ->toggleable()
                        ->getStateUsing(function ($record) {
                            return $record->operation->locked_at == null ? false : true;
                        })
                        ->label(__('fields.locked')),

                    Tables\Columns\TextColumn::make('created_at')
                        ->toggleable()
                        ->label(__('fields.created_at'))
                        ->dateTime('M j, Y')
                        ->sortable(),

                ])
                ->filters([
                    Tables\Filters\SelectFilter::make('currency_id')
                        ->label(__('fields.currency'))
                        ->relationship('currency', 'name'),

                    Tables\Filters\SelectFilter::make('op_id')
                        ->searchable()
//                        ->multiple()
                        ->label(__('fields.voucher_no'))
                        ->relationship('operation', 'no'),

                    Tables\Filters\SelectFilter::make('transaction_id')
                        ->searchable()
                        ->options(CashDet::pluck('transaction_id', 'transaction_id')->unique()->toArray())
                        ->label(__('fields.transaction_id')),

                ])
                ->actions([
                    Tables\Actions\ActionGroup::make([
//                        Tables\Actions\EditAction::make(),
                        Tables\Actions\Action::make('lock_unlock')
                            ->visible(function ($record) {
                                return $record->operation->locked_at == null;
                            })
                            ->modalWidth('lg')
                            ->requiresConfirmation()
                            ->color('danger')
                            ->icon(function ($record){
                                if ($record->operation->locked_at == null) {
                                    return 'heroicon-o-lock-closed';
                                }
                                return 'heroicon-o-lock-open';
                            })
                            ->action(function ($record) {
//                                !auth()->user()->isSuperAdmin()
                                if(!can_lock_journal_entry())
                                {
                                    Filament::notify('danger', __('fields.insufficient_permission'));
                                    return;
                                }

                                $record->operation->update(['locked_at' => now()]);

                                Filament::notify('success', __('fields.alert_invoice_locked'));

                            })
                    ]),

                ])
                ->bulkActions([
                    Tables\Actions\ExportBulkAction::make('export')
                        ->fileName('My File') // Default file name
                        ->timeFormat('m y d') // Default time format for naming exports
                        ->defaultFormat('pdf') // xlsx, csv or pdf
                        ->defaultPageOrientation('landscape') // Page orientation for pdf files. portrait or landscape
//                        ->directDownload() // Download directly without showing modal
//                        ->disableAdditionalColumns() // Disable additional columns input
//                        ->disableFilterColumns() // Disable filter columns input
//                        ->disableFileName() // Disable file name input
//                        ->disableFileNamePrefix() // Disable file name prefix
//                        ->disablePreview() // Disable export preview
                        ->withHiddenColumns() //Show the columns which are toggled hidden
//                        ->fileNameFieldLabel('File Name') // Label for file name input
//                        ->formatFieldLabel('Format') // Label for format input
//                        ->pageOrientationFieldLabel('Page Orientation') // Label for page orientation input
//                        ->filterColumnsFieldLabel('filter columns') // Label for filter columns input
//                        ->additionalColumnsFieldLabel('Additional Columns') // Label for additional columns input
//                        ->additionalColumnsTitleFieldLabel('Title') // Label for additional columns' title input
//                        ->additionalColumnsDefaultValueFieldLabel('Default Value') // Label for additional columns' default value input
//                        ->additionalColumnsAddButtonLabel('Add Column') // Label for additional columns' add button
                ]);
        }

        public static function getEloquentQuery(): Builder
        {
            return parent::getEloquentQuery()->with(['operation', 'account', 'currency', 'invoice'])->latest();
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
                'index' => Pages\ListJournalEntries::route('/'),
                'create' => Pages\CreateCustomJournalEntry::route('/create'),
//                'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
            ];
        }

        public static function shouldRegisterNavigation(): bool
        {
            return false;
        }
    }

<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\BankAccountResource\Pages;
use App\Models\Acc4;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class BankAccountResource extends Resource
{
    protected static ?string $model = Acc4::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'settings/bank-accounts';

    public static function getLabel(): ?string
    {
        return __('fields.bank_account');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.bank_accounts');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('fields.bank_account'))
                    ->description(__('fields.bank_account_form_hint'))
                    ->schema([
                        hidden_tenant_id_field(),

                        Forms\Components\Hidden::make('acc3_code')
                            ->default('1227'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->autofocus()
                            ->maxLength(255)
                            ->rules([new UniqueTenantItemRule(Acc4::class, 'name', $form->getRecord()?->id)])
                            ->label(__('fields.name'))
                            ->placeholder(__('fields.bank_account_name_placeholder')),

                        Forms\Components\TextInput::make('meta.account_number')
                            ->label(__('fields.bank_account_number'))
                            ->maxLength(50),

                        Forms\Components\TextInput::make('meta.iban')
                            ->label(__('fields.iban'))
                            ->maxLength(34),

                        Forms\Components\Toggle::make('meta.is_default')
                            ->label(__('fields.default_bank_account'))
                            ->helperText(__('fields.default_bank_account_hint'))
                            ->default(fn (): bool => ! Acc4::query()->bankAccounts()->exists()),
                    ])
                    ->columns(1),

                View::make('components.loading'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.bank_accounts_empty'))
            ->emptyStateDescription(__('fields.bank_accounts_empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->description(fn (Acc4 $record): ?string => $record->isDefaultBankAccount()
                        ? __('fields.main_bank_account_description')
                        : null)
                    ->searchable(),
                Tables\Columns\TextColumn::make('meta.account_number')
                    ->label(__('fields.bank_account_number'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('meta.iban')
                    ->label(__('fields.iban'))
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('meta.is_default')
                    ->label(__('fields.default_bank_account'))
                    ->boolean()
                    ->getStateUsing(fn (Acc4 $record): bool => $record->isDefaultBankAccount()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Acc4 $record): bool => $record->canBeEdited())
                        ->mutateFormDataUsing(function (array $data, Acc4 $record): array {
                            $data['meta'] = array_merge($record->meta ?? [], $data['meta'] ?? []);

                            return $data;
                        })
                        ->after(function (Acc4 $record, array $data): void {
                            if ($data['meta']['is_default'] ?? false) {
                                $record->markAsDefaultBankAccount();
                            }
                        }),
                    Tables\Actions\Action::make('mark_as_default')
                        ->label(__('fields.mark_bank_as_default'))
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->visible(fn (Acc4 $record): bool => $record->canBeEdited() && ! $record->isDefaultBankAccount())
                        ->requiresConfirmation()
                        ->action(function (Acc4 $record): void {
                            $record->markAsDefaultBankAccount();
                            fns()->saved();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Acc4 $record): bool => $record->canBeDeleted())
                        ->before(function (Acc4 $record): void {
                            if (! $record->canBeDeleted()) {
                                Notification::make()
                                    ->title(__('fields.record_in_use_alert'))
                                    ->warning()
                                    ->send();

                                $this->halt();
                            }
                        })
                        ->after(function (): void {
                            $banks = Acc4::query()->bankAccounts()->get();

                            if ($banks->isEmpty()) {
                                return;
                            }

                            if ($banks->contains(fn (Acc4 $account): bool => $account->isDefaultBankAccount())) {
                                return;
                            }

                            $banks->first()->markAsDefaultBankAccount();
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->bankAccounts()
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBankAccounts::route('/'),
        ];
    }
}

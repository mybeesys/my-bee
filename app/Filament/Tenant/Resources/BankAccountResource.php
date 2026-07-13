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
        $isEditing = $form->getRecord() !== null;

        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    hidden_tenant_id_field(),

                    Forms\Components\Hidden::make('acc3_code')
                        ->default('1227'),

                    Forms\Components\TextInput::make('code')
                        ->disabled()
                        ->dehydrated()
                        ->visible($isEditing)
                        ->label(__('fields.code')),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->rules([new UniqueTenantItemRule(Acc4::class, 'name', $form->getRecord()?->id)])
                        ->label(__('fields.name')),

                    Forms\Components\TextInput::make('meta.account_number')
                        ->label(__('fields.bank_account_number')),

                    Forms\Components\TextInput::make('meta.iban')
                        ->label(__('fields.iban')),
                ])->columns($isEditing ? 2 : 1),

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
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('fields.code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('meta.account_number')
                    ->label(__('fields.bank_account_number'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('meta.iban')
                    ->label(__('fields.iban'))
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Acc4 $record): bool => $record->canBeEdited()),
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

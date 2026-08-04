<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\Acc4Resource\Pages;
use App\Models\Acc4;
use App\Rules\UniqueAcc4OtherPartyNameRule;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;

class Acc4Resource extends Resource
{
    protected static ?string $model = Acc4::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'finance/tree-accounts/level-four';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.finance');
    }

    public static function getLabel(): ?string
    {
        return __('fields.other_party_account');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.other_party_accounts');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->userCreatedOtherPartyAccounts()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    hidden_tenant_id_field(),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->rules([
                            new UniqueAcc4OtherPartyNameRule($form->getRecord()?->getKey()),
                        ])
                        ->label(__('fields.name')),
                ]),

                View::make('components.loading'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.other_party_accounts_empty'))
            ->emptyStateDescription(__('fields.other_party_accounts_empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(),
            ])
            ->filters([])
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->userCreatedOtherPartyAccounts()
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAcc4s::route('/'),
        ];
    }
}

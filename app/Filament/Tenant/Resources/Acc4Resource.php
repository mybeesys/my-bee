<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\Acc4Resource\Pages;
use App\Models\Acc3;
use App\Models\Acc4;
use App\Models\Client;
use App\Models\Representative;
use App\Models\Supplier;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

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
        return (string) static::getModel()::query()->excludeInventoryItems()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $isEditing = $form->getRecord() !== null;

        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    hidden_tenant_id_field(),

                    Forms\Components\Select::make('acc3_code')
                        ->required()
                        ->disabled($isEditing)
                        ->dehydrated()
                        ->options(Acc3::pluck('name', 'code'))
                        ->label(__('fields.acc3_code'))
                        ->live()
                        ->hint(function ($state) {
                            if ($acc3 = Acc3::firstWhere('code', $state)) {
                                return $acc3->code;
                            }
                        }),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->disabled($isEditing)
                        ->dehydrated()
                        ->rules([new UniqueTenantItemRule(Acc4::class, 'code', $form->getRecord()?->id)])
                        ->label(__('fields.code')),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->rules([new UniqueTenantItemRule(Acc4::class, 'name', $form->getRecord()?->id)])
                        ->label(__('fields.name')),

                ])->columns(3),

                View::make('components.loading'),

            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('fields.code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('acc3_code')
                    ->searchable()
                    ->description(function ($record) {
                        return $record->acc3->code;
                    })
                    ->getStateUsing(function ($record) {
                        return $record->acc3->name;
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('type')
                    ->label(__('fields.type'))
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label(__('fields.type'))
                            ->options([
                                'clients' => __('fields.clients'),
                                'suppliers' => __('fields.suppliers'),
                                'representatives' => __('fields.representatives'),
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['type'] == 'clients',
                                fn (Builder $query) => $query->where('item_type', Client::class))
                            ->when($data['type'] == 'suppliers',
                                fn (Builder $query) => $query->where('item_type', Supplier::class))
                            ->when($data['type'] == 'representatives',
                                fn (Builder $query) => $query->where('item_type', Representative::class));
                    }),
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
            ->excludeInventoryItems()
            ->with(['acc3'])
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAcc4s::route('/'),
        ];
    }
}

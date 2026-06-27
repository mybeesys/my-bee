<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SupplierResource\Pages;
use App\Filament\Tenant\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use App\Rules\InternationalPhoneRule;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = "suppliers";

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.suppliers', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_clients_and_suppliers');
    }

    public static function getLabel(): ?string
    {
        return __('fields.supplier');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.suppliers');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getQuickCreateSchema(): array
    {
        return [
            Forms\Components\Section::make([
                hidden_tenant_id_field(),

                Forms\Components\TextInput::make('name')
                    ->label(__('fields.supplier_name'))
                    ->autofocus()
                    ->required()
                    ->maxLength(255),
            ]),
        ];
    }

    public static function getSchema(): array
    {
        return [
            Forms\Components\Section::make([

                hidden_tenant_id_field(),

                Forms\Components\TextInput::make('name')
                    ->label(__('fields.supplier_name'))
                    ->autofocus()
                    ->required(),

                Forms\Components\TextInput::make('phone')
                    ->label(__('fields.phone'))
                    ->placeholder('966xxxxxxxxx')
                    ->rules([new InternationalPhoneRule(false)])
                    ->nullable(),

                Forms\Components\TextInput::make('company')
                    ->label(__('fields.company'))
                    ->nullable(),

                Forms\Components\TextInput::make('address')
                    ->type('address')
                    ->label(__('fields.address'))
                    ->nullable(),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->label(__('fields.email'))
                    ->nullable(),

            ])->columns(2),

            Forms\Components\Section::make([
                Forms\Components\Textarea::make('notes')
                    ->cols(10)
                    ->rows(5)
                    ->label(__('fields.notes')),
            ]),

            View::make('components.loading'),

        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema(self::getSchema());
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.supplier_name'))
                    ->searchable()
                    ->url(fn (Supplier $record) => static::getUrl('view', ['record' => $record->id])),
                Tables\Columns\TextColumn::make('phone')->label(__('fields.phone'))->searchable(),
                Tables\Columns\TextColumn::make('address')->label(__('fields.address'))->searchable(),
                Tables\Columns\TextColumn::make('company')->label(__('fields.company'))->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('fields.email'))->searchable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('fields.updated_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([

                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->form([

                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->modalWidth(MaxWidth::SevenExtraLarge),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('primary')
                    ->extraAttributes(['class' => 'document-list-row-actions']),
            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchaseInvoicesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['acc4'])->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'view' => Pages\ViewSupplier::route('/{record}/view'),
        ];
    }

}

<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CustomerResource\Pages;
use App\Filament\Tenant\Resources\CustomerResource\RelationManagers;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\State;
use App\Rules\InternationalPhoneRule;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = "customers";

    protected static ?int $navigationSort = 1;

    public static function getLabel(): ?string
    {
        return __('fields.client');
    }

    public static function getNavigationSort(): ?int
    {
        return user_setting('fav.customers', false) ? 1 : 2;
    }

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.customers', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_clients_and_suppliers');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.clients');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                self::getSchema()[0]
            ])->columns(4);
    }

    public static function getSchema(): array
    {
        return [

            Forms\Components\Section::make([
                hidden_tenant_id_field(),

                Forms\Components\TextInput::make('name')
                    ->label(__('fields.name'))
                    ->required()
                    ->autofocus(),

                Forms\Components\TextInput::make('phone')
                    ->label(__('fields.phone'))
                    ->placeholder('966xxxxxxxxx')
                    ->tel()
                    ->rules([
                        new InternationalPhoneRule(false)
                    ])
                    ->nullable(),

                Forms\Components\TextInput::make('trn')
                    ->label(__('fields.trn')),

                Forms\Components\TextInput::make('email')
                    ->label(__('fields.email'))
                    ->email(),

                Forms\Components\Hidden::make('country_id')
                    ->dehydrated(false)
                    ->default(Country::firstWhere('dial_code', '966')->id),

                Forms\Components\Fieldset::make()->schema([
                    Select::make('state_id')
                        ->label(__('fields.district'))
                        ->live()
                        ->required()
                        ->searchable()
//                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, Forms\Set $set){
                            $set('city_id', null);
                            $set('area_id', null);
                        })
                        ->options(State::pluck('name', 'id')),

                    Select::make('city_id')
                        ->visible(function (Forms\Get $get){
                            return State::where('id', $get('state_id'))->has('cities')->count() > 0;
                        })
                        ->live()
                        ->label(__('fields.city'))
                        ->required()
                        ->searchable()
                        ->afterStateUpdated(function ($state, Forms\Set $set){
                            $set('area_id', null);
                        })
                        ->options(function (Forms\Get $get) {
                            $state_id = $get('state_id');
                            if ($state_id) {
                                return City::with('areas')->where('state_id', $state_id)->has('areas')->pluck('name', 'id');
                            }
                            return [];
                        }),

                    Select::make('area_id')
                        ->visible(function (Forms\Get $get){
                            return City::where('id', $get('city_id'))->has('areas')->count() > 0;
                        })
                        ->label(__('fields.area'))
                        ->required()
                        ->searchable()
                        ->options(function (Forms\Get $get) {
                            $city_id = $get('city_id');
                            if ($city_id) {
                                return Area::where('city_id', $city_id)->pluck('name', 'id');
                            }
                            return [];
                        }),

                    Forms\Components\TextInput::make('delivery_address')
                        ->label(__('fields.delivery_address'))
                        ->type('address')
                        ->required(),
                ])->columns(4),

            ])->columns(4),

            View::make('components.loading'),

        ];
    }

    public static function mutateEditFormData(array $data, Customer $record): array
    {
        $record->loadMissing('city');
        $data['state_id'] = $record->city?->state_id ?? $record->state_id ?? null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([

                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.reference_code'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('fields.phone'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('delivery_address')
                    ->label(__('fields.delivery_address'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('fields.email'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label(__('fields.orders'))
                    ->toggleable()
                    ->counts('orders'),

                Tables\Columns\TextColumn::make('trn')
                    ->label(__('fields.trn'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.join_date'))
                    ->dateTime('M j, Y h:i')
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
                        ->mutateRecordDataUsing(fn (array $data, Customer $record) => static::mutateEditFormData($data, $record))
                        ->modalWidth(MaxWidth::SevenExtraLarge),
                    Tables\Actions\DeleteAction::make()
                        ->action(function ($record) {
                            try {
                                $record->delete();
                                fns()->deleted();
                            } catch (\Throwable $throwable) {
                                fns()->displayException($throwable);
                            }
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('primary')
                    ->extraAttributes(['class' => 'document-list-row-actions']),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['orders.details', 'invoices', 'city.state', 'area'])->latest(); // TODO: Change the autogenerated stub
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}/view'),
        ];
    }

}

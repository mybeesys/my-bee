<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TaxProfileResource\Pages;
use App\Filament\Tenant\Resources\TaxProfileResource\RelationManagers;
use App\Models\Tax;
use App\Models\TaxProfile;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxProfileResource extends Resource
{
    protected static ?string $model = TaxProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = "settings/tax-profiles";

    protected static ?string $recordTitleAttribute = "name";

    public static function getNavigationGroup(): ?string
    {
        return "";
    }

    public static function getLabel(): ?string
    {
        return __('fields.tax_profile');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.tax_profiles');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columnSpan(1)->schema([

                        hidden_tenant_id_field(),

                        Forms\Components\TextInput::make('name')
                            ->label(__('fields.name'))
                            ->placeholder(__('fields.tax_profile_name_place_holder'))
                            ->required()
                            ->maxLength(255)
                            ->rules([new UniqueTenantItemRule(TaxProfile::class, 'name', $form->getRecord()?->id)]),

                        Forms\Components\Repeater::make("taxes")
                            ->relationship('taxes')
                            ->label("")
                            ->defaultItems(2)
                            ->reorderable()
                            ->columns(2)
                            ->addActionLabel(__('fields.add'))
                            ->schema([

                                hidden_tenant_id_field(),
//                            new UniqueTenantItemRule(Tax::class, 'name', dd($form->getLivewire()))
                                Forms\Components\TextInput::make('description')
                                    ->label(__('fields.tax_description'))
                                    ->placeholder(__('fields.tax_description_place_holder'))
                                    ->required()
                                    ->maxLength(255)
                                    ->rules([
                                        function ($component, $record) {
                                            return new UniqueTenantItemRule(Tax::class, 'description', $record?->id);
                                        }
                                    ]),

                                Forms\Components\TextInput::make('percent')
                                    ->label(__('fields.tax_percent'))
                                    ->placeholder("%")
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100),
                            ])
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                ->label(__('fields.name'))
                ->searchable(),

                Tables\Columns\TextColumn::make('taxes_count')
                    ->label(__('fields.taxes'))
                    ->counts('taxes')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_percentages')
                    ->formatStateUsing(function (TaxProfile $record){
                        return $record->total_percentages."%";
                    })
                    ->label(__('fields.total_percentages')),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
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
            'index' => Pages\ListTaxProfiles::route('/'),
            'create' => Pages\CreateTaxProfile::route('/create'),
            'edit' => Pages\EditTaxProfile::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['taxes'])->latest();
    }

}

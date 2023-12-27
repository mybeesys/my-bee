<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\VariantLibraryResource\Pages;
use App\Filament\Tenant\Resources\VariantLibraryResource\RelationManagers;
use App\Models\VariantLibrary;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VariantLibraryResource extends Resource
{
    protected static ?string $model = VariantLibrary::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $slug = "products/variant-library";

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('fields.variant_library');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.variant_libraries');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('fields.products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([

                    Forms\Components\TextInput::make('name_ar')
                        ->required()
                        ->label(__('fields.option_name_ar'))
                        ->placeholder(__('fields.variant_library_name_ar_placeholder')),

                    Forms\Components\TextInput::make('name_en')
                        ->required()
                        ->label(__('fields.option_name_en'))
                        ->placeholder(__('fields.variant_library_name_en_placeholder')),

                ])->columns(2),

                Forms\Components\Section::make(__('fields.values'))->schema([
                    TableRepeater::make('options')
                        ->relationship('options')
                        ->orderColumn()
                        ->required()
                        ->minItems(1)
                        ->label("")
                        ->addActionLabel(__('fields.add'))
                        ->alignHeaders(fn() => app()->getLocale() == "ar" ? "right" : "left")
                        ->defaultItems(1)
                        ->hideLabels()
                        ->columnSpan('full')
                        ->columnWidths([
                            'name_ar' => '200px',
                            'name_en' => '200px',
                        ])
                        ->live(true)
                        ->schema([

                            hidden_tenant_id_field(),

                            Forms\Components\TextInput::make('name_ar')
                                ->required()
                                ->label(__('fields.value_name_ar'))
                                ->placeholder(__('fields.variant_library_value_name_ar_placeholder')),

                            Forms\Components\TextInput::make('name_en')
                                ->required()
                                ->label(__('fields.value_name_en'))
                                ->placeholder(__('fields.variant_library_value_name_en_placeholder')),

                        ])
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name_ar')
                    ->label(__('fields.option_name_ar'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('name_en')
                    ->label(__('fields.option_name_en'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('options_count')
                    ->counts('options')
                    ->label(__('fields.values')),

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['options'])->latest();
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
            'index' => Pages\ListVariantLibraries::route('/'),
            'create' => Pages\CreateVariantLibrary::route('/create'),
            'edit' => Pages\EditVariantLibrary::route('/{record}/edit'),
        ];
    }
}

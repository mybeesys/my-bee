<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CategoryResource\Pages;
use App\Filament\Tenant\Resources\CategoryResource\RelationManagers;
use App\Filament\Tenant\Resources\CustomerResource\Widgets\CustomerOverview;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = Category::class;

    protected static ?string $recordTitleAttribute = "name";

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $slug = "shop/categories";


    public static function getTranslatableLocales(): array
    {
        return config('system.supported_languages', []);
    }

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.products_categories', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_store');
    }

    public static function getLabel(): ?string
    {
        return __('fields.category');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.categories');
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
                    ->schema([
                        TextInput::make('name')
                            ->label(__('fields.name'))
                            ->autofocus()
                            ->required(),

//                        Select::make('parent_id')
//                            ->label(__('fields.category_parent'))
//                            ->options(function ($operation, $record) {
//                                if ($operation == "edit")
//                                    return Category::canBecomeParent($ignore_ids = [$record->id])->pluck('name', 'id');
//
//                                return Category::canBecomeParent()->pluck('name', 'id');
//                            })
//                            ->searchable(),

                        Forms\Components\TextInput::make('sort')
                            ->label(__('fields.sort'))
                            ->required()
                            ->default(1)
                            ->numeric(),


                        hidden_tenant_id_field()

                    ])->columns(3),
                View::make('components.loading'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        //php artisan make:filament-resource Banner
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort')
                    ->label(__('fields.sort'))
                    ->getStateUsing(function ($record) {
                        return "#" . $record->sort;
                    }),
                Tables\Columns\TextColumn::make('name')->label(__('fields.name')),
//                Tables\Columns\TextColumn::make('parent.name')->label(__('fields.category_parent')),
//                Tables\Columns\TextColumn::make('all_children_count')->counts('allChildren')->label(__('fields.category_subs')),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label(__('fields.products')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])->actions([

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->action(function (Category $record) {
                            if ($record->children->isNotEmpty()) {
                                Notification::make()
                                    ->title("Item has children and cannot be deleted.")
                                    ->warning()
                                    ->send();
                                return;
                            } else if ($record->products->isNotEmpty()) {
                                Notification::make()
                                    ->title("Item has products and cannot be deleted.")
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $record->delete();

                            Notification::make()
                                ->title("Item deleted")
                                ->success()
                                ->send();

                            if ($record->parent) {
                                Cache::clear("sub-of-" . $record->parent->slug);
                            }
                        })
                        ->requiresConfirmation()
                        ->color('danger'),
                ]),

            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Tenant\Resources\CategoryResource\RelationManagers\ChildrenRelationManager::class,
            \App\Filament\Tenant\Resources\CategoryResource\RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\CategoryResource\Pages\ListCategories::route('/'),
            'create' => \App\Filament\Tenant\Resources\CategoryResource\Pages\CreateCategory::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\CategoryResource\Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['parent', 'children', "allChildren"])->orderBy("sort");
    }


    public static function getWidgets(): array
    {
        return [
            \App\Filament\Tenant\Resources\CategoryResource\Widgets\CategoryOverview::class
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record); // TODO: Change the autogenerated stub
    }
}

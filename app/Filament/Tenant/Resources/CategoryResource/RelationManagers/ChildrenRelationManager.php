<?php

namespace App\Filament\Tenant\Resources\CategoryResource\RelationManagers;

use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'name';


    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.category_subs');
    }

    protected function getDeleteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('delete')
            ->icon('heroicon-o-rectangle-stack')
            ->action(function (Category $record) {
                if ($record->children->isNotEmpty()) {
                    Notification::make()
                        ->title("Item has children and cannot be deleted.")
                        ->warning()
                        ->send();
                } elseif ($record->products->isNotEmpty()) {
                    Notification::make()
                        ->title("Item has products and cannot be deleted.")
                        ->warning()
                        ->send();
                } else {
                    $record->delete();
                    Notification::make()
                        ->title("Item deleted")
                        ->success()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->color('danger');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make([
                    TextInput::make('name')->required(),
                    Select::make('parent_id')
                        ->options(Category::all()->pluck('name', 'id'))
                        ->searchable()
                ])
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('fields.name')),
                Tables\Columns\TextColumn::make('all_children_count')->counts('allChildren')->label(__('fields.category_subs')),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label(__('fields.products')),

            ])
            ->bulkActions([])
            ->actions([])
            ->filters([
                //
            ]);
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->children->isNotEmpty();
    }
}

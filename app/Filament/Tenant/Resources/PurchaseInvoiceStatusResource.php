<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PurchaseInvoiceStatusResource\Pages;
use App\Filament\Tenant\Resources\PurchaseInvoiceStatusResource\RelationManagers;
use App\Models\PurchaseInvoiceStatus;
use App\Rules\UniqueTenantItemRule;
use App\Services\RoleService;
use App\Services\TenantService;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;

class PurchaseInvoiceStatusResource extends Resource
{
    protected static ?string $model = PurchaseInvoiceStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = "name";

    protected static ?string $slug = "invoices/purchases/statuses";

    public static function getModelLabel(): string
    {
        return __('fields.purchase_invoice_status');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.purchase_invoice_statuses');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make()->schema([

                    TextInput::make('name')
                        ->label(__('fields.name'))
                        ->rules([new UniqueTenantItemRule(PurchaseInvoiceStatus::class, 'name', $form->getRecord()?->id)])
                        ->required()
                        ->maxLength(255),

                    Forms\Components\ColorPicker::make('color')
                        ->label(__('fields.color'))
                        ->required(),

                ])->columns(2),

                Section::make()->schema([

                    Forms\Components\Checkbox::make('locks_invoice')
                        ->label(__("fields.status_locks_invoice"))
                        ->live(),

                    Forms\Components\Checkbox::make('releases_stock')
                        ->label(__("fields.status_release_stock"))
                        ->live(),

                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label(__('fields.name')),

                Tables\Columns\ColorColumn::make('color')
                    ->label(__('fields.color')),

                Tables\Columns\IconColumn::make('default')
                    ->label(__('fields.default_status'))
                    ->tooltip("")
                    ->boolean(),

                Tables\Columns\IconColumn::make('releases_stock')
                    ->label(__('fields.status_release_stock'))
                    ->tooltip("")
                    ->boolean(),

                Tables\Columns\IconColumn::make('locks_invoice')
                    ->label(__('fields.status_locks_invoice'))
                    ->tooltip("")
                    ->boolean(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
//                    ->visible(fn(PurchaseInvoiceStatus $record) => $record->invoices->isEmpty() and !$record->default and !$record->system)
                    ->action(function (PurchaseInvoiceStatus $record, Tables\Actions\Action $action) {
                        if ($record->default) {
                            fns()->sendRecordInUse();
                            $action->cancel();
                        }
                        if ($record->system) {
                            fns()->sendRecordInUse();
                            $action->cancel();
                        }
                        if ($record->invoices->isNotEmpty()) {
                            fns()->sendRecordInUse();
                            $action->cancel();
                        }

                        fns()->deleted();
                    })
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
            'index' => Pages\ListPurchaseInvoiceStatuses::route('/'),
            'create' => Pages\CreatePurchaseInvoiceStatus::route('/create'),
            'edit' => Pages\EditPurchaseInvoiceStatus::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['invoices']);
    }
}

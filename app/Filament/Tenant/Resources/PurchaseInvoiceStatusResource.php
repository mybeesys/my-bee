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

                Section::make()
                    ->schema([
                        Forms\Components\Select::make('permission_type')
                            ->label(__("fields.status_who_can_use_this_status"))
                            ->required()
                            ->dehydrated(false)
                            ->live()
                            ->default('all-supervisors')
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state == "all-supervisors")
                                    $set('all_supervisors_can_change_to_status', 1);
                                else
                                    $set('all_supervisors_can_change_to_status', 0);
                            })
                            ->options([
                                'all-supervisors' => __('fields.status_all_supervisors'),
                                'specified-supervisors' => __('fields.status_specified_supervisors'),
                            ]),

                        Forms\Components\Select::make('supervisors')
                            ->visible(fn(Forms\Get $get) => $get('permission_type') == "specified-supervisors")
                            ->required()
                            ->label(__('fields.status_supervisors'))
                            ->multiple()
                            ->searchable()
                            ->options(TenantService::instance()->getUsers(filament()->getTenant()->id)->pluck('full_name', 'id')),

                        Forms\Components\Hidden::make('all_supervisors_can_change_to_status')->default(1),
                    ])->columns(2),


                Section::make()->schema([

                    Forms\Components\Checkbox::make('lock_change')
                        ->label(__("fields.status_lock_change"))
                        ->live(),

                    Forms\Components\Select::make('lock_change_supervisors')
                        ->label(__("fields.status_who_can_change_status_even_if_its_locked"))
                        ->helperText(__("fields.lock_change_supervisors_helper_text"))
                        ->visible(fn(Forms\Get $get) => $get('lock_change') == true)
                        ->multiple()
                        ->searchable()
                        ->options(TenantService::instance()->getUsers(filament()->getTenant()->id)->pluck('full_name', 'id')),
                ]),

                Section::make()->schema([

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

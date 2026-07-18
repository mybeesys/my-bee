<?php

namespace App\Filament\Tenant\Resources\SupplierResource\Pages;

use App\Filament\Tenant\Resources\SupplierResource;
use App\Services\SupplierAccountStatementService;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected static string $view = 'filament.tenant.resources.suppliers.view-supplier';

    public ?array $filters = [];

    public ?array $statement = null;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['acc4', 'supplyOrders', 'purchaseInvoices', 'city.state', 'area']);

        $this->filters = [
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ];

        $this->filtersForm->fill($this->filters);
        $this->loadStatement();
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([]);
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'filtersForm' => $this->filtersForm(
                $this->makeForm()
                    ->statePath('filters'),
            ),
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('fields.created_from'))
                            ->native(false)
                            ->maxDate(fn () => $this->filters['to'] ?? now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadStatement()),
                        DatePicker::make('to')
                            ->label(__('fields.created_until'))
                            ->native(false)
                            ->minDate(fn () => $this->filters['from'] ?? null)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadStatement()),
                    ])
                    ->columns(2),
            ]);
    }

    public function loadStatement(): void
    {
        try {
            $this->statement = app(SupplierAccountStatementService::class)->build(
                $this->record,
                $this->filters['from'] ?? null,
                $this->filters['to'] ?? null,
            );
        } catch (\Throwable $exception) {
            report($exception);
            $this->statement = null;

            Notification::make()
                ->title(__('fields.supplier_statement_load_error'))
                ->danger()
                ->send();
        }
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('fields.supplier_overview_tab');
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function configureEditAction(EditAction $action): void
    {
        $resource = static::getResource();

        $action
            ->authorize($resource::canEdit($this->getRecord()))
            ->form(fn (Form $form): Form => $resource::form($form))
            ->modalWidth(MaxWidth::SevenExtraLarge)
            ->after(function (): void {
                $this->record->refresh()->loadMissing(['acc4', 'supplyOrders', 'purchaseInvoices']);
                $this->loadStatement();
            });
    }
}

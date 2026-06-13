<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Pages\InventoryDetailReport;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class InventorySummaryReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?int $navigationSort = 7;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'reports/inventory-summary';

    protected static string $view = 'filament.tenant.pages.inventory-summary-report';

    public ?array $filters = [];

    public ?array $report = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.inventory_summary_report');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.inventory_summary_report');
    }

    public function mount(): void
    {
        $this->filters = [
            'from' => request('from', now()->startOfMonth()->format('Y-m-d')),
            'to' => request('to', now()->format('Y-m-d')),
            'warehouse_ids' => [],
            'product_ids' => [],
            'movement_types' => [],
        ];

        $this->form->fill($this->filters);
        $this->loadReport();
    }

    public function form(Form $form): Form
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
                            ->afterStateUpdated(fn () => $this->loadReport()),
                        DatePicker::make('to')
                            ->label(__('fields.created_until'))
                            ->native(false)
                            ->minDate(fn () => $this->filters['from'] ?? null)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadReport()),
                        Select::make('warehouse_ids')
                            ->label(__('fields.warehouse'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadReport()),
                        Select::make('product_ids')
                            ->label(__('fields.products'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadReport()),
                        Select::make('movement_types')
                            ->label(__('fields.inventory_movement_type'))
                            ->multiple()
                            ->options($this->movementTypeOptions())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadReport()),
                    ])
                    ->columns(2),
            ])
            ->statePath('filters');
    }

    public function loadReport(): void
    {
        try {
            $this->report = app(InventoryReportService::class)->buildSummary($this->filters ?? []);
        } catch (\Throwable $exception) {
            report($exception);
            $this->report = null;

            Notification::make()
                ->title(__('fields.inventory_report_load_error'))
                ->danger()
                ->send();
        }
    }

    public function detailUrl(array $row): string
    {
        return InventoryDetailReport::getUrl() . '?' . http_build_query(array_filter([
            'product_id' => $row['product_id'],
            'warehouse_id' => $row['warehouse_id'],
            'from' => $this->filters['from'] ?? null,
            'to' => $this->filters['to'] ?? null,
            'movement_types' => $this->filters['movement_types'] ?? [],
            'opening_inventory' => $row['opening_inventory'],
            'purchased_quantity' => $row['purchased_quantity'],
            'sales_quantity' => $row['sales_quantity'],
            'purchase_returns' => $row['purchase_returns'],
            'transferred_quantity' => $row['transferred_quantity'],
            'quantity_on_inventory' => $row['quantity_on_inventory'],
        ], fn ($value) => $value !== null && $value !== []));
    }

    /** @return array<string, string> */
    protected function movementTypeOptions(): array
    {
        return [
            InventoryReportService::TYPE_OPENING => __('fields.inventory_movement_opening'),
            InventoryReportService::TYPE_PURCHASE => __('fields.inventory_movement_purchase'),
            InventoryReportService::TYPE_SALES => __('fields.inventory_movement_sales'),
            InventoryReportService::TYPE_TRANSFER_IN => __('fields.inventory_movement_transfer_in'),
            InventoryReportService::TYPE_TRANSFER_OUT => __('fields.inventory_movement_transfer_out'),
            InventoryReportService::TYPE_PURCHASE_RETURN => __('fields.inventory_movement_purchase_return'),
            InventoryReportService::TYPE_SALES_RETURN => __('fields.inventory_movement_sales_return'),
        ];
    }
}

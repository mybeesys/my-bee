<?php

namespace App\Filament\Tenant\Pages;

use App\Models\InventoryReportLine;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryReportService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class InventoryDetailReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'reports/inventory-detail';

    protected static string $view = 'filament.tenant.pages.inventory-detail-report';

    public ?array $productFilter = [];

    public ?array $report = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.inventory_detail_report');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.inventory_detail_report');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();

        $movementTypes = request('movement_types', []);
        if (is_string($movementTypes)) {
            $movementTypes = array_filter(explode(',', $movementTypes));
        }

        $this->productFilter = [
            'product_id' => request('product_id') ?: Product::query()->orderBy('name')->value('id'),
        ];

        $this->tableFilters = [
            'report' => [
                'warehouse_id' => request('warehouse_id') ?: Warehouse::query()->orderBy('name')->value('id'),
                'from' => $this->normalizeFilterDate(request('from')) ?? now()->startOfMonth()->format('Y-m-d'),
                'to' => $this->normalizeFilterDate(request('to')) ?? now()->format('Y-m-d'),
                'movement_types' => is_array($movementTypes) ? $movementTypes : [],
            ],
        ];

        $this->productForm->fill($this->productFilter);
        $this->loadReport();
    }

    protected function getForms(): array
    {
        return ['productForm'];
    }

    public function productForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label(__('fields.product'))
                    ->searchable()
                    ->required()
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->resetTableRecordsCache();
                        $this->loadReport();
                    }),
            ])
            ->statePath('productFilter');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()->whereRaw('0 = 1'))
            ->heading(__('fields.inventory_item_ledger'))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.inventory_col_operation_date'))
                    ->date()
                    ->sortable(false),
                Tables\Columns\TextColumn::make('movement_label')
                    ->label(__('fields.inventory_col_operation')),
                Tables\Columns\ViewColumn::make('transfer_direction')
                    ->label(__('fields.inventory_col_transfer'))
                    ->view('filament.tenant.components.inventory-transfer-direction'),
                Tables\Columns\TextColumn::make('quantity_display')
                    ->label(__('fields.qty')),
                Tables\Columns\TextColumn::make('balance_after_display')
                    ->label(__('fields.inventory_col_balance_after'))
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('party')
                    ->label(__('fields.inventory_col_party'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('product_name')
                    ->label(__('fields.inventory_col_product'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('warehouse_name')
                    ->label(__('fields.inventory_col_branch'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('report')
                    ->columnSpanFull()
                    ->columns(4)
                    ->form([
                        Forms\Components\Select::make('warehouse_id')
                            ->label(__('fields.warehouse'))
                            ->searchable()
                            ->required()
                            ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                            ->live(),
                        Forms\Components\DatePicker::make('from')
                            ->label(__('fields.created_from'))
                            ->native(false)
                            ->maxDate(fn (Forms\Get $get) => $get('to') ?? now())
                            ->live(),
                        Forms\Components\DatePicker::make('to')
                            ->label(__('fields.created_until'))
                            ->native(false)
                            ->minDate(fn (Forms\Get $get) => $get('from'))
                            ->maxDate(now())
                            ->live(),
                        Forms\Components\Select::make('movement_types')
                            ->label(__('fields.inventory_movement_type'))
                            ->multiple()
                            ->options($this->movementTypeOptions())
                            ->live(),
                    ])
                    ->query(fn (Builder $query): Builder => $query),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->paginated(false)
            ->striped();
    }

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        if ($this->cachedTableRecords !== null) {
            return $this->cachedTableRecords;
        }

        $lines = $this->report['lines'] ?? [];

        return $this->cachedTableRecords = EloquentCollection::make(
            collect($lines)->map(function (array $line): InventoryReportLine {
                $record = new InventoryReportLine;
                $record->forceFill($line);
                $record->setAttribute($record->getKeyName(), (string) ($line['id'] ?? uniqid('line-', true)));
                $record->exists = true;

                return $record;
            })->all()
        );
    }

    public function getAllTableRecordsCount(): int
    {
        return count($this->report['lines'] ?? []);
    }

    public function updatedTableFilters(): void
    {
        $this->resetTableRecordsCache();
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $productId = $this->productFilter['product_id'] ?? null;
        $filterData = $this->tableFilters['report'] ?? [];

        if (blank($productId) || blank($filterData['warehouse_id'] ?? null)) {
            $this->report = null;
            $this->resetTableRecordsCache();

            return;
        }

        try {
            $this->report = app(InventoryReportService::class)->buildDetail([
                'from' => $filterData['from'] ?? null,
                'to' => $filterData['to'] ?? null,
                'product_id' => $productId,
                'warehouse_id' => $filterData['warehouse_id'] ?? null,
                'movement_types' => $filterData['movement_types'] ?? [],
            ]);

            $this->resetTableRecordsCache();
        } catch (\Throwable $exception) {
            report($exception);
            $this->report = null;
            $this->resetTableRecordsCache();

            Notification::make()
                ->title(__('fields.inventory_report_load_error'))
                ->danger()
                ->send();
        }
    }

    protected function resetTableRecordsCache(): void
    {
        $this->cachedTableRecords = null;
    }

    protected function normalizeFilterDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /** @return array<string, string> */
    protected function movementTypeOptions(): array
    {
        return [
            InventoryReportService::TYPE_PURCHASE => __('fields.inventory_movement_purchase'),
            InventoryReportService::TYPE_SALES => __('fields.inventory_movement_sales'),
            InventoryReportService::TYPE_PURCHASE_RETURN => __('fields.inventory_movement_purchase_return'),
            InventoryReportService::TYPE_SALES_RETURN => __('fields.inventory_movement_sales_return'),
        ];
    }
}

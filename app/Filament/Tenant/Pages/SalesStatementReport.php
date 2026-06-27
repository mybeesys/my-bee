<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesStatementLine;
use App\Services\SalesStatementService;
use Carbon\Carbon;
use Filament\Forms;
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

class SalesStatementReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'reports/sales-statement';

    protected static string $view = 'filament.tenant.pages.sales-statement-report';

    public ?array $report = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.sales_statement_report');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.sales_statement_report');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();

        $this->tableFilters = [
            'report' => [
                'from' => $this->normalizeFilterDate(request('from')) ?? now()->startOfMonth()->format('Y-m-d'),
                'to' => $this->normalizeFilterDate(request('to')) ?? now()->format('Y-m-d'),
                'customer_ids' => [],
                'product_ids' => [],
                'line_types' => [],
                'group_by' => 'product',
            ],
        ];

        $this->loadReport();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SalesStatementLine::query()->whereRaw('0 = 1'))
            ->heading($this->isInvoiceView()
                ? __('fields.sales_statement_by_invoice')
                : __('fields.sales_statement_detail'))
            ->columns($this->getTableColumns())
            ->filters([
                Tables\Filters\Filter::make('report')
                    ->columnSpanFull()
                    ->columns(3)
                    ->form([
                        Forms\Components\Select::make('group_by')
                            ->label(__('fields.sales_statement_group_by'))
                            ->options($this->groupByOptions())
                            ->default('product')
                            ->native(false)
                            ->live(),
                        Forms\Components\DatePicker::make('from')
                            ->label(__('fields.sales_statement_from'))
                            ->native(false)
                            ->maxDate(fn (Forms\Get $get) => $get('to') ?? now())
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('to')
                            ->label(__('fields.sales_statement_to'))
                            ->native(false)
                            ->minDate(fn (Forms\Get $get) => $get('from'))
                            ->maxDate(now())
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('line_types')
                            ->label(__('fields.sales_statement_movement_type'))
                            ->multiple()
                            ->options($this->lineTypeOptions())
                            ->live(),
                        Forms\Components\Select::make('customer_ids')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Customer::query()->orderBy('name')->pluck('name', 'id'))
                            ->live(),
                        Forms\Components\Select::make('product_ids')
                            ->label(__('fields.product'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->live(),
                    ])
                    ->query(fn (Builder $query): Builder => $query),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->paginated(false)
            ->striped();
    }

    /** @return array<int, Tables\Columns\Column> */
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('date')
                ->label(__('fields.date'))
                ->date(),
            Tables\Columns\TextColumn::make('invoice_no')
                ->label(__('fields.invoice_no'))
                ->searchable(),
            Tables\Columns\TextColumn::make('customer_name')
                ->label(__('fields.client'))
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('product_name')
                ->label(__('fields.product'))
                ->placeholder('—')
                ->visible(fn (): bool => ! $this->isInvoiceView()),
            Tables\Columns\TextColumn::make('line_type_label')
                ->label(__('fields.sales_statement_movement_type'))
                ->badge()
                ->color(fn (SalesStatementLine $record): string => match ($record->line_type) {
                    'return' => 'warning',
                    'mixed' => 'gray',
                    default => 'success',
                }),
            Tables\Columns\TextColumn::make('items_count')
                ->label(__('fields.sales_statement_products_count'))
                ->formatStateUsing(fn ($state): string => number_format((int) $state))
                ->alignCenter()
                ->visible(fn (): bool => $this->isInvoiceView()),
            Tables\Columns\TextColumn::make('qty')
                ->label(fn (): string => $this->isInvoiceView()
                    ? __('fields.sales_statement_invoice_qty')
                    : __('fields.qty'))
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('unit_price')
                ->label(__('fields.unit_price'))
                ->formatStateUsing(fn ($state): ?string => format_amount($state))
                ->visible(fn (): bool => ! $this->isInvoiceView()),
            Tables\Columns\TextColumn::make('discount')
                ->label(__('fields.discount'))
                ->formatStateUsing(fn ($state): ?string => format_amount($state)),
            Tables\Columns\TextColumn::make('tax')
                ->label(__('fields.tax'))
                ->formatStateUsing(fn ($state): ?string => format_amount($state)),
            Tables\Columns\TextColumn::make('total')
                ->label(fn (): string => $this->isInvoiceView()
                    ? __('fields.sales_statement_invoice_amount')
                    : __('fields.total'))
                ->formatStateUsing(fn ($state): ?string => format_amount($state))
                ->weight('medium'),
        ];
    }

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        if ($this->cachedTableRecords !== null) {
            return $this->cachedTableRecords;
        }

        $lines = $this->report['lines'] ?? [];

        return $this->cachedTableRecords = EloquentCollection::make(
            collect($lines)->map(function (array $line): SalesStatementLine {
                $record = new SalesStatementLine;
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
        $filterData = $this->tableFilters['report'] ?? [];

        try {
            $this->report = app(SalesStatementService::class)->build([
                'from' => $filterData['from'] ?? null,
                'to' => $filterData['to'] ?? null,
                'customer_ids' => $filterData['customer_ids'] ?? [],
                'product_ids' => $filterData['product_ids'] ?? [],
                'line_types' => $filterData['line_types'] ?? [],
                'group_by' => $filterData['group_by'] ?? 'product',
            ]);

            $this->resetTableRecordsCache();
        } catch (\Throwable $exception) {
            report($exception);
            $this->report = null;
            $this->resetTableRecordsCache();

            Notification::make()
                ->title(__('fields.sales_statement_load_error'))
                ->danger()
                ->send();
        }
    }

    protected function isInvoiceView(): bool
    {
        return ($this->tableFilters['report']['group_by'] ?? 'product') === 'invoice';
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
    protected function lineTypeOptions(): array
    {
        return [
            'sale' => __('fields.sales_statement_line_sale'),
            'return' => __('fields.sales_statement_line_return'),
        ];
    }

    /** @return array<string, string> */
    protected function groupByOptions(): array
    {
        return [
            'product' => __('fields.sales_statement_group_product'),
            'invoice' => __('fields.sales_statement_group_invoice'),
        ];
    }
}

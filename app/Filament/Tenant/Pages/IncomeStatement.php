<?php

namespace App\Filament\Tenant\Pages;

use App\Http\Controllers\Filament\Tenant\IncomeStatementExportController;
use App\Services\IncomeStatementService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

class IncomeStatement extends Page implements HasForms
{
    use InteractsWithForms;

    /** @todo Re-enable when export is ready */
    private const EXPORT_ACTIONS_ENABLED = false;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'reports/income-statement';

    protected static string $view = 'filament.tenant.pages.income-statement';

    public ?array $filters = [];

    public ?array $statement = null;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.income_statement');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.income_statement');
    }

    protected function getHeaderActions(): array
    {
        if (! self::EXPORT_ACTIONS_ENABLED) {
            return [];
        }

        return [
            Action::make('exportExcel')
                ->label(__('fields.income_statement_export_excel'))
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->outlined()
                ->visible(fn (): bool => filled($this->statement))
                ->action(fn () => $this->redirect($this->exportUrl('excel'), navigate: false)),
            Action::make('exportPdf')
                ->label(__('fields.income_statement_export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->outlined()
                ->visible(fn (): bool => filled($this->statement))
                ->action(fn () => $this->redirect($this->exportUrl('pdf'), navigate: false)),
        ];
    }

    protected function exportUrl(string $format): string
    {
        $query = array_filter([
            'from' => $this->normalizeFilterDate($this->filters['from'] ?? null),
            'to' => $this->normalizeFilterDate($this->filters['to'] ?? null),
        ]);

        $path = route('filament.tenant.pages.reports.income-statement.export', [
            'tenant' => filament()->getTenant(),
            'format' => $format,
        ], absolute: false);

        return $query === [] ? $path : $path . '?' . http_build_query($query);
    }

    public static function routes(Panel $panel): void
    {
        Route::get(static::getRoutePath(), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName());

        Route::get(static::getRoutePath() . '/export/{format}', IncomeStatementExportController::class)
            ->whereIn('format', ['pdf', 'excel'])
            ->middleware(static::getRouteMiddleware($panel))
            ->name(static::getRelativeRouteName() . '.export');
    }

    protected function normalizeFilterDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->startOfYear()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ];

        $this->form->fill($this->filters);
        $this->loadStatement();
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
                            ->displayFormat('Y-m-d')
                            ->format('Y-m-d')
                            ->maxDate(fn () => $this->filters['to'] ?? now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadStatement()),
                        DatePicker::make('to')
                            ->label(__('fields.created_until'))
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->format('Y-m-d')
                            ->minDate(fn () => $this->filters['from'] ?? null)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadStatement()),
                    ])
                    ->columns(2),
            ])
            ->statePath('filters');
    }

    public function loadStatement(): void
    {
        try {
            $this->statement = app(IncomeStatementService::class)->build(
                $this->filters['from'] ?? null,
                $this->filters['to'] ?? null,
            );
        } catch (\Throwable $exception) {
            report($exception);
            $this->statement = null;

            Notification::make()
                ->title(__('fields.income_statement_load_error'))
                ->danger()
                ->send();
        }
    }
}

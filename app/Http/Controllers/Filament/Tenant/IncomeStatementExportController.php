<?php

namespace App\Http\Controllers\Filament\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\IncomeStatementExportService;
use App\Services\IncomeStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IncomeStatementExportController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);

        $from = $this->normalizeDate($request->query('from'));
        $to = $this->normalizeDate($request->query('to'));

        $statement = app(IncomeStatementService::class)->build($from, $to);

        $exporter = app(IncomeStatementExportService::class);
        $filename = $exporter->makeFilename(
            $from,
            $to,
            $format === 'pdf' ? 'pdf' : 'xlsx',
        );

        if ($format === 'pdf') {
            return $exporter->downloadPdf($statement, $tenant->name, $filename);
        }

        return $exporter->downloadExcel($statement, $filename);
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}

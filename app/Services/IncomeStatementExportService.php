<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementExportService
{
    public function downloadExcel(array $statement, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($statement): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            foreach ($this->excelRows($this->normalizeStatementForExport($statement)) as $row) {
                $writer->addRow($this->makeExcelRow($row));
            }

            $writer->close();
        }, $this->sanitizeFilename($filename), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadPdf(array $statement, string $tenantName, string $filename)
    {
        $statement = $this->normalizeStatementForExport($statement);

        return Pdf::loadView('exports.income-statement-pdf', [
            'statement' => $statement,
            'tenantName' => $this->sanitizeText($tenantName),
            'isAr' => app()->getLocale() === 'ar',
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->download($this->sanitizeFilename($filename));
    }

    public function makeFilename(?string $from, ?string $to, string $extension): string
    {
        $fromPart = $from ?: 'all';
        $toPart = $to ?: 'all';

        return "income-statement-{$fromPart}-{$toPart}.{$extension}";
    }

    /**
     * @return array<int, array{cells: array<int, Cell>, height?: int}>
     */
    protected function excelRows(array $statement): array
    {
        $currency = $statement['currency'] ?? main_currency_iso_code();
        $rows = [];

        $rows[] = $this->excelDataRow([
            $this->textCell(__('fields.income_statement'), bold: true, size: 14),
            $this->textCell('', bold: true),
        ], 22);

        $rows[] = $this->excelDataRow([
            $this->textCell(filament()->getTenant()?->name ?? '', muted: true),
            $this->textCell(''),
        ]);

        $rows[] = $this->excelDataRow([
            $this->textCell(__('fields.income_statement_operational_basis'), muted: true),
            $this->textCell(''),
        ]);

        $rows[] = $this->excelDataRow([
            $this->textCell($this->periodLabel($statement), muted: true),
            $this->textCell(''),
        ]);

        $rows[] = $this->excelDataRow([$this->textCell(''), $this->textCell('')]);

        $rows[] = $this->excelDataRow([
            $this->textCell(__('fields.income_statement_summary'), bold: true),
            $this->textCell(__('fields.amount') . " ({$currency})", bold: true, align: CellAlignment::RIGHT),
        ], 20);

        foreach ($this->summaryItems($statement, $currency) as $item) {
            $rows[] = $this->excelDataRow([
                $this->textCell($item['label'], highlight: $item['highlight'] ?? false),
                $this->amountCell($item['amount'], highlight: $item['highlight'] ?? false),
            ]);
        }

        $rows[] = $this->excelDataRow([$this->textCell(''), $this->textCell('')]);

        $rows[] = $this->excelDataRow([
            $this->textCell(__('fields.income_statement_item'), bold: true, background: 'F3F4F6'),
            $this->textCell(__('fields.amount') . " ({$currency})", bold: true, align: CellAlignment::RIGHT, background: 'F3F4F6'),
        ], 20);

        $rows = array_merge($rows, $this->excelSectionRows(
            __('fields.income_statement_sales_section'),
            $statement['sales_lines'] ?? collect(),
            __('fields.income_statement_total_sales'),
            (float) ($statement['sales_total'] ?? 0),
        ));

        $rows = array_merge($rows, $this->excelSectionRows(
            __('fields.income_statement_purchases_section'),
            $statement['purchases_lines'] ?? collect(),
            __('fields.income_statement_total_purchases'),
            (float) ($statement['purchases_total'] ?? 0),
        ));

        $rows = array_merge($rows, $this->excelSectionRows(
            __('fields.income_statement_expense_section'),
            $statement['expense_lines'] ?? collect(),
            __('fields.income_statement_total_expenses'),
            (float) ($statement['expenses_total'] ?? 0),
        ));

        $rows[] = $this->excelDataRow([
            $this->textCell(__('fields.income_statement_net_income'), bold: true, background: 'E5E7EB'),
            $this->amountCell((float) ($statement['net_income'] ?? 0), bold: true, background: 'E5E7EB'),
        ], 22);

        return $rows;
    }

    /**
     * @return array<int, array{cells: array<int, Cell>, height?: int}>
     */
    protected function excelSectionRows(string $sectionTitle, Collection $lines, string $subtotalLabel, float $subtotal): array
    {
        $rows = [];

        $rows[] = $this->excelDataRow([
            $this->textCell($sectionTitle, bold: true, background: 'F9FAFB'),
            $this->textCell('', background: 'F9FAFB'),
        ]);

        if ($lines->isEmpty()) {
            $rows[] = $this->excelDataRow([
                $this->textCell(__('fields.income_statement_no_lines'), muted: true),
                $this->textCell(''),
            ]);

            $rows[] = $this->excelDataRow([
                $this->textCell($subtotalLabel, bold: true),
                $this->amountCell($subtotal, bold: true),
            ]);

            return $rows;
        }

        foreach ($lines as $line) {
            $label = filled($line->code ?? null)
                ? trim(($line->code ?? '') . ' - ' . ($line->name ?? ''))
                : (string) ($line->name ?? '');

            $rows[] = $this->excelDataRow([
                $this->textCell($label),
                $this->amountCell((float) ($line->net ?? 0)),
            ]);
        }

        $rows[] = $this->excelDataRow([
            $this->textCell($subtotalLabel, bold: true),
            $this->amountCell($subtotal, bold: true),
        ]);

        return $rows;
    }

    /**
     * @param  array<int, Cell>  $cells
     * @return array{cells: array<int, Cell>, height?: int}
     */
    protected function excelDataRow(array $cells, ?int $height = null): array
    {
        $row = ['cells' => $cells];

        if ($height !== null) {
            $row['height'] = $height;
        }

        return $row;
    }

    /**
     * @param  array{cells: array<int, Cell>, height?: int}  $rowData
     */
    protected function makeExcelRow(array $rowData): Row
    {
        $row = new Row($rowData['cells']);

        if (isset($rowData['height'])) {
            $row->setHeight($rowData['height']);
        }

        return $row;
    }

    protected function textCell(
        string $value,
        bool $bold = false,
        bool $muted = false,
        bool $highlight = false,
        ?string $background = null,
        ?string $align = null,
        int $size = 11,
    ): Cell {
        $style = (new Style)
            ->setFontSize($size)
            ->setFontBold($bold);

        if ($align !== null) {
            $style->setCellAlignment($align);
        }

        if ($background !== null) {
            $style->setBackgroundColor($background);
        } elseif ($highlight) {
            $style->setBackgroundColor('EFF6FF');
        }

        if ($muted) {
            $style->setFontColor('6B7280');
        }

        $style->setBorder($this->excelBorder());

        return Cell::fromValue($this->sanitizeText($value), $style);
    }

    protected function amountCell(
        float $amount,
        bool $bold = false,
        bool $highlight = false,
        ?string $background = null,
    ): Cell {
        $style = (new Style)
            ->setFontSize(11)
            ->setFontBold($bold)
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setFormat('0.00');

        if ($background !== null) {
            $style->setBackgroundColor($background);
        } elseif ($highlight) {
            $style->setBackgroundColor('EFF6FF');
        }

        $style->setBorder($this->excelBorder());

        return Cell::fromValue(round($amount, 2), $style);
    }

    protected function excelBorder(): Border
    {
        return new Border(
            new BorderPart(Border::BOTTOM, 'D1D5DB', Border::WIDTH_THIN),
        );
    }

    /**
     * @return array<int, array{label: string, amount: float, highlight?: bool}>
     */
    protected function summaryItems(array $statement, string $currency): array
    {
        return [
            [
                'label' => __('fields.income_statement_sales_section') . " ({$currency})",
                'amount' => (float) ($statement['sales_total'] ?? 0),
            ],
            [
                'label' => __('fields.income_statement_purchases_section') . " ({$currency})",
                'amount' => (float) ($statement['purchases_total'] ?? 0),
            ],
            [
                'label' => __('fields.income_statement_expense_section') . " ({$currency})",
                'amount' => (float) ($statement['expenses_total'] ?? 0),
            ],
            [
                'label' => __('fields.income_statement_net_income') . " ({$currency})",
                'amount' => (float) ($statement['net_income'] ?? 0),
                'highlight' => true,
            ],
        ];
    }

    protected function periodLabel(array $statement): string
    {
        $from = filled($statement['from'] ?? null)
            ? Carbon::parse($statement['from'])->translatedFormat('d M Y')
            : '...';
        $to = filled($statement['to'] ?? null)
            ? Carbon::parse($statement['to'])->translatedFormat('d M Y')
            : '...';

        return __('fields.income_statement_period') . ' ' . $from . ' ' . __('fields.income_statement_period_to') . ' ' . $to;
    }

    protected function normalizeStatementForExport(array $statement): array
    {
        foreach (['sales_lines', 'purchases_lines', 'expense_lines'] as $key) {
            $lines = collect($statement[$key] ?? [])->map(function ($line) {
                $line->name = $this->sanitizeText((string) ($line->name ?? ''));

                if (filled($line->code ?? null)) {
                    $line->code = $this->sanitizeText((string) $line->code);
                }

                return $line;
            });

            $statement[$key] = $lines;
        }

        return $statement;
    }

    protected function sanitizeText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            ["\u{2212}", "\u{2013}", "\u{2014}", "\u{2026}", '—', '–', '−', '…'],
            ['-', '-', '-', '...', '-', '-', '-', '...'],
            $value,
        );

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($clean) ? $clean : '';
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = $this->sanitizeText($filename);
        $filename = preg_replace('/[^\w.\-]+/u', '-', $filename) ?? 'income-statement.pdf';

        return trim($filename, '-');
    }
}

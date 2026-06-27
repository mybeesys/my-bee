<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoiceZatcaQrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use NumberFormatter;

class InvoicePdfService
{
    public function stream(Invoice $invoice, bool $inline = true): Response
    {
        $invoice->loadMissing([
            'tenant.media',
            'customer',
            'supplier',
            'representative',
            'items.product',
            'items.productVariant',
            'items.extras',
            'items.taxProfile',
            'services.type',
            'additionalCosts.type',
        ]);

        $settings = Setting::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->pluck('value', 'key')
            ->all();

        $isRtl = app()->getLocale() === 'ar';
        $currencyCode = $settings['main_currency'] ?? 'SAR';
        $currencySymbol = $this->currencySymbol($currencyCode, $isRtl);
        $companyName = $settings['company.name'] ?? $invoice->tenant->name;
        $companyAddress = $settings['company.address'] ?? ($invoice->tenant->store_address ?? '');
        $companyPhone = $settings['company.contact.phone'] ?? ($invoice->tenant->phone ?? '');
        $partyName = $invoice->getInvoicePerson();
        $qrService = InvoiceZatcaQrService::instance();
        $vatSummary = $qrService->vatSummary($invoice);
        $total = $vatSummary['total'];
        $logoPath = $invoice->tenant->getFirstMedia('logos')?->getPath();
        $decimals = (int) ($settings['main_currency_decimals'] ?? 2);
        $trn = trim((string) ($invoice->tenant->trn ?? ''));
        $qrDataUri = $qrService->qrDataUri($invoice, $invoice->tenant, $companyName);

        $labels = $this->labels($invoice->type);
        $documentTitle = $invoice->type === 'sales' ? $labels['title'] : $labels['purchaseTitle'];
        $partyLabel = $invoice->type === 'sales' ? $labels['party'] : $labels['supplier'];

        $items = $invoice->items->map(function ($item) use ($isRtl, $decimals) {
            $name = $item->productVariant?->name ?? $item->product?->name ?? '—';
            $lineTotal = ($item->price * $item->qty) + $item->extras_total - $item->discount;

            return [
                'name' => $isRtl ? arabic_for_pdf($name) : $name,
                'qty' => $this->formatAmount($item->qty, 0, $decimals),
                'price' => $this->formatAmount($item->price, $decimals, $decimals),
                'discount' => $this->formatAmount($item->discount, $decimals, $decimals),
                'total' => $this->formatAmount($lineTotal, $decimals, $decimals),
            ];
        });

        $paymentStatus = $invoice->getPaymentStatus();
        $amountWords = $this->amountInWords($total, $decimals, $isRtl);
        $amountInWordsLine = $isRtl
            ? arabic_for_pdf($labels['amountInWords'] . ': ' . $amountWords)
            : $labels['amountInWords'] . ': ' . $amountWords;

        $fontDir = resource_path('fonts/cairo');

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'isRtl' => $isRtl,
            'documentTitle' => $isRtl ? arabic_for_pdf($documentTitle) : $documentTitle,
            'currencySymbol' => $currencySymbol,
            'companyName' => $isRtl ? arabic_for_pdf($companyName) : $companyName,
            'companyAddress' => $companyAddress ? ($isRtl ? arabic_for_pdf($companyAddress) : $companyAddress) : null,
            'companyPhone' => $companyPhone,
            'partyName' => $isRtl ? arabic_for_pdf($partyName) : $partyName,
            'partyLabel' => $isRtl ? arabic_for_pdf($partyLabel) : $partyLabel,
            'total' => $this->formatAmount($total, $decimals, $decimals),
            'subtotalBeforeVat' => $this->formatAmount($vatSummary['subtotal'], $decimals, $decimals),
            'vatAmount' => $this->formatAmount($vatSummary['vat'], $decimals, $decimals),
            'trn' => $trn !== '' ? ($isRtl ? arabic_for_pdf($trn) : $trn) : null,
            'trnLabel' => $isRtl ? arabic_for_pdf(__('fields.trn')) : __('fields.trn'),
            'qrDataUri' => $qrDataUri,
            'additionalCosts' => $this->formatAmount($invoice->getAdditionalCosts(true), $decimals, $decimals),
            'discount' => $this->formatAmount($invoice->getDiscountInAmount(), $decimals, $decimals),
            'paymentStatus' => $isRtl ? arabic_for_pdf($paymentStatus) : $paymentStatus,
            'amountInWordsLine' => $amountInWordsLine,
            'notes' => $invoice->notes
                ? ($isRtl ? arabic_for_pdf($invoice->notes) : $invoice->notes)
                : null,
            'logoPath' => $logoPath && is_file($logoPath) ? $logoPath : null,
            'items' => $items,
            'labels' => $this->shapeLabels($labels, $isRtl),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('fontDir', $fontDir)
            ->setOption('fontCache', $fontDir)
            ->setOption('defaultFont', 'cairo')
            ->setOption('isRemoteEnabled', false);

        $filename = 'invoice-' . $invoice->no . '.pdf';

        return $inline
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    protected function currencySymbol(string $currencyCode, bool $isRtl): string
    {
        $symbol = Currency::query()
            ->where('iso_code', $currencyCode)
            ->value('symbol_native');

        if ($symbol) {
            return $symbol;
        }

        return $isRtl ? 'ر.س' : $currencyCode;
    }

    protected function formatAmount(float|int|string $amount, int $decimals = 2, ?int $fractionDigits = null): ?string
    {
        $formatter = new NumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $fractionDigits ?? $decimals);

        return $formatter->format((float) str_replace(',', '', (string) $amount));
    }

    protected function amountInWords(float|int $total, int $decimals, bool $isRtl): string
    {
        $locale = $isRtl ? 'ar' : 'en';
        $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $formatter->format($total) ?: (string) $total;
    }

    /**
     * @return array<string, string>
     */
    protected function labels(string $invoiceType): array
    {
        return [
            'title' => __('fields.sales_invoice'),
            'purchaseTitle' => __('fields.purchase_invoice'),
            'invoiceNo' => __('fields.invoice_no'),
            'date' => __('fields.date'),
            'paymentStatus' => __('fields.payment_status'),
            'party' => __('fields.the_client'),
            'supplier' => __('fields.supplier'),
            'product' => __('fields.product'),
            'qty' => __('fields.qty'),
            'price' => __('fields.price'),
            'discount' => __('fields.discount'),
            'total' => __('fields.total'),
            'totalBeforeVat' => __('fields.total_before_vat'),
            'vat' => __('fields.vat'),
            'trn' => __('fields.trn'),
            'additionalCosts' => __('fields.additional_costs'),
            'invoiceTotal' => __('fields.invoice_total'),
            'amountInWords' => __('fields.amount_in_words'),
            'notes' => __('fields.notes'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, string>
     */
    protected function shapeLabels(array $labels, bool $isRtl): array
    {
        if (! $isRtl) {
            return $labels;
        }

        return array_map(
            fn (string $label) => arabic_for_pdf($label),
            $labels
        );
    }
}

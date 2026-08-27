<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePublicViewService
{
    public static function instance(): self
    {
        return new self();
    }

    /**
     * @param  array<string, string|null>  $settings
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice, array $settings): array
    {
        $invoice->loadMissing([
            'tenant.media',
            'customer.state',
            'customer.city',
            'customer.area',
            'supplier.state',
            'supplier.city',
            'supplier.area',
            'items.product',
            'items.productVariant',
            'items.extras',
            'services.type',
            'additionalCosts.type',
        ]);

        $companyName = trim((string) ($settings['company.name'] ?? '')) ?: (string) $invoice->tenant->name;
        $qrService = InvoiceZatcaQrService::instance();
        $vatSummary = $qrService->vatSummary($invoice);
        $documentUrl = filled($invoice->uid)
            ? route('public.invoice.show', ['uid' => $invoice->uid])
            : null;

        $qr = $qrService->buildInvoiceQr(
            $invoice,
            $invoice->tenant,
            $companyName,
            $settings,
            $documentUrl,
        );

        return [
            'party' => $this->partyDetails($invoice),
            'additionalCostsTotal' => (float) $invoice->getAdditionalCosts(true),
            'discountAmount' => $this->invoiceDiscountAmount($invoice),
            'servicesTotal' => (float) $invoice->getServicesCost(true),
            'vatSummary' => $vatSummary,
            'qrPayload' => $qr['qrPayload'],
            'qrDataUri' => $qr['qrDataUri'],
            'qrKind' => $qr['qrKind'],
            'trn' => $qr['trn'],
            'companyName' => $companyName,
        ];
    }

    /**
     * @return array{label: string, name: string, lines: array<int, array{label: string, value: string|null}>}
     */
    public function partyDetails(Invoice $invoice): array
    {
        if ($invoice->for === 'customer' && $invoice->customer) {
            $customer = $invoice->customer;

            return [
                'label' => __('fields.the_client'),
                'name' => $customer->name,
                'lines' => $this->filterLines([
                    ['label' => __('fields.phone'), 'value' => $customer->phone],
                    ['label' => __('fields.email'), 'value' => $customer->email],
                    ['label' => __('fields.trn'), 'value' => $customer->trn],
                    ['label' => __('fields.delivery_address'), 'value' => $customer->delivery_address],
                    ['label' => __('fields.location'), 'value' => $customer->location],
                    ['label' => __('fields.postal_code'), 'value' => $customer->postal_code ?? null],
                ]),
            ];
        }

        if ($invoice->for === 'supplier' && $invoice->supplier) {
            $supplier = $invoice->supplier;

            return [
                'label' => __('fields.supplier'),
                'name' => $supplier->company
                    ? $supplier->name . ', ' . $supplier->company
                    : $supplier->name,
                'lines' => $this->filterLines([
                    ['label' => __('fields.phone'), 'value' => $supplier->phone],
                    ['label' => __('fields.email'), 'value' => $supplier->email],
                    ['label' => __('fields.trn'), 'value' => $supplier->trn ?? null],
                    ['label' => __('fields.delivery_address'), 'value' => $supplier->delivery_address ?? $supplier->address],
                    ['label' => __('fields.location'), 'value' => $supplier->location],
                    ['label' => __('fields.postal_code'), 'value' => $supplier->postal_code ?? null],
                ]),
            ];
        }

        return [
            'label' => $invoice->type === 'sales' ? __('fields.the_client') : __('fields.supplier'),
            'name' => $invoice->getInvoicePerson(),
            'lines' => [],
        ];
    }

    protected function invoiceDiscountAmount(Invoice $invoice): float
    {
        if ($invoice->discount_option === 'overall') {
            return match ($invoice->discount_method) {
                'amount' => (float) ($invoice->discount_amount ?? 0),
                'percent' => (float) $invoice->items->sum(function ($item) {
                    return ($item->price * $item->qty) * ((float) ($invoice->discount_percent ?? 0) / 100);
                }),
                default => 0.0,
            };
        }

        if ($invoice->discount_option === 'per-item') {
            return (float) $invoice->items->sum('discount');
        }

        return 0.0;
    }

    /**
     * @param  array<int, array{label: string, value: string|null}>  $lines
     * @return array<int, array{label: string, value: string|null}>
     */
    protected function filterLines(array $lines): array
    {
        return array_values(array_filter(
            $lines,
            fn (array $line): bool => filled($line['value'] ?? null)
        ));
    }
}

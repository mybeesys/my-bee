<?php


namespace App\Services;


use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\Seller;

class InvoiceService
{

    public $file_path = "invoices/";

    protected ?int $tenantId = null;

    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function filePath($path)
    {
        $this->file_path = Str::endsWith($path, '/') ? $path : $path . '/';
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function download(Invoice $invoice, $tax = 0, $delivery = 0, $customFields = []): \Illuminate\Http\Response
    {
        $invoice->loadMissing(['items.product', 'customer', 'supplier', 'representative', 'purchasePayments']);

        return $this->getInvoice($invoice, $tax, $delivery, $customFields)->download();
    }

    public function downloadPublic(Invoice $invoice, array $customFields = []): \Illuminate\Http\Response
    {
        return (new InvoicePdfService())->stream($invoice);
    }

    public function getInvoice(Invoice $invoice, $tax = 0, $delivery = 0, $customFields = []): \LaravelDaily\Invoices\Invoice
    {
        $invoice->loadMissing([
            'items.product',
            'items.productVariant',
            'items.taxProfile',
            'customer',
            'supplier',
            'representative',
            'purchasePayments',
        ]);

        $customer = null;

        $seller = null;

        if ($invoice->for == "supplier") {

            $seller = new Party(
                [
                    'name' => $invoice->supplier->name,
                ]
            );

            $customer = new Party(
                [
                    'name' => $this->tenantSetting('company.name', ''),
                    'address' => $this->tenantSetting('company.address', ''),
                    'phone' => $this->tenantSetting('company.contact.phone', ''),
                    'custom_fields' => $customFields,
                ]
            );

        }

        if ($invoice->for === 'customer') {
            $customer = new Party(
                [
                    'name' => $invoice->customer?->name ?? __('fields.client'),
                    'custom_fields' => $customFields,
                ]
            );

            $seller = new Party(
                [
                    'name' => $this->tenantSetting('company.name', ''),
                    'address' => $this->tenantSetting('company.address', ''),
                    'phone' => $this->tenantSetting('company.contact.phone', ''),
                ]
            );
        }

        if ($invoice->for === 'representative') {
            $customer = new Party(
                [
                    'name' => $invoice->representative?->name ?? __('fields.representative'),
                    'custom_fields' => $customFields,
                ]
            );

            $seller = new Party(
                [
                    'name' => $this->tenantSetting('company.name', ''),
                    'address' => $this->tenantSetting('company.address', ''),
                    'phone' => $this->tenantSetting('company.contact.phone', ''),
                ]
            );
        }

        $items = collect();

        foreach ($invoice->items as $item) {
            $productName = $item->productVariant?->name ?? $item->product?->name ?? '—';
            $invoiceItem = InvoiceItem::make($productName)
                ->quantity($item->qty)
                ->pricePerUnit($item->price);

            if ($item->unit_id) {
                $invoiceItem->units($item->unit->name);
            }

            if ($item->discount > 0) {
                $invoiceItem->discount($item->discount);
            }

            if ($item->tax_profile_id) {
                $invoiceItem->taxByPercent($item->taxProfile->total_percentages);
            }

            $items->add($invoiceItem);
        }


        $template = app()->getLocale() == "en" ? "default" : "default-ar";
        $logo = $this->tenantSetting('admin.invoices.logo', 'default/invoice-logo.png');
        $logoPath = file_exists(public_path($logo))
            ? public_path($logo)
            : public_path('vendor/invoices/sample-logo.png');
        $currency = $this->tenantSetting('main_currency', 'SAR');
        $currencyDecimals = (int) $this->tenantSetting('main_currency_decimals', 2);

        return \LaravelDaily\Invoices\Invoice::make()
            ->template($template)
            ->serialNumberFormat($invoice->no)
//                ->serialNumberFormat('{SEQUENCE}')
            ->date($invoice->date)
            ->currencySymbol($currency)
            ->currencyCode($currency)
            ->currencyDecimals($currencyDecimals)
            ->setCustomData([
                'swefs' => 900,
            ])
            ->seller($seller)
            ->buyer($customer)
            ->filename($this->file_path . $invoice->type . '-' . $invoice->no)
            ->addItems($items)
            ->currencyThousandsSeparator(',')
            ->shipping($delivery)
            ->logo($logoPath);
    }

    protected function tenantSetting(string $key, $default = ''): ?string
    {
        if ($this->tenantId) {
            return Setting::query()
                ->where('tenant_id', $this->tenantId)
                ->where('key', $key)
                ->value('value') ?? $default;
        }

        return setting($key, $default);
    }

    public function cleanupFiles()
    {
        $files = [];

        array_merge($files, \Storage::disk('public')->files('invoices/orders'));
        array_merge($files, \Storage::disk('public')->files('invoices/sales'));
        array_merge($files, \Storage::disk('public')->files('invoices/purchases'));
        array_merge($files, \Storage::disk('public')->files('invoices/representatives'));

        foreach ($files as $file) {
            try {
                unlink($file);
            } catch (\Exception $exception) {
            }
        }
    }
}

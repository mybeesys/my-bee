<?php


namespace App\Services;


use App\Models\Client;
use App\Models\Invoice;
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
        $invoice->loadMissing(['items.product', 'client', 'payments']);

        $inv = $this->getInvoice($invoice, $tax, $delivery, $customFields);

        return \Response::make(file_get_contents($inv->url()), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function getInvoice(Invoice $invoice, $tax = 0, $delivery = 0, $customFields = []): \LaravelDaily\Invoices\Invoice
    {
        $invoice->loadMissing(['items.product', 'client', 'purchasePayments']);

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
                    'name' => setting('company.name', ''),
                    'address' => setting('company.address', ''),
                    'phone' => setting('company.contact.phone', ''),
                    'custom_fields' => $customFields,
                ]
            );

        }

        if ($invoice->for == "client") {
            $customer = new Party(
                [
                    'name' => $invoice->client->name,
                    'custom_fields' => $customFields,
                ]
            );

            $seller = new Party(
                [
                    'name' => setting('company.name', ''),
                    'address' => setting('company.address', ''),
                    'phone' => setting('company.contact.phone', ''),
                ]
            );
        }

        if ($invoice->for == "representative") {
            $customer = new Party(
                [
                    'name' => $invoice->representative->name,
                    'custom_fields' => $customFields,
                ]
            );

            $seller = new Party(
                [
                    'name' => setting('company.name', ''),
                    'address' => setting('company.address', ''),
                    'phone' => setting('company.contact.phone', ''),
                ]
            );
        }

        $items = collect();

        foreach ($invoice->items as $item) {
            $invoiceItem = InvoiceItem::make($item->product->name)
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
        $logo = setting('admin.invoices.logo', 'default/invoice-logo.png');

        $inv = \LaravelDaily\Invoices\Invoice::make()
            ->template($template)
            ->serialNumberFormat($invoice->no)
//                ->serialNumberFormat('{SEQUENCE}')
            ->date($invoice->date)
            ->currencySymbol(main_currency_iso_code())
            ->currencyCode(main_currency_iso_code())
            ->currencyDecimals(currency_decimals())
            ->setCustomData([
                'swefs' => 900,
            ])
            ->seller($seller)
            ->buyer($customer)
            ->filename($this->file_path . $invoice->type . '-' . $invoice->no)
            ->addItems($items)
            ->currencyThousandsSeparator(',')
            ->shipping($delivery)
            ->logo(public_path('vendor/invoices/sample-logo.png'))
            ->save('public');

        if (file_exists(public_path($logo))) {
            $inv->logo(public_path($logo));
        }

        return $inv;
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

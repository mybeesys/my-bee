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
            $invoice->loadMissing(['items.product', 'client', 'payments']);


            $customer = null;

            $seller = null;

            if($invoice->for == "supplier")
            {

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

            if($invoice->for == "client")
            {
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

            if($invoice->for == "representative")
            {
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

            foreach ($invoice->items as $invItem) {
                $items->add(
                    (new InvoiceItem())
                        ->title($invItem->product->name)
                        ->quantity($invItem->qty)
                        ->pricePerUnit($invItem->price));
            }


            $template = app()->getLocale() == "en" ? "default" : "default-ar";
            $logo = setting('admin.invoices.logo', 'default/invoice-logo.png');

            $inv = \LaravelDaily\Invoices\Invoice::make()
                ->template($template)
                ->sequence($invoice->no)
                ->serialNumberFormat('{SEQUENCE}')
                ->date($invoice->date)
                ->currencySymbol('SDG')
                ->seller($seller)
                ->buyer($customer)
                ->currencyCode('SDG')
                ->filename($this->file_path . $invoice->type . '-' . $invoice->no)
                ->addItems($items)
                ->currencyThousandsSeparator(',')
                ->shipping($delivery)
                ->save('public');

            if(file_exists(public_path($logo)))
            {
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

            foreach ($files as $file)
            {
                try {
                    unlink($file);
                }catch (\Exception $exception){}
            }
        }
    }

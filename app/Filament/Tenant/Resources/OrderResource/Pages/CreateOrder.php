<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;


    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('afterValidate');

        try {

            DB::beginTransaction();

            $data = $this->mutateFormDataBeforeCreate($data);

            $this->callHook('beforeCreate');

            $this->record = $this->handleRecordCreation($data);

            $this->form->model($this->getRecord())->saveRelationships();

            $this->createInvoice($this->record);

            DB::commit();

            $this->callHook('afterCreate');

        } catch (Halt $exception) {
            DB::rollBack();
            return;
        } catch (ValidationException $exception) {
            DB::rollBack();
            return;
        } catch (\Throwable $exception) {
            DB::rollBack();
            fns()->sendDanger('خطأ', 'فشلت العمليلة الرجاء التواصل مع الدعم الفني');
            dd($exception);
            $this->halt();
        }

        $this->getCreatedNotification()?->send();

        if ($another) {
            // Ensure that the form record is anonymized so that relationships aren't loaded.
            $this->form->model($this->getRecord()::class);
            $this->record = null;

            $this->fillForm();

            return;
        }

        $redirectUrl = $this->getRedirectUrl();

        if (FilamentView::hasSpaMode()) {
            $this->redirect($redirectUrl, navigate: is_app_url($redirectUrl));
        } else {
            $this->redirect($redirectUrl);
        }

    }

    protected function createInvoice(Order $order): Invoice
    {
        $order->loadMissing('details');

        $tenant_id = filament()->getTenant()->id;

        $invoice = Invoice::create([
            'no' => generate_invoice_no(),
            'tenant_id' => $tenant_id,
            'type' => 'sales',
            'for' => 'customer',
            'customer_id' => $order->customer_id,
            'user_id' => auth()->id(),
            'date' => now(),
            'notes' => 'sales',
        ]);

        $order->update(['invoice_id' => $invoice->id]);

        foreach ($order->details as $detail) {

            $product_id = null;
            $unit_id = null;
            $product_variant_id = null;

            if($detail->item instanceof Product){
                $product_id = $detail->item->id;
                $unit_id = $detail->item->main_unit_id;
            }

            if($detail->item instanceof ProductUnit){
                $product_id = $detail->item->product_id;
                $unit_id = $detail->item->unit_id;
            }

            if($detail->item instanceof ProductVariant){
                $product_id = $detail->item->product_id;
                $product_variant_id = $detail->item->id;
            }

            $invoice->items()
                ->insert([
                    [
                        'tenant_id' => $tenant_id,
                        'invoice_id' => $invoice->id,
                        'product_id' => $product_id,
                        'unit_id' => $unit_id,
                        'product_variant_id' => $product_variant_id,
                        'order_details_id' => $detail->id,
                        'tax_profile_id' => null,
                        'discount' => 0,
                        'currency_iso_code' => main_currency_iso_code(),
                        'qty' => $detail->qty,
                        'price' => $detail->unit_price,
                        'created_at' => now(),
                    ],
                ]);
        }

        return $invoice;
    }
}

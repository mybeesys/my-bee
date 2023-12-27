<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record->status == Order::$STATUS_COMPLETED) {
            return [];
        }
        return parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true): void
    {
        $this->authorizeAccess();

        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('afterValidate');

        try {

            DB::beginTransaction();

            $data = $this->mutateFormDataBeforeSave($data);

            $this->callHook('beforeSave');

            $this->handleRecordUpdate($this->getRecord(), $data);

            $this->record->load(['details', 'invoice']);

            foreach ($this->record->invoice->items as $item) {
                $hasItem = $this->record->details->where('id', $item->order_details_id)->first();

                if (!$hasItem) {
                    $item->delete();
                }
            }

            DB::commit();

            $this->callHook('afterSave');

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

        $this->getSavedNotification()?->send();

        if ($shouldRedirect && ($redirectUrl = $this->getRedirectUrl())) {
            if (FilamentView::hasSpaMode()) {
                $this->redirect($redirectUrl, navigate: is_app_url($redirectUrl));
            } else {
                $this->redirect($redirectUrl);
            }
        }
    }
}

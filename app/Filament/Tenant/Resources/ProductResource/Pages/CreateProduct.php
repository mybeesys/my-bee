<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Models\ItemPrice;
use App\Services\PricingService;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;


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

            $this->callHook('afterCreate');

            DB::commit();

        }catch (Halt $exception){
            DB::rollBack();
            return;
        } catch (ValidationException $exception){
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

}

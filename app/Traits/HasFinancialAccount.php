<?php

namespace App\Traits;

use App\Models\Acc4;
use App\Models\AccReference;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait HasFinancialAccount
{

    //example
//        public $finance = [
//            'name' => 'full_name',
//            'acc3_code' => 3
//        ];

    public static function bootHasFinancialAccount()
    {

        static::creating(function ($model) {
            if (!$model->financeAttributesReady())
                throw new \Exception("Model finance attributes not implemented");
        });

        static::created(function ($model) {
            $finance = $model->getFinanceAttributes();

            $name = $model->{$finance['name']};

            $acc3_code = $model->finance['acc3_code'];

            try {

                $last = Acc4::where('code', 'LIKE', "%$acc3_code%")->get()->last();

                $new_code = $last == null ? $acc3_code . "000001" : $last->code + 1;

                $acc4 = Acc4::create(
                    [
                        'tenant_id' => $model->tenant_id,
                        'item_id' => $model->id,
                        'item_type' => get_class($model),
                        'acc3_code' => $acc3_code,
                        'code' => $new_code,
                        'name' => $name,
                    ]
                );

            } catch (\Exception $exception) {

                //display exception is tenant is available

                if (filament()->getTenant()) {
//                    fns()->displayException($exception);

//                    dd($exception);
//
                    Notification::make()
                        ->title("Error")
                        ->body("Failed to create a financial account, please contact support.")
                        ->persistent()
                        ->danger()
                        ->send();

                }
            }

        });
    }

    public function getFinanceAttributes(): array
    {
        return is_array($this->finance)
            ? $this->finance
            : [];
    }

    public function financeAttributesReady(): bool
    {
        return isset($this->finance) and array_key_exists('name', $this->finance) and array_key_exists('acc3_code', $this->finance);
    }

    public function acc4(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Acc4::class, 'item');
    }
}

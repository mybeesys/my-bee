<?php

namespace App\Filament\Admin\Resources\ClientResource\Pages;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RoleService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
//        $pr1 = (new PhoneRule(true, false));
//
//        if (!$pr1->passes('phone', $data['phone'])) {
//            fns()->sendWarning($data['phone'] . ", " . $pr1->getErrorMessage());
//            $this->halt();
//        }
//
//        $pr2 = (new PhoneRule(true, false));
//
//        if (!$pr2->passes('phone', $data['mobile'])) {
//            fns()->sendWarning($data['mobile'] . ", " . $pr2->getErrorMessage());
//            $this->halt();
//        }
//
//        $pr3 = (new PhoneRule(true, false));
//
//        if (!$pr3->passes('phone', $data['user_phone'])) {
//            fns()->sendWarning($data['user_phone'] . ", " . $pr3->getErrorMessage());
//            $this->halt();
//        }

        $model = null;
        try {
            DB::beginTransaction();

            $plan = Plan::findOrFail($data['plan_id']);

            $user_data = [
                'first_name' => $data['user_first_name'],
                'second_name' => $data['user_second_name'],
                'third_name' => $data['user_third_name'],
                'fourth_name' => $data['user_fourth_name'],
                'phone' => $data['user_phone'],
                'email' => $data['user_email'],
                'password' => bcrypt($data['user_password']),
                'active' => 1,
            ];

            $user = User::create($user_data);

            RoleService::instance()->assignRole($user, User::ROLE_CLIENT);

            $data['user_id'] = $user->id;

            $model = parent::handleRecordCreation(Arr::except($data, [
                'plan_id',
                'user_first_name',
                'user_second_name',
                'user_third_name',
                'user_fourth_name',
                'user_phone',
                'user_email',
                'user_password',
                'user_password_confirmation'
            ]));

            Subscription::subscribe($plan, $model);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            fns()->displayException($exception);
            $this->halt();
        }
        return $model;
    }
}

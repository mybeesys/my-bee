<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Filament\Tenant\Resources\UserResource;
use App\Models\User;
use App\Services\RoleService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public array $localities = [];
    public $roles;

    public function beforeCreate(): void
    {
        $this->roles = $this->data['roles'];

        if (!\Str::startsWith($this->data['phone'], '+'))
            $this->data['phone'] = '+' . $this->data['phone'];

        $v = Validator::make([$this->data['phone']], ['phone:INTERNATIONAL']);
        if (!$v->passes()) {
            fns()->sendWarning( "Phone number is invalid");
            $this->halt();
        }
        $this->data['phone'] = \Str::replace('+', '', $this->data['phone']);

    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['roles']);
        unset($data['password_confirmation']);

        if (Hash::needsRehash($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }
}

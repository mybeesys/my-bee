<?php

namespace App\Filament\Admin\Resources\AdminResource\Pages;

use App\Filament\Admin\Resources\AdminResource;
use App\Jobs\EmailPassJob;
use App\Jobs\EmailVerfJob;
use App\Models\User;
use App\Rules\InternationalPhoneRule;
use App\Services\SMSService;
use Filament\Actions\ActionGroup;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    public function getHeading(): string|Htmlable
    {
        $full_name = $this->record->full_name;
        return new HtmlString("$full_name");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return implode(', ', $this->record->roles->pluck('name')->toArray());
    }

    protected function getActions(): array
    {
        return [
            ActionGroup::make([
                \Filament\Actions\Action::make('mark_active_inactive')
                    ->label(fn() => $this->record->active ? "Deactivate" : "Activate")
                    ->icon('heroicon-o-pencil')
                    ->action(function () {
                        if (auth()->id() == $this->record->id || $this->record->isSuperAdmin()) {
                            Notification::make()
                                ->title("Operation not allowed")
                                ->danger()
                                ->send();
                            return;
                        }
                        $this->record->update(['active' => !$this->record->active]);
                        $this->record->refresh();
                        fns()->saved();
                        $this->redirect(AdminResource::getUrl('edit', ['record' => $this->record->id]));
                    })
                    ->requiresConfirmation()
                    ->color('danger'),

                \Filament\Actions\Action::make('resend_email_verification')
                    ->color('secondary')
                    ->visible(fn() => !$this->record->hasVerifiedEmail())
                    ->icon('heroicon-s-at-symbol')
                    ->action(function () {
                        dispatch(new EmailVerfJob($this->record));
                        fns()->sendSuccess("The verification email will be sent shortly.");
                    }),

                \Filament\Actions\Action::make('send_password_to_email')
                    ->color('primary')
                    ->icon('heroicon-s-lock-open')
                    ->mountUsing(fn(ComponentContainer $form) => $form->fill([
                        'send_to' => $this->record->email,
                    ]))
                    ->form([
                        Card::make([
                            TextInput::make('send_to')->disabled(),
                            TextInput::make('password')->hint("The user`s password")->required(),
                        ])
                    ])
                    ->action(function (array $data) {
                        dispatch(new EmailPassJob($this->record, $data['password']));
                        fns()->sendSuccess("The password will be sent shortly.");
                    }),

                \Filament\Actions\Action::make('reset_password')
                    ->label('Reset password')
                    ->form([
                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->minLength(8)
                            ->required(),
                    ])
                    ->modalHeading(function () {
                        return $this->record->full_name;
                    })
                    ->modalDescription('Are you sure you\'d like to reset the admin\'s password?')
                    ->modalSubmitActionLabel('Yes, reset account')
                    ->icon('heroicon-o-lock-open')
                    ->action(function (array $data) {
                        try {
                            abort_if($this->record->isSuperAdmin() and !auth()->user()->isSuperAdmin(), 403, 'Only a super admin can perform this action');
                            abort_if(!auth()->user()->isSuperAdmin(), 403, 'Only a super admin can perform this action');
                            $this->record->update(['password' => bcrypt($data['password'])]);
                            Notification::make()
                                ->title("New password saved")
                                ->success()
                                ->send();
                        } catch (\Exception $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->color('warning'),

                \Filament\Actions\Action::make('change_email')
                    ->color('warning')
                    ->icon('heroicon-o-at-symbol')
                    ->requiresConfirmation()
                    ->mountUsing(fn(ComponentContainer $form) => $form->fill([
                        'current_email' => $this->record->email,
                    ]))
                    ->form([
                        TextInput::make('current_email')
                            ->email()
                            ->disabled(1),
                        TextInput::make('new_email')
                            ->email()
                            ->required(),
                    ])
                    ->modalHeading(function () {
                        return $this->record->full_name;
                    })
                    ->modalDescription('Are you sure you\'d like to change the user\'s email?')
                    ->modalSubmitActionLabel('Yes, change email')
                    ->action(function (array $data, \Filament\Actions\Action $action) {
                        try {
                            abort_if($this->record->isSuperAdmin() and !auth()->user()->isSuperAdmin(), 403, 'Only a super admin can perform this action');
                            abort_if(!auth()->user()->isSuperAdmin(), 403, 'Only a super admin can perform this action');


                            if (User::firstWhere('email', $data['new_email'])) {
                                fns()->sendWarning("Email address already exists.");
                                $action->halt();
                            }

                            $this->record->update(['email' => $data['new_email'], 'email_verified_at' => null]);
                            Notification::make()
                                ->title("New email saved")
                                ->success()
                                ->send();
                        } catch (\Exception $exception) {
                            fns()->displayException($exception);
                        }
                    }),

                \Filament\Actions\Action::make('assign_roles')
                    ->icon('heroicon-s-pencil')
                    ->color('primary')
                    ->mountUsing(fn(ComponentContainer $form) => $form->fill([
                        'roles' => Role::all()->whereIn('name', $this->record->roles->pluck('name')->toArray())->pluck('id')->toArray(),
                    ]))
                    ->form([
                        Card::make([
                            TextInput::make('password')
                                ->required()
                                ->password(),
                            Select::make('roles')
                                ->required()
                                ->multiple()
                                ->options(Role::pluck('name', 'id'))
                                ->searchable(),
                        ])
                    ])->action(function (array $data) {
                        if ($data['password'] == "##") {
                            $this->record->syncRoles($data['roles']);
                            fns()->saved();
                        } else {
                            fns()->sendDanger("Invalid credentials");
                        }
                    }),

                \Filament\Actions\Action::make('send_sms')
                    ->requiresConfirmation()
                    ->color('success')
                    ->mountUsing(fn(ComponentContainer $form) => $form->fill([
                        'phone' => $this->record->phone,
                    ]))
                    ->form([
                        Select::make('sms_provider_class')
                            ->label("Sms provider")
                            ->required()
                            ->options(function () {
                                return (new SMSService())->listSmsProviders();
                            }),

                        TextInput::make('reason_for_sms')->required(),

                        TextInput::make('phone')
                            ->required()
                            ->numeric()
                            ->label('sms will be sent to:'),
                        Textarea::make('content')
                            ->required(),
                    ])
                    ->modalHeading(fn() => $this->record->full_name)
                    ->modalDescription('Are you sure you\'d like to sent the sms?')
                    ->modalSubmitActionLabel('Yes, send now')
                    ->modalWidth('lg')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (array $data, \Filament\Actions\Action $action) {
                        $phone = $data['phone'];
                        $content = $data['content'];
                        $sms_provider_class = $data['sms_provider_class'];

                        $passes = (new InternationalPhoneRule(false))->passes('phone', $phone);

                        if (!$passes) {
                            fns()->sendWarning("Phone number is invalid");
                            $action->halt();
                        }

                        try {
                            $sms = (new SMSService())
                                ->getProviderViaServiceClass($sms_provider_class)
                                ->sendTextMessage($phone, $content, null, "other", $data['reason_for_sms']);

                            fns()->sendSuccess("Sms #$sms->id confirmed, check sms history for more details.");
                        } catch (\Exception $exception) {
                            fns()->displayException($exception);
                        }
                    }),
            ]),

        ];
    }


    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles_list'] = implode(', ', $this->record->roles->pluck('name')->toArray());

        return parent::mutateFormDataBeforeFill($data); // TODO: Change the autogenerated stub
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['password_confirmation']);

        if ($data["change_password"] == true && \Hash::needsRehash($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        unset($data['change_password']);

        return $data;
    }

    protected function beforeSave(): void
    {
        if (array_key_exists('password_confirmation', $this->data)) {
            unset($this->data['password_confirmation']);
        }
    }

}

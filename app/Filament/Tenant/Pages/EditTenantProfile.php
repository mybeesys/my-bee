<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Rules\TenantEmailRule;
use App\Rules\TenantPhoneRule;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Tenancy\EditTenantProfile as BaseTenantProfile;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class EditTenantProfile extends BaseTenantProfile
{
    public static function getLabel(): string
    {
        return __('fields.activity_profile');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->data['name'];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('fields.company_or_individual_details'))->schema([
                    Select::make('type')
                        ->label(__('fields.type'))
                        ->reactive()
                        ->required()
                        ->options(
                            [
                                'company' => str(__('fields.company'))->replace('ال', '')->value(),
                                'individual' => __('fields.individual')
                            ]),

                    TextInput::make('name')
                        ->label(__('fields.name'))
                        ->required(),

                    TextInput::make('company_person')
                        ->label(__('fields.company_contact_person'))
                        ->visible(fn(Get $get) => $get('type') === 'company')
                        ->required(),

                    TextInput::make('trn')
                        ->label(__('fields.trn'))
                        ->visible(fn(Get $get) => $get('type') === 'company')
                        ->unique(table: Tenant::class, ignorable: fn(?Model $record): ?Model => $record)
                        ->required(),

                    TextInput::make('phone')
                        ->label(__('fields.phone'))
                        ->tel()
                        ->required()
                        ->rules([new TenantPhoneRule()]),

                    TextInput::make('mobile')
                        ->label(__('fields.mobile'))
                        ->tel()
                        ->required(),

                    TextInput::make('email')
                        ->label(__('fields.email'))
                        ->email()
                        ->required()
                        ->rules([new TenantEmailRule()]),

                    TextInput::make('address')
                        ->label(__('fields.address')),

                ])->columns(3),
            ]);
    }

    public static function canView(Model $tenant): bool
    {
        return filament()->auth()->user()->hasRole(User::ROLE_CLIENT);
    }
}

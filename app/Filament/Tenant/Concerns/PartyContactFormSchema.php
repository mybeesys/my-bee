<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Rules\InternationalPhoneRule;
use Filament\Forms;
use Filament\Forms\Components\Select;

trait PartyContactFormSchema
{
    protected static function partyContactFormFields(string $nameLabel): array
    {
        return [
            hidden_tenant_id_field(),

            Forms\Components\TextInput::make('name')
                ->label($nameLabel)
                ->required()
                ->autofocus(),

            Forms\Components\TextInput::make('phone')
                ->label(__('fields.phone'))
                ->placeholder('966xxxxxxxxx')
                ->tel()
                ->rules([
                    new InternationalPhoneRule(false),
                ])
                ->nullable(),

            Forms\Components\TextInput::make('trn')
                ->label(__('fields.trn')),

            Forms\Components\TextInput::make('email')
                ->label(__('fields.email'))
                ->email(),

            Forms\Components\Hidden::make('country_id')
                ->dehydrated(false)
                ->default(Country::firstWhere('dial_code', '966')->id),

            Forms\Components\TextInput::make('postal_code')
                ->label(__('fields.postal_code'))
                ->maxLength(20),

            Select::make('state_id')
                ->label(__('fields.district'))
                ->live()
                ->searchable()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $set('city_id', null);
                    $set('area_id', null);
                })
                ->options(State::pluck('name', 'id')),

            Select::make('city_id')
                ->visible(function (Forms\Get $get) {
                    $stateId = $get('state_id');

                    return filled($stateId)
                        && City::where('state_id', $stateId)->exists();
                })
                ->live()
                ->label(__('fields.city'))
                ->searchable()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $set('area_id', null);
                })
                ->options(function (Forms\Get $get) {
                    $stateId = $get('state_id');

                    if ($stateId) {
                        return City::where('state_id', $stateId)->pluck('name', 'id');
                    }

                    return [];
                }),

            Select::make('area_id')
                ->visible(function (Forms\Get $get) {
                    $cityId = $get('city_id');

                    return filled($cityId)
                        && Area::where('city_id', $cityId)->exists();
                })
                ->label(__('fields.area'))
                ->searchable()
                ->options(function (Forms\Get $get) {
                    $cityId = $get('city_id');

                    if ($cityId) {
                        return Area::where('city_id', $cityId)->pluck('name', 'id');
                    }

                    return [];
                }),

            Forms\Components\TextInput::make('delivery_address')
                ->label(__('fields.delivery_address'))
                ->helperText(__('fields.delivery_address_hint'))
                ->type('address')
                ->columnSpanFull(),
        ];
    }

    public static function mutatePartyEditFormData(array $data, object $record): array
    {
        $record->loadMissing('city');

        $data['state_id'] = $record->city?->state_id ?? $record->state_id ?? null;

        return $data;
    }
}

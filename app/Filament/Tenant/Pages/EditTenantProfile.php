<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Rules\TenantEmailRule;
use App\Rules\TenantPhoneRule;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Tenancy\EditTenantProfile as BaseTenantProfile;
use Filament\Resources\Components\Tab;
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

                Tabs::make("main")->schema([
                    Tabs\Tab::make(__('fields.company_or_individual_details'))
                        ->schema([
                            Section::make()
                                ->schema([
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
                                        ->required(fn(Get $get) => $get('type') === 'company')
                                        ->unique(table: Tenant::class, ignorable: fn(?Model $record): ?Model => $record),

                                    TextInput::make('phone')
                                        ->label(__('fields.phone'))
                                        ->tel()
                                        ->hint('9665********')
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
                        ]),
//                    Tabs\Tab::make(__('fields.shop'))
//                        ->schema([
//                            Section::make()
//                                ->schema([
//
//                                    Tabs::make('Tabs')
//                                        ->tabs([
//                                            Tabs\Tab::make(__('fields.arabic'))
//                                                ->schema([
//
//                                                    TextInput::make('store_title_ar')
//                                                        ->label(__('fields.store_title')),
//
//                                                    RichEditor::make('store_bio_ar')
//                                                        ->label(__('fields.store_bio'))
//                                                        ->columnSpanFull(),
//
//                                                    TextInput::make('store_address_ar')
//                                                        ->label(__('fields.store_address')),
//
//                                                    TextInput::make('store_working_hours_ar')
//                                                        ->label(__('fields.store_working_hours')),
//
//                                                ])->columns(3),
//                                            Tabs\Tab::make(__('fields.english'))
//                                                ->schema([
//
//                                                    TextInput::make('store_title_en')
//                                                        ->label(__('fields.store_title')),
//
//                                                    RichEditor::make('store_bio_en')
//                                                        ->label(__('fields.store_bio'))
//                                                        ->columnSpanFull(),
//
//                                                    TextInput::make('store_address_en')
//                                                        ->label(__('fields.store_address')),
//
//                                                    TextInput::make('store_working_hours_en')
//                                                        ->label(__('fields.store_working_hours')),
//                                                ]),
//
//                                        ])->columns(3),
//
//                                    Fieldset::make(__('fields.social_media_links'))->schema([
//
//                                        TextInput::make('store_social_media_links.facebook')
//                                            ->label(__('fields.social_label.facebook')),
//
//                                        TextInput::make('store_social_media_links.instagram')
//                                            ->label(__('fields.social_label.instagram')),
//
//                                        TextInput::make('store_social_media_links.twitter')
//                                            ->label(__('fields.social_label.twitter')),
//
//                                        TextInput::make('store_social_media_links.youtube')
//                                            ->label(__('fields.social_label.youtube')),
//
//                                        TextInput::make('store_social_media_links.snapchat')
//                                            ->label(__('fields.social_label.snapchat')),
//
//                                        TextInput::make('store_social_media_links.whatsapp')
//                                            ->label(__('fields.social_label.whatsapp')),
//
//                                    ]),
//
//                                    SpatieMediaLibraryFileUpload::make('cover')
//                                        ->label(__('fields.store_cover'))
//                                        ->image()
//                                        ->openable()
//                                        ->downloadable()
//                                        ->maxSize(4080)
//                                        ->disk('public')
//                                        ->collection('covers')
//                                        ->directory('covers'),
//
//                                    Toggle::make('store_enable_stock_tracking')
//                                        ->helperText(__('fields.store_enable_stock_tracking_hint'))
//                                        ->label(__('fields.store_enable_stock_tracking')),
//
//                                    Toggle::make('store_hide_out_of_stock_products')
//                                        ->label(__('fields.store_hide_out_of_stock_products')),
//
//                                    Fieldset::make()->schema([
//
//                                        Toggle::make('store_enable_orders_tracking')
//                                            ->live()
//                                            ->label(__('fields.store_enable_orders_tracking')),
//
//                                        Radio::make('store_orders_tracking_mode')
//                                            ->live()
//                                            ->visible(fn(Get $get) => $get('store_enable_orders_tracking') == true)
//                                            ->required()
//                                            ->options([
//                                                'manually' => __('fields.store_manually_change_orders_statuses'),
//                                                'automatic' => __('fields.store_automatically_change_orders_statuses'),
//                                            ])
//                                            ->label(__('fields.store_orders_tracking_mode')),
//
//                                        TextInput::make('store_orders_tracking_packaging_time_hours')
//                                            ->visible(fn(Get $get) => $get('store_enable_orders_tracking') == true and $get('store_orders_tracking_mode') == "automatic")
//                                            ->required()
//                                            ->numeric()
//                                            ->maxValue(500)
//                                            ->step(1)
//                                            ->extraInputAttributes(['min' => 1, 'max' => 500])
//                                            ->label(__('fields.store_orders_tracking_packaging_time_hours')),
//
//                                        TextInput::make('store_orders_tracking_delivery_time_hours')
//                                            ->visible(fn(Get $get) => $get('store_enable_orders_tracking') == true and $get('store_orders_tracking_mode') == "automatic")
//                                            ->required()
//                                            ->numeric()
//                                            ->maxValue(500)
//                                            ->step(1)
//                                            ->extraInputAttributes(['min' => 1, 'max' => 500])
//                                            ->label(__('fields.store_orders_tracking_delivery_time_hours')),
//
//                                    ])->columns(4),
//
//                                    RichEditor::make('store_terms_and_conditions')
//                                        ->label(__('fields.store_terms_and_conditions')),
//                                ]),
//                        ]),
                ]),

            ]);
    }

    public static function canView(Model $tenant): bool
    {
        return filament()->auth()->user()->hasRole(User::ROLE_CLIENT);
    }
}

<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\MyActions\Pages\ClearCache;
use App\Filament\Resources\ManageSettingsResource;
use App\Filament\Tenant\Resources\Acc1Resource;
use App\Filament\Tenant\Resources\Acc2Resource;
use App\Filament\Tenant\Resources\Acc3Resource;
use App\Filament\Tenant\Resources\Acc4Resource;
use App\Filament\Tenant\Resources\CategoryResource;
use App\Filament\Tenant\Resources\CouponResource;
use App\Filament\Tenant\Resources\ExpenseCategoryResource;
use App\Filament\Tenant\Resources\Shield\RoleResource;
use App\Filament\Tenant\Resources\SupplierResource;
use App\Filament\Tenant\Resources\TaxProfileResource;
use App\Filament\Tenant\Resources\UserResource;
use App\Filament\Tenant\Resources\WarehouseResource;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use App\Services\CacheService;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Contracts\HasFormActions;
use Filament\Pages\Page;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Pages\Concerns\UsesResourceForm;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Settings extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithFormActions, Translatable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 50;

    protected static bool $shouldRegisterNavigation = false;

//    public static function shouldRegisterNavigation(): bool
//    {
//        return auth()->user()->hasRole(User::ROLE_CLIENT);
//    }

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.settings');
//    }

    public static function getNavigationLabel(): string
    {
        return __('fields.settings');
    }

    public function getTitle(): string | Htmlable
    {
        return __('fields.settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.settings');
    }

    public ?array $data = [];

    public $enable_full_access = false;

//    protected function getFormStatePath(): string
//    {
//        return 'data';
//    }

    public function mount(): void
    {
        abort_if(!auth()->user()->hasRole(User::ROLE_CLIENT), 403);

        $this->form->fill();

        if (config('app.debug'))
            $this->enable_full_access = true;
    }

    protected function getActions(): array
    {
        return [
            Action::make('enable_full_access')
                ->icon('heroicon-o-lock-open')
                ->color('secondary')
                ->requiresConfirmation()
                ->visible(fn() => !$this->enable_full_access)
                ->form([
                    Card::make([
                        TextInput::make('credentials')->required()->password(),
                    ]),
                ])
                ->action(function (array $data) {
                    if ($data['credentials'] === "@872ERVQWER45") {
                        $this->enable_full_access = true;

                        Notification::make()
                            ->title(__('alert.success'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('alert.invalid_credentials'))
                            ->danger()
                            ->send();
                    }
                })
        ];
    }


    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Settings')
                ->tabs($this->getTabs())
                ->statePath('data'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = settings()->pluck('tab')->unique()->toArray();

        $tabs = array_filter($tabs);

        $data = [];

        foreach ($tabs as $tab) {
            $fields = $this->getFields($tab);

//            $textFields = collect($fields)->filter(function ($item) {
//                return $item instanceof TextInput or $item instanceof Select;
//            })->toArray();
//
//            $richEditorFields = collect($fields)->filter(function ($item) {
//                return $item instanceof RichEditor;
//            })->toArray();

            $schema = [
                Card::make($fields)->columns(3),
            ];

//            if (count($richEditorFields) > 0)
//                $schema[] = Section::make(__('fields.other_settings'))->schema($richEditorFields)->collapsible()->collapsed();

            $data[] = Tabs\Tab::make(__("fields." . strtolower(\Str::replace([' ', '-'], '_', $tab))))
                ->visible(function () use ($tab) {
                    $requiresSpecialAccess = \setting("settings.tabs." . strtolower(\Str::replace(' ', '-', $tab)) . ".requires_special_access", false);
                    return $requiresSpecialAccess ? $this->enable_full_access : true;
                })
                ->icon(settings_tab_icon($tab))
                ->disabled(function () use ($tab) {
                    $requiresSpecialAccess = \setting("settings.tabs." . strtolower(\Str::replace(' ', '-', $tab)) . ".requires_special_access", false);
                    return $requiresSpecialAccess ? !$this->enable_full_access : false;
                })
                ->schema($schema);
        }
        return $data;
    }

    public function getFields(string $tab): array
    {
        $fields = [];

        $settings = settings_by_tab($tab)->where('visible_in_user_friendly_settings', true);

        foreach ($settings as $setting) {
            if ($setting->type == "text") {
                $action = null;
                if ($setting->key == 'cpanel.user') {
                    $action = \Filament\Forms\Components\Actions\Action::make('visit')
                        ->icon('heroicon-s-check-badge')
                        ->action(function () {
                            $ok = \App\Services\CpanelService::instance()->check();

                            if ($ok)
                                fns()->sendSuccess("Connection ok");
                            else
                                fns()->sendSuccess("Connection failed");
                        });
                }
                $field = TextInput::make($setting->id)
                    ->required($setting->is_required)
                    ->numeric($setting->is_numeric)
                    ->password($setting->is_password)
                    ->label($setting->display_name)
                    ->placeholder($setting->placeholder)
                    ->helperText($setting->helper_text)
                    ->rules($setting->rules ?? [])
                    ->default($setting->value);

                if ($action)
                    $field->suffixAction($action);

                $fields[] = $field;
            }

            if ($setting->type == "options") {
                $fields[] = Select::make($setting->id)
                    ->label($setting->display_name)
                    ->placeholder($setting->placeholder)
                    ->helperText($setting->helper_text)
                    ->searchable()
                    ->required($setting->is_required)
                    ->rules($setting->rules ?? [])
                    ->options($this->getSettingOptions($setting))
                    ->default($setting->value);
            }

            if ($setting->type == "rich-text") {
                $fields[] = RichEditor::make($setting->id)
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name)
                    ->default($setting->value);
            }

            if ($setting->type == "toggle") {
//                $fields[] = Toggle::make($setting->id)
//                    ->label($setting->display_name)
//                    ->columnSpanFull()
//                    ->default($setting->value);
            }
        }

        return $fields;
    }

    public function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label(__('fields.save'))
                ->action(function () {
                    $this->form->validate();
                    foreach ($this->data as $id => $value) {
                        $setting = Setting::find($id);
                        if ($this->validateSetting($setting, $value) and $value != $setting->value) {
                            $setting->update(['value' => $value]);
                        }
                    }

                    CacheService::instance()->forget('settings');

                    fns()->saved();
                })
        ];
    }

    public function validateSetting(Setting $setting, $newValue): bool
    {
        if (!$setting)
            return false;

        $validator = \Illuminate\Support\Facades\Validator::make(
            [
                "value" => $newValue,
            ],
            [
                "value" => $setting->rules ?? []
            ],
        );

        if (!$validator->passes()) {
//            fns()->sendWarning($validator->errors()->getMessages()[0]);
//            Notification::make()
//                ->title()
//            dd($validator->errors()->getMessages());
            foreach ($validator->errors()->getMessages() as $key => $messages) {
//                dd($key, $message);
                Notification::make()
                    ->title($setting->display_name)
                    ->body($messages[0] ?? "")
                    ->persistent()
                    ->warning()
                    ->send();
            }
        }

        return $validator->passes();
    }

    protected function getSettingOptions(Setting $setting)
    {
        if ($setting->options_cache_key) {
            if (str($setting->options_cache_key)->contains("currency_options")) {
                $tenant_id = str($setting->options_cache_key)->after("@")->value();
                return CacheService::instance()->remember("currency_options", 60 * 60, function () use ($tenant_id) {
                    return Currency::where('tenant_id', $tenant_id)->pluck('name', 'iso_code');
                });
            }
        }
        return $setting->options;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([

                \Filament\Actions\Action::make('categories')
                    ->label(__('fields.products_categories'))
                    ->url(fn() => CategoryResource::getUrl()),

                \Filament\Actions\Action::make('coupons')
                    ->label(__('fields.coupons'))
                    ->url(fn() => CouponResource::getUrl()),

                \Filament\Actions\Action::make('roles')
                    ->label(__('fields.roles'))
                    ->url(fn() => RoleResource::getUrl()),

                \Filament\Actions\Action::make('user_management')
                    ->label(__('fields.user_management'))
                    ->url(fn() => UserResource::getUrl()),

                \Filament\Actions\Action::make('warehouses')
                    ->label(__('fields.warehouses'))
                    ->url(fn() => WarehouseResource::getUrl()),

                \Filament\Actions\Action::make('suppliers')
                    ->label(__('fields.suppliers'))
                    ->url(fn() => SupplierResource::getUrl()),

                \Filament\Actions\Action::make('tax_profiles')
                    ->label(__('fields.tax_profiles'))
                    ->url(fn() => TaxProfileResource::getUrl()),

                \Filament\Actions\Action::make('expense_categories')
                    ->label(__('fields.expense_categories'))
                    ->url(fn() => ExpenseCategoryResource::getUrl()),


//                \Filament\Actions\Action::make('level_1')
//                    ->label(__('fields.level_1'))
//                    ->url(fn() => Acc1Resource::getUrl()),
//
//                \Filament\Actions\Action::make('level_2')
//                    ->label(__('fields.level_2'))
//                    ->url(fn() => Acc2Resource::getUrl()),
//
//                \Filament\Actions\Action::make('level_3')
//                    ->label(__('fields.level_3'))
//                    ->url(fn() => Acc3Resource::getUrl()),
//
                \Filament\Actions\Action::make('level_4')
                    ->label(__('fields.other_party_accounts'))
                    ->url(fn() => Acc4Resource::getUrl()),

            ])
                ->label(__('fields.more_settings'))
//                ->iconSize(IconSize::Large)
                ->icon('heroicon-o-ellipsis-horizontal-circle')
                ->color(Color::Gray)
                ->tooltip(__('fields.more_settings'))
                ->button(),

//            ClearCache::make(),
        ];
    }
}

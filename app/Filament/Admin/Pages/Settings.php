<?php

namespace App\Filament\Admin\Pages;

use App\Filament\MyActions\Pages\ClearCache;
use App\Filament\Resources\ManageSettingsResource;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\CacheService;
use App\Services\SMSService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
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
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\UsesResourceForm;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class Settings extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithFormActions, Translatable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 6;

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.settings');
//    }

    public static function getNavigationLabel(): string
    {
        return __('fields.settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.settings');
    }

    public ?array $data = [];

    public $enable_full_access = false;

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function mount(): void
    {
        $this->refreshSettingsForm();

        if (config('app.debug')) {
            $this->enable_full_access = true;
        }
    }

    protected function getInitialFormState(): array
    {
        $state = [];

        foreach (platform_settings()->where('visible_in_user_friendly_settings', true) as $setting) {
            $value = $setting->value;

            if ($setting->type === 'toggle') {
                $value = in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
            }

            $state[$this->settingFieldName($setting)] = $value;
        }

        return $state;
    }

    public function refreshSettingsForm(): void
    {
        CacheService::instance()->forget('settings');
        forget_platform_settings_cache();

        $state = $this->getInitialFormState();
        $this->data = $state;
        $this->form->fill($state);
    }

    protected function settingFieldName(Setting $setting): string
    {
        return 'setting_'.$setting->id;
    }

    protected function settingFromField(string $field): ?Setting
    {
        if (! str_starts_with($field, 'setting_')) {
            return null;
        }

        $id = (int) str_replace('setting_', '', $field);

        if ($id < 1) {
            return null;
        }

        return Setting::query()->whereNull('tenant_id')->find($id);
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
//        dd($this->getTabs());
        $tabs = $this->getTabs();
//        unset($tabs[4]);
        return [
            Tabs::make('Settings')
                ->persistTabInQueryString('tab')
                ->tabs($tabs),
        ];
    }

    public function getTabs(): array
    {
        $tabs = platform_settings()
            ->pluck('tab')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data = [];

        foreach ($tabs as $tab) {
            $fields = $this->getFields($tab);

            $mainFields = collect($fields)->filter(function ($item) {
                return $item instanceof TextInput
                    || $item instanceof Select
                    || $item instanceof Toggle
                    || $item instanceof Textarea;
            })->values()->all();

            $richEditorFields = collect($fields)->filter(function ($item) {
                return $item instanceof RichEditor;
            })->values()->all();

            $schema = [
                Card::make($mainFields)->columns(3),
            ];

            if (count($richEditorFields) > 0) {
                $schema[] = Section::make(__('fields.other_settings'))->schema($richEditorFields)->collapsible()->collapsed();
            }

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

        $settings = platform_settings()
            ->where('tab', $tab)
            ->where('visible_in_user_friendly_settings', true)
            ->sortBy('sort');

        foreach ($settings as $setting) {
            if ($setting->type == "text") {
                $action = null;
                if ($setting->key == 'hyperpay.access_token') {
                    $action = \Filament\Forms\Components\Actions\Action::make('verify_hyperpay')
                        ->icon('heroicon-s-check-badge')
                        ->action(function () {
                            try {
                                $config = \App\Services\HyperPay\HyperPayConfig::instance();
                                $gateway = \App\Services\HyperPay\HyperPayGateway::make($config);
                                $service = \App\Services\HyperPay\HyperPayCheckoutService::instance();
                                $payload = $service->checkoutPayload(
                                    $config->formatAmount(1),
                                    'MB-VERIFY-'.now()->format('YmdHis'),
                                    [
                                        'given_name' => 'Verify',
                                        'surname' => 'MyBee',
                                        'email' => 'verify@mybeesystem.com',
                                        'street1' => 'Test street',
                                        'city' => 'Riyadh',
                                        'state' => 'Riyadh',
                                        'country' => 'SA',
                                        'postcode' => '11564',
                                    ],
                                );
                                $checkout = $gateway->createCheckout($payload);

                                fns()->sendSuccess(__('fields.hyperpay_credentials_ok'), $checkout['id'] ?? '');
                            } catch (\Throwable $exception) {
                                fns()->sendDanger(__('fields.hyperpay_credentials_failed'), $exception->getMessage());
                            }
                        });
                }
                $field = TextInput::make($this->settingFieldName($setting))
                    ->required($setting->is_required)
                    ->numeric($setting->is_numeric)
                    ->password($setting->is_password)
                    ->revealable($setting->is_password)
                    ->label($setting->display_name)
                    ->placeholder($setting->placeholder)
                    ->helperText($setting->helper_text)
                    ->rules($setting->rules ?? [])
                    ->columnSpan($setting->is_password ? 2 : 1)
                    ->maxLength($setting->is_password ? 2000 : 255)
                    ->default($setting->value);

                if ($action)
                    $field->suffixAction($action);

                $fields[] = $field;
            }

            if ($setting->type == "options") {
                $fields[] = Select::make($this->settingFieldName($setting))
                    ->searchable()
                    ->required($setting->is_required)
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name)
                    ->options($this->getSettingOptions($setting))
                    ->default($setting->value);
            }

            if ($setting->type == "toggle") {
                $fields[] = Toggle::make($this->settingFieldName($setting))
                    ->label($setting->display_name)
                    ->helperText($setting->helper_text)
                    ->inline(false)
                    ->onColor('success')
                    ->default(in_array(strtolower((string) $setting->value), ['1', 'true', 'yes', 'on'], true));
            }

            if ($setting->type == "text-area") {
                $fields[] = Textarea::make($this->settingFieldName($setting))
                    ->rows(8)
                    ->columnSpanFull()
                    ->label($setting->display_name)
                    ->helperText($setting->helper_text)
                    ->placeholder($setting->placeholder)
                    ->rules($setting->rules ?? [])
                    ->default($setting->value);
            }

            if ($setting->type == "rich-text") {
                $fields[] = RichEditor::make($this->settingFieldName($setting))
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name)
                    ->default($setting->value);
            }
        }

        return $fields;
    }

    public function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label(__('fields.save'))
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $field => $value) {
            $setting = $this->settingFromField((string) $field);

            if (! $setting) {
                continue;
            }

            if ($setting->is_password && ($value === null || $value === '')) {
                continue;
            }

            if ($setting->type === 'toggle') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            if ($this->validateSetting($setting, $value)) {
                $setting->update(['value' => $value]);
            }
        }

        CacheService::instance()->forget('settings');
        forget_platform_settings_cache();

        $this->refreshSettingsForm();

        fns()->saved();
    }

    public function fillHyperPayTestDefaults(): void
    {
        \App\Services\HyperPay\HyperPaySettingsInstaller::applyTestDefaults();

        $this->refreshSettingsForm();

        try {
            $checkoutId = $this->verifyHyperPayConnection();
            fns()->sendSuccess(
                __('fields.hyperpay_fill_test_defaults_success'),
                __('fields.hyperpay_credentials_ok').($checkoutId ? ': '.$checkoutId : ''),
            );
        } catch (\Throwable $exception) {
            fns()->sendWarning(
                __('fields.hyperpay_fill_test_defaults_saved'),
                $exception->getMessage(),
            );
        }
    }

    public function verifyHyperPayConnection(): string
    {
        $defaults = \App\Services\HyperPay\HyperPaySettingsInstaller::testDefaults();

        $config = \App\Services\HyperPay\HyperPayConfig::instance([
            'enabled' => true,
            'mode' => 'test',
            'test_base_url' => $defaults['test_base_url'] ?? 'https://eu-test.oppwa.com',
            'access_token' => platform_setting('hyperpay.access_token') ?: ($defaults['access_token'] ?? ''),
            'entity_id' => platform_setting('hyperpay.entity_id') ?: ($defaults['entity_id'] ?? ''),
            'currency' => 'SAR',
            'payment_type' => 'DB',
            'round_test_amounts' => true,
        ]);

        $gateway = \App\Services\HyperPay\HyperPayGateway::make($config);
        $service = new \App\Services\HyperPay\HyperPayCheckoutService($config, $gateway);
        $checkout = $gateway->createCheckout($service->checkoutPayload(
            $config->formatAmount(1),
            'MB-VERIFY-'.now()->format('YmdHis'),
            [
                'given_name' => 'Verify',
                'surname' => 'MyBee',
                'email' => 'verify@mybeesystem.com',
                'street1' => 'Test street',
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'country' => 'SA',
                'postcode' => '11564',
            ],
        ));

        return (string) ($checkout['id'] ?? '');
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
            }
        }
        return $setting->options;
    }


    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('fill_hyperpay_test_defaults')
                ->label(__('fields.hyperpay_fill_test_defaults'))
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('fields.hyperpay_fill_test_defaults'))
                ->modalDescription(__('fields.hyperpay_fill_test_defaults_help'))
                ->action(fn () => $this->fillHyperPayTestDefaults()),
            ClearCache::make(),
        ];
    }
}

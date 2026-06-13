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

//    protected function getFormStatePath(): string
//    {
//        return 'data';
//    }

    public function mount(): void
    {
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
//        dd($this->getTabs());
        $tabs = $this->getTabs();
//        unset($tabs[4]);
        return [
            Tabs::make('Settings')
                ->tabs($tabs)->statePath('data'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = settings()->pluck('tab')->unique()->toArray();

        $tabs = array_filter($tabs);

        $data = [];

        foreach ($tabs as $tab) {
            $fields = $this->getFields($tab);

            $textFields = collect($fields)->filter(function ($item) {
                return $item instanceof TextInput or $item instanceof Select;
            })->toArray();

            $richEditorFields = collect($fields)->filter(function ($item) {
                return $item instanceof RichEditor;
            })->toArray();

            $schema = [
                Card::make($textFields)->columns(3),
            ];

            if (count($richEditorFields) > 0)
                $schema[] = Section::make(__('fields.other_settings'))->schema($richEditorFields)->collapsible()->collapsed();

            $data[] = Tabs\Tab::make(__("fields." . strtolower(\Str::replace([' ', '-'], '_', $tab))))
                ->visible(function () use ($tab) {
                    $requiresSpecialAccess = \setting("settings.tabs." . strtolower(\Str::replace(' ', '-', $tab)) . ".requires_special_access", false);
                    return $requiresSpecialAccess ? $this->enable_full_access : true;
                })
                ->icon(\setting("settings.tabs." . strtolower(\Str::replace(' ', '-', $tab)) . ".icon"))
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
                    ->searchable()
                    ->required($setting->is_required)
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name)
                    ->options($this->getSettingOptions($setting))
                    ->default($setting->value);
            }

            if ($setting->type == "rich-text") {
                $fields[] = RichEditor::make($setting->id)
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
                ->action(function () {
                    $this->form->validate();

                    foreach ($this->data as $id => $value) {
                        $setting = Setting::find($id);
                        if ($this->validateSetting($setting, $value)) {
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
            }
        }
        return $setting->options;
    }


    protected function getHeaderActions(): array
    {
        return [
            ClearCache::make(),
        ];
    }
}

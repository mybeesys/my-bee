<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Currency;
use App\Models\Setting;
use App\Services\CacheService;
use App\Services\TenantService;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Illuminate\Contracts\Support\Htmlable;

class InvConfig extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.inv-config';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'settings/currency-and-invoices';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('fields.currency_and_invoices');
    }

    public function mount(): void
    {
        $tenantId = filament()->getTenant()?->id;

        if ($tenantId && settings_by_group(['system'])->isEmpty()) {
            TenantService::instance()->updateOrCreateSettings($tenantId);
            CacheService::instance()->forget('settings');
        }

        $this->form->fill($this->getSettingsFormState());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make()
                ->schema($this->getFields())
                ->columns(2),
        ];
    }

    protected function getSettingsFormState(): array
    {
        $state = [];

        foreach (settings_by_group(['system']) as $setting) {
            $state[$setting->id] = $setting->value;
        }

        return $state;
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('fields.save'))
                ->action(function () {
                    $this->form->validate();

                    foreach ($this->data as $id => $value) {
                        $setting = Setting::find($id);

                        if ($setting && $value != $setting->value) {
                            $setting->update(['value' => $value]);
                        }
                    }

                    CacheService::instance()->forget('settings');

                    $this->redirect(InvConfig::getUrl());

                    fns()->saved();
                }),
        ];
    }

    public function getFields(): array
    {
        $fields = [];

        $settings = settings_by_group(['system']);

        foreach ($settings as $setting) {
            if ($setting->type == 'text') {
                $fields[] = TextInput::make((string) $setting->id)
                    ->required($setting->is_required)
                    ->numeric($setting->is_numeric)
                    ->password($setting->is_password)
                    ->label($setting->display_name)
                    ->placeholder($setting->placeholder)
                    ->helperText($setting->helper_text)
                    ->rules($setting->rules ?? []);
            }

            if ($setting->type == 'options') {
                $fields[] = Select::make((string) $setting->id)
                    ->searchable()
                    ->required($setting->is_required)
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name)
                    ->options($this->getSettingOptions($setting));
            }

            if ($setting->type == 'rich-text') {
                $fields[] = RichEditor::make((string) $setting->id)
                    ->rules($setting->rules ?? [])
                    ->label($setting->display_name);
            }
        }

        return $fields;
    }

    protected function getSettingOptions(Setting $setting): array
    {
        if ($setting->options_cache_key && str($setting->options_cache_key)->contains('currency_options')) {
            $tenantId = str($setting->options_cache_key)->after('@')->value();

            return CacheService::instance()->remember('currency_options', 60 * 60, function () use ($tenantId) {
                return Currency::where('tenant_id', $tenantId)->pluck('name', 'iso_code')->all();
            });
        }

        return $setting->options ?? [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->icon('heroicon-m-arrow-uturn-left')
                ->size(ActionSize::Large)
                ->url(CustomSettings::getUrl())
                ->iconButton(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return array_merge([
            CustomSettings::getUrl() => __('fields.settings'),
        ], parent::getBreadcrumbs());
    }
}

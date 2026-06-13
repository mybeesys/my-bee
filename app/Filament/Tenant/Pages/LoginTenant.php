<?php

namespace App\Filament\Tenant\Pages;

use App\Models\TenantUser;
use App\Models\User;
use App\Rules\InternationalPhoneRule;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class LoginTenant extends SimplePage
{
    use InteractsWithFormActions;
    use WithRateLimiting;

    /**
     * @var view-string
     */
    protected static string $view = 'filament.tenant.pages.auth.login';

    protected static string $layout = 'filament.tenant.layout.login';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if ($lang = request()->query('lang')) {
            $supported = config('system.supported_languages', ['ar', 'en']);

            if (in_array($lang, $supported, true)) {
                session(['locale' => $lang]);
                app()->setLocale($lang);
            }
        }

        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected bool $hasTopbar = false;

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/login.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();

        //custom

        $identifier = $data['email_or_phone'];
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::with('tenants')->whereEmail($identifier)->first();
        } else {
            $user = User::with('tenants')->wherePhone($identifier)->first();
        }

        if (!$user)
            $this->throwFailureValidationException();

        if ($user->roles->isEmpty())
            $this->throwCustomFailureValidationException("This account does not have any role configuration, please contact your administrator");


        if ($user and Hash::check($data['password'], $user->password)) {
            if (!$user->active) {
                $this->throwCustomFailureValidationException("Account inactive, please contact support.");
            }

//            if(!$user->email_verified_at)
//            {
//                $this->throwCustomFailureValidationException("Please verify your account from the email that we sent you. or contact support.");
//            }

            if (!$user->hasRole(User::ROLE_CLIENT) and $user->tenants->isEmpty()) {
                Notification::make()
                    ->title("Information")
                    ->body("You don`t have access to any activities, please contact your administrator.")
                    ->warning()
                    ->seconds(10)
                    ->send();
                return null;
            }

        }
        if (!Filament::auth()->attempt($this->getCredentialsFromFormData($identifier, $data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (!$user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email_or_phone' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    protected function throwCustomFailureValidationException($message): never
    {
        throw ValidationException::withMessages([
            'data.email_or_phone' => $message,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                        View::make('components.loading'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email_or_phone')
            ->label(__('fields.login_email_or_username'))
            ->placeholder('admin@admin.com')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1, 'class' => 'tenant-login-input']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('fields.password'))
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2, 'class' => 'tenant-login-input']);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label(__('filament-panels::pages/auth/login.form.remember.label'))
            ->extraAttributes(['class' => 'tenant-login-remember']);
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('fields.login_submit'))
            ->color('primary')
            ->submit('authenticate')
            ->extraAttributes(['class' => 'tenant-login-submit-btn']);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData($identifier, array $data): array
    {
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        return [
            $field => $data['email_or_phone'],
            'password' => $data['password'],
        ];
    }
}

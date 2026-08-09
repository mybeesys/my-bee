<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\UniqueClientAttributeRule;
use App\Rules\InternationalPhoneRule;
use App\Rules\NumWords;
use App\Rules\UniqueTenantItemRule;
use App\Services\RoleService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;

class RegisterClient extends SimplePage
{
    use InteractsWithFormActions;
    use WithRateLimiting;

    /**
     * @var view-string
     */
    protected static string $view = 'filament.tenant.pages.auth.register';

    protected static string $layout = 'filament.tenant.layout.login';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected string $userModel;

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

    public function register(): ?RegistrationResponse
    {
        try {

            DB::beginTransaction();

            $this->rateLimit(5);

            $data = $this->form->getState();

            $names = explode(' ', $data['full_name']);

            $data['first_name'] = $names[0];
            $data['second_name'] = $names[1];
            $data['third_name'] = $names[2] ?? null;
            $data['fourth_name'] = $names[3] ?? null;

            $user = $this->getUserModel()::create(Arr::except($data, ['full_name']));

            (new RoleService())->assignRole($user, User::ROLE_CLIENT);

            $client = Client::create([
                'name' => $data['full_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'user_id' => $user->id,
            ]);

            Subscription::subscribe(Plan::query()->where('code', Plan::CODE_FREE)->first() ?? Plan::firstWhere('price', 0), $client);

            app()->bind(
                \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
                \Filament\Listeners\Auth\SendEmailVerificationNotification::class,
            );
//        event(new Registered($user));

            Filament::auth()->login($user);

            session()->regenerate();

            DB::commit();
        } catch (TooManyRequestsException $exception) {
            DB::rollBack();
            report($exception);
            Notification::make()
                ->title(__('filament-panels::pages/auth/register.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/register.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/register.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return null;
        } catch (QueryException $exception) {
            DB::rollBack();
            report($exception);

            fns()->displayException($exception);
//            fns()->sendDanger("تعذر إضافة النشاط", 'حدث خطأ أثناء إنشاء النشاط.');
            return null;
        }

        return app(RegistrationResponse::class);
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

        $terms_of_service_url = env('APP_URL') . "/terms";
        $privacy_policy_url = env('APP_URL') . "/privacy";

        $terms_and_privacy_content_en = "By registering, you agree to Mybee`s <a style='color: #425aff;' href='$terms_of_service_url'>Terms of Service</a> and <a style='color: #425aff;' href='$privacy_policy_url'>Privacy policy</a>";
        $terms_and_privacy_content_ar = "من خلال التسجيل، فإنك توافق على <a style='color: #425aff;' href='$terms_of_service_url'>شروط الخدمة</a>  و <a style='color: #425aff;' href='$privacy_policy_url'>سياسة الخصوصية</a> الخاصة بـ Mybee";

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getFullNameFormComponent(),
                        $this->getPhoneFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),

                        Placeholder::make('')
                            ->dehydrated(false)
                            ->content(new HtmlString(app()->getLocale() == "ar" ? $terms_and_privacy_content_ar : $terms_and_privacy_content_en)),
                        View::make('components.loading'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFullNameFormComponent(): Component
    {
        return TextInput::make('full_name')
            ->label(__('fields.full_name'))
            ->required()
            ->maxLength(255)
            ->rules([
                new NumWords(2, 15, 'messages.full_name_min_words', 'messages.full_name_max_words', translateMessages: true)
            ])
            ->autofocus()
            ->extraInputAttributes(['class' => 'tenant-login-input']);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('fields.phone'))
            ->required()
            ->maxLength(255)
            ->tel()
            ->rules([new InternationalPhoneRule(false), new UniqueClientAttributeRule('phone', 'phone')])
            ->extraInputAttributes(['class' => 'tenant-login-input']);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/register.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->rules([new UniqueClientAttributeRule('email', 'email')])
            ->extraInputAttributes(['class' => 'tenant-login-input']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/register.form.password.label'))
            ->password()
            ->required()
            ->rule(Password::default())
            ->dehydrateStateUsing(fn($state) => Hash::make($state))
            ->same('passwordConfirmation')
            ->revealable()
            ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute'))
            ->extraInputAttributes(['class' => 'tenant-login-input']);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
            ->password()
            ->required()
            ->revealable()
            ->dehydrated(false)
            ->extraInputAttributes(['class' => 'tenant-login-input']);
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label(__('filament-panels::pages/auth/register.actions.login.label'))
            ->url(filament()->getLoginUrl());
    }

    protected function getUserModel(): string
    {
        if (isset($this->userModel)) {
            return $this->userModel;
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        /** @var EloquentUserProvider $provider */
        $provider = $authGuard->getProvider();

        return $this->userModel = $provider->getModel();
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
            $this->getRegisterFormAction(),
        ];
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('fields.register_submit'))
            ->submit('register')
            ->extraAttributes(['class' => 'tenant-login-submit-btn']);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }
}

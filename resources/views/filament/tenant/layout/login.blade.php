@php
    use Filament\Support\Enums\MaxWidth;

    $loginBgUrl = url('images/bg11.jpg');
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div
        class="fi-simple-layout tenant-login-layout flex min-h-screen flex-col items-center justify-center p-4 sm:p-6 lg:p-8"
        style="--tenant-login-bg-image: url('{{ $loginBgUrl }}')"
    >
        <div class="fi-simple-main-ctn flex w-full flex-grow items-center justify-center">
            <main
                @class([
                    'fi-simple-main tenant-login-main w-full max-w-5xl bg-transparent p-0 shadow-none ring-0 dark:bg-transparent',
                ])
            >
                {{ $slot }}
            </main>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $livewire->getRenderHookScopes()) }}
    </div>
</x-filament-panels::layout.base>

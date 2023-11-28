<x-filament::page
        :class="\Illuminate\Support\Arr::toCssClasses([
        'filament-resources-list-records-page',
        'filament-resources-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])"
>

    {{ $this->table }}

</x-filament::page>


{{--<div class="grid grid-cols-12 gap-4 sm:gap-5 lg:gap-6">--}}
{{--    <div--}}
{{--            class="col-span-12 md:col-span-{{ config('filament-page-with-sidebar.sidebar_width.md') }} lg:col-span-{{ config('filament-page-with-sidebar.sidebar_width.lg') }} xl:col-span-{{ config('filament-page-with-sidebar.sidebar_width.xl') }} 2xl:col-span-{{ config('filament-page-with-sidebar.sidebar_width.2xl') }} rounded">--}}
{{--        <div class="">--}}
{{--            <div class="flex items-center rtl:space-x-reverse">--}}
{{--                    <div class="w-full">--}}
{{--                            <h3 class="text-base font-medium text-slate-700 dark:text-navy-100 truncate block">--}}

{{--                            </h3>--}}


{{--                            <p class="text-xs text-gray-500">--}}

{{--                            </p>--}}

{{--                    </div>--}}
{{--            </div>--}}
{{--            <ul class="space-y-2 font-inter font-medium" wire:ignore>--}}

{{--                <li class="filament-sidebar-item-active">--}}
{{--                    <a class="flex items-center justify-center gap-3 px-3 py-2 rounded-lg font-medium transition hover:bg-gray-500/5 focus:bg-gray-500/5 dark:text-gray-300 dark:hover:bg-gray-700" href="{{ admin_panel_url(). '/warehouses/warehouses' }}">--}}
{{--                        {{ __('fields.warehouses') }}--}}
{{--                    </a>--}}
{{--                </li>--}}

{{--            </ul>--}}
{{--        </div>--}}
{{--    </div>--}}

{{--    <div--}}
{{--            class="col-span-12 md:col-span-{{ 12 - config('filament-page-with-sidebar.sidebar_width.md') }} lg:col-span-{{ 12 - config('filament-page-with-sidebar.sidebar_width.lg') }} xl:col-span-{{ 12 - config('filament-page-with-sidebar.sidebar_width.xl') }} 2xl:col-span-{{ 12 - config('filament-page-with-sidebar.sidebar_width.2xl') }}">--}}
{{--        <x-filament::page--}}
{{--                :class="\Illuminate\Support\Arr::toCssClasses([--}}
{{--                'filament-resources-list-records-page',--}}
{{--                'filament-resources-' . str_replace('/', '-', $this->getResource()::getSlug()),--}}
{{--            ])"--}}
{{--        >--}}

{{--            {{ $this->table }}--}}

{{--        </x-filament::page>--}}
{{--    </div>--}}
{{--</div>--}}
<x-filament::widget>
    <x-filament::card>
        {{-- Widget content --}}
        <div class="h-12 flex items-center space-x-4 rtl:space-x-reverse">
            <div class="w-10 h-10 rounded-full bg-gray-200 bg-cover bg-center" style="background-image: url('https://ui-avatars.com/api/?name=client&amp;color=FFFFFF&amp;background=111827')"></div>

            <div>
                <h2 class="text-lg sm:text-xl font-bold tracking-tight">
                    Welcome, client
                </h2>

                <form action="http://127.0.0.1:8000/filament/logout" method="post" class="text-sm">
                    <input type="hidden" name="_token" value="NXxCpNYmO7FxU7bzUf9tnjP17HiuaB6h8l4FglLR">
                    <button type="submit" class="text-gray-600 hover:text-primary-500 focus:outline-none focus:underline">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>

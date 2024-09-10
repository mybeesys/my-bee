
<a
    href="{{ $url }}" wire:navigate
    class="group relative aspect-square rounded-full bg-muted p-1 transition-all hover:scale-105 hover:bg-blue-400 hover:text-blue-50 focus:scale-105 focus:bg-muted focus:text-accent-foreground"
>
    <div class="flex flex-col items-center justify-center h-full">
        <x-filament::icon
            alias="{{ $icon }}"
            icon="{{ $icon }}"
            class="h-8 w-8"
        />
        <span
            class="mt-2 text-sm font-medium group-hover:underline group-focus:underline">{{ $title }}</span>
    </div>
</a>

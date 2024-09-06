<div>
    @if(\Filament\Facades\Filament::getCurrentPanel()->getId() == 'tenant')
        <span class="font-bold text-sm" style="color: #0054ff">
        <a target="_blank" href="{{ env('SHOP_URL') . \Filament\Facades\Filament::getTenant()->slug }}"> {{ __("fields.shop_link") }}</a>
    </span>
    @endif
</div>

<div>
    @if(\Filament\Facades\Filament::getCurrentPanel()->getId() == 'tenant')
        <span class="font-semibold text-sm" style="color: #ff512c">
        <a target="_blank" href="{{ env('SHOP_URL') . \Filament\Facades\Filament::getTenant()->slug }}"> {{ __("fields.shop_link") }}</a>
    </span>
    @endif
</div>

<?php

namespace App\Filament\MyActions\Pages;

use App\Services\CacheService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Pages\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;

class AddToFavourites extends \Filament\Actions\Action
{
    protected string|null $setting_key = null;


    public function settingKey($key): static
    {
        $this->setting_key = $key;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton();
        $this->tooltip(function () {
            if (user_setting($this->setting_key, false))
                return app()->getLocale() == "ar" ? "إزالة من المفضلة" : "Remove from favourites";
            return app()->getLocale() == "ar" ? "إضافة إلى المفضلة" : "Add to favourites";
        });
        $this->size('sm');
        $this->color(Color::Neutral);
        $this->icon(function () {
            return user_setting($this->setting_key, false) ? "heroicon-o-bookmark-slash" : "heroicon-o-star";
        });
        $this->action(function () {
            auth()->user()->settings(
                [
                    $this->setting_key => !user_setting($this->setting_key, false),
                ]
            );
            $this->redirect(url()->previous());
        });
    }
}

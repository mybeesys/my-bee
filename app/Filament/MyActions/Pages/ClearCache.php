<?php

namespace App\Filament\MyActions\Pages;

use App\Services\CacheService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Pages\Actions\Action;

class ClearCache extends \Filament\Actions\Action
{

    public static function getDefaultName(): ?string
    {
        return 'Clear cache';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(app()->isLocal());
        $this->label('Clear cache');
        $this->size('sm');
        $this->isModalSlideOver = true;
        $this->color('danger');
        $this->icon('heroicon-o-trash');
        $this->visible(function () {
            return count(CacheService::keysList()) > 0;
        });
        $this->form(function () {
            $list = CacheService::keysList();

            $data = [];
            foreach ($list as $item) {

                $data[$item['key']] = $item['key'] . ' (cache time: ' . Carbon::createFromTimestamp($item['stored_at'])->diffForHumans() . ')';
            }
            return [
                Section::make(CacheService::instance()->getMasterKey())->schema([
                    CheckboxList::make('items')->columns(2)->required()->options($data),
                ])
            ];
        });
        $this->action(function (array $data) {
            foreach ($data['items'] as $key) {
                $key = str($key)->before("@")->value();
                CacheService::instance()->tenant(\filament()->getTenant()?->id)->forget($key);
            }

            fns()->sendSuccess("Cache cleared");

        });
    }
}

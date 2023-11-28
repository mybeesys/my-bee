<?php

namespace App\Filament\Admin\Resources\ClientResource\Widgets;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestClients extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('fields.latest_clients'))
            ->query(Client::limit(10))
            ->columns(ClientResource::table(new Table($this))->getColumns());
    }
}

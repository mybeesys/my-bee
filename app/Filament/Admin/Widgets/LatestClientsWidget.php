<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestClientsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('fields.latest_clients'))
            ->query(
                Client::query()
                    ->with(['subscription.plan'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(false)
                    ->weight('medium')
                    ->limit(24),

                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label(__('fields.subscription'))
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('fields.email'))
                    ->limit(28)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.join_date'))
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('fields.view'))
                    ->icon('heroicon-m-eye')
                    ->url(fn (Client $record): string => ClientResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false);
    }
}

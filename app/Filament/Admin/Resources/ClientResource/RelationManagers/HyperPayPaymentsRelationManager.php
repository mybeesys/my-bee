<?php

namespace App\Filament\Admin\Resources\ClientResource\RelationManagers;

use App\Models\HyperPayPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class HyperPayPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'hyperPayPayments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.hyperpay_payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('merchant_transaction_id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_plan'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('fields.amount'))
                    ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->currency.' '.format_amount((float) $state)),
                Tables\Columns\TextColumn::make('brand')
                    ->label(__('fields.hyperpay_brand'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('source')
                    ->label(__('fields.hyperpay_source'))
                    ->formatStateUsing(fn (?string $state) => $state === HyperPayPayment::SOURCE_REGISTRATION
                        ? __('fields.hyperpay_source_registration')
                        : __('fields.hyperpay_source_subscription')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->statusLabel())
                    ->color(fn ($state, HyperPayPayment $record) => $record->statusColor()),
                Tables\Columns\TextColumn::make('merchant_transaction_id')
                    ->label(__('fields.hyperpay_merchant_transaction_id'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view_gateway')
                    ->label(__('fields.view'))
                    ->url(fn (HyperPayPayment $record) => \App\Filament\Admin\Resources\HyperPayPaymentResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->bulkActions([]);
    }
}

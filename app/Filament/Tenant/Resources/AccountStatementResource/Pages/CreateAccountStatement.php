<?php

namespace App\Filament\Tenant\Resources\AccountStatementResource\Pages;

use App\Filament\Tenant\Resources\AccountStatementResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountStatement extends CreateRecord
{
    protected static string $resource = AccountStatementResource::class;
}

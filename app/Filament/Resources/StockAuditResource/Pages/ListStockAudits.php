<?php

namespace App\Filament\Resources\StockAuditResource\Pages;

use App\Filament\Resources\StockAuditResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockAudits extends ListRecords
{
    protected static string $resource = StockAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Create Stock Audit'),
        ];
    }
}

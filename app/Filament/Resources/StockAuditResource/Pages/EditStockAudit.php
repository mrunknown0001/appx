<?php

namespace App\Filament\Resources\StockAuditResource\Pages;

use App\Filament\Resources\StockAuditResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockAudit extends EditRecord
{
    protected static string $resource = StockAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

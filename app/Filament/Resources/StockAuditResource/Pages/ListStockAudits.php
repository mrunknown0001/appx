<?php

namespace App\Filament\Resources\StockAuditResource\Pages;

use App\Filament\Resources\StockAuditResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\StockAudit;
use Filament\Notifications\Notification;
use App\Models\Product;

class ListStockAudits extends ListRecords
{
    protected static string $resource = StockAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_stock_audit')
                ->label('Create Stock Audit')
                ->visible(fn () => auth()->user()->role != 'manager')
                ->requiresConfirmation()
                ->modalHeading('Create Stock Audit')
                ->modalDescription('Are you sure you want to create a new stock audit?')
                ->modalSubmitActionLabel('Yes')
                ->modalCancelActionLabel('Cancel')
                ->modalCloseButton()
                ->action(function () {
                    if (!auth()->check()) {
                        throw new \Exception('You must be logged in to create a stock audit.');
                    }

                    // Check if StockAudit has pending stock audits
                    if (StockAudit::where('requested_by', auth()->id())->where('status', 'pending')->exists()) {
                        Notification::make()
                            ->title('Pending Stock Audit')
                            ->body('You already have a pending stock audit. Please complete or cancel it before creating a new one.')
                            ->danger()
                            ->send();
                        return;
                    }
                    $stockAudit = StockAudit::create([
                        'requested_by' => auth()->id(),
                        'date_requested' => now(),
                        'status' => 'pending',
                    ]);
                    Notification::make()
                            ->title('Stock Audit Created')
                            ->body('Your stock audit has been successfully created.')
                            ->success()
                            ->send();

                    // Make StockAuditEntry for each product in the stock audit
                    Product::all()->each(function ($product) use ($stockAudit) {
                        $stockAudit->entries()->create([
                            'product_id' => $product->id,
                            'expected_quantity' => $product->getCurrentStock(),
                            'actual_quantity' => $product->getCurrentStock(),
                            'matched' => false,
                            'is_audited' => false,
                            'remarks' => ''
                        ]);
                    });
                })
                ->successNotificationTitle('Stock audit created successfully')
        ];
    }
}

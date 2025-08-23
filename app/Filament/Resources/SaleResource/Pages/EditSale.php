<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected $listeners = [
        'refresh-parent-form' => 'refreshForm',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    // Check if sale has items before allowing deletion
                    if ($this->record->saleItems()->count() > 0) {
                        // Return stock to inventory before deleting
                        foreach ($this->record->saleItems as $saleItem) {
                            $batch = $saleItem->inventoryBatch;
                            if ($batch) {
                                $batch->update([
                                    'current_quantity' => $batch->current_quantity + $saleItem->quantity,
                                    'status' => 'active'
                                ]);
                            }
                        }
                    }
                }),
            Actions\Action::make('recalculate')
                ->label('Recalculate Totals')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->action(function () {
                    $this->recalculateTotals();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sale Updated')
            ->body('The sale has been updated successfully.');
    }

    public function refreshForm(): void
    {
        // Refresh the record from database
        $this->record = $this->record->fresh();
        
        // Reset the form with fresh data
        $this->fillForm();
        
        // Refresh the page state
        $this->dispatch('$refresh');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure tax and discount have values
        $data['tax_amount'] = $data['tax_amount'] ?? 0;
        $data['discount_amount'] = $data['discount_amount'] ?? 0;
        
        // Recalculate total if tax or discount changed
        $subtotal = $data['subtotal'] ?? 0;
        $data['total_amount'] = max(0, $subtotal + $data['tax_amount'] - $data['discount_amount']);

        return $data;
    }

    public function recalculateTotals(): void
    {
        $sale = $this->record;
        $sale->load('saleItems');
        
        $subtotal = $sale->saleItems->sum('total_price');
        $taxAmount = $sale->tax_amount ?? 0;
        $discountAmount = $sale->discount_amount ?? 0;
        $total = $subtotal + $taxAmount - $discountAmount;
        
        $sale->update([
            'subtotal' => $subtotal,
            'total_amount' => max(0, $total),
        ]);
        
        $this->refreshForm();
        
        Notification::make()
            ->success()
            ->title('Totals Recalculated')
            ->body("Subtotal: ₱" . number_format($subtotal, 2) . " | Total: ₱" . number_format($total, 2))
            ->send();
    }
}
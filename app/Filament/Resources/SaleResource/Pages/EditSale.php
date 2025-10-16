<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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

    protected function afterSave(): void
    {
        try {
            $sale = $this->record->fresh(['saleItems']);

            Log::info('EditSale::afterSave - invoking persistRecalculatedTotals', [
                'sale_id' => $sale?->id,
                'items_count' => $sale?->saleItems?->count(),
            ]);

            if ($sale) {
                SaleResource::persistRecalculatedTotals($sale);
                $this->refreshForm();
            }
        } catch (\Throwable $exception) {
            Log::error('EditSale::afterSave - failed to persist recalculated totals', [
                'sale_id' => $this->record?->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
        Log::info('EditSale::mutateFormDataBeforeSave - incoming payload', [
            'sale_id' => $this->record?->id,
            'has_sale_items' => array_key_exists('saleItems', $data),
            'sale_items_count' => isset($data['saleItems']) ? count($data['saleItems']) : 0,
            'subtotal' => $data['subtotal'] ?? null,
            'tax_amount' => $data['tax_amount'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
        ]);

        $saleItems = collect($data['saleItems'] ?? []);

        $subtotal = $saleItems->sum(function (array $item): float {
            $totalPrice = $item['total_price'] ?? null;

            if (!is_null($totalPrice) && $totalPrice !== '') {
                return (float) str_replace(',', '', $totalPrice);
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) str_replace(',', '', $item['unit_price'] ?? 0);
            $discountAmount = (float) str_replace(',', '', $item['discount_amount'] ?? 0);

            return max(0, ($quantity * $unitPrice) - $discountAmount);
        });

        $taxAmount = (float) str_replace(',', '', $data['tax_amount'] ?? 0);
        $discountAmount = (float) str_replace(',', '', $data['discount_amount'] ?? 0);

        if ($saleItems->isNotEmpty()) {
            $data['saleItems'] = $saleItems
                ->map(function (array $item): array {
                    $item['total_price'] = round((float) str_replace(',', '', $item['total_price'] ?? 0), 2);
                    $item['unit_price'] = round((float) str_replace(',', '', $item['unit_price'] ?? 0), 2);
                    $item['discount_amount'] = round((float) str_replace(',', '', $item['discount_amount'] ?? 0), 2);

                    return $item;
                })
                ->toArray();
        }

        $data['subtotal'] = round($subtotal ?: (float) str_replace(',', '', $data['subtotal'] ?? 0), 2);
        $data['tax_amount'] = round($taxAmount, 2);
        $data['discount_amount'] = round($discountAmount, 2);
        $data['total_amount'] = max(0, round($data['subtotal'] + $taxAmount - $discountAmount, 2));

        Log::info('EditSale::mutateFormDataBeforeSave - normalized payload', [
            'sale_id' => $this->record?->id,
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'discount_amount' => $data['discount_amount'],
            'total_amount' => $data['total_amount'],
        ]);

        return $data;
    }

    public function recalculateTotals(): void
    {
        try {
            $sale = $this->record->fresh(['saleItems']);

            Log::info('EditSale::recalculateTotals - manual trigger', [
                'sale_id' => $sale?->id,
                'items_count' => $sale?->saleItems?->count(),
            ]);

            if (!$sale) {
                throw new \RuntimeException('Sale record missing during recalculation');
            }

            $normalizedTotals = SaleResource::persistRecalculatedTotals($sale);

            $this->refreshForm();

            Notification::make()
                ->success()
                ->title('Totals Recalculated')
                ->body("Subtotal: ₱" . number_format($normalizedTotals['subtotal'], 2) . " | Total: ₱" . number_format($normalizedTotals['total_amount'], 2))
                ->send();
        } catch (\Throwable $exception) {
            Log::error('EditSale::recalculateTotals - failed to persist recalculated totals', [
                'sale_id' => $this->record?->id,
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Recalculation Failed')
                ->body('Totals could not be recalculated. Please check the logs.')
                ->send();
        }
    }
}
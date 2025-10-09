<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sale Created')
            ->body('The sale has been created successfully. You can now add items to this sale.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('CreateSale::mutateFormDataBeforeCreate - incoming payload', [
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

        $data['saleItems'] = $saleItems
            ->map(function (array $item): array {
                $item['total_price'] = round((float) str_replace(',', '', $item['total_price'] ?? 0), 2);
                $item['unit_price'] = round((float) str_replace(',', '', $item['unit_price'] ?? 0), 2);
                $item['discount_amount'] = round((float) str_replace(',', '', $item['discount_amount'] ?? 0), 2);

                return $item;
            })
            ->toArray();

        $data['subtotal'] = round($subtotal, 2);
        $data['tax_amount'] = round($taxAmount, 2);
        $data['discount_amount'] = round($discountAmount, 2);
        $data['total_amount'] = max(0, round($subtotal + $taxAmount - $discountAmount, 2));

        if (empty($data['sale_number'])) {
            $data['sale_number'] = 'SALE-' . strtoupper(uniqid());
        }

        Log::info('CreateSale::mutateFormDataBeforeCreate - normalized payload', [
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'discount_amount' => $data['discount_amount'],
            'total_amount' => $data['total_amount'],
        ]);

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $sale = $this->record->fresh(['saleItems']);

            Log::info('CreateSale::afterCreate - invoking persistRecalculatedTotals', [
                'sale_id' => $sale?->id,
                'items_count' => $sale?->saleItems?->count(),
            ]);

            if ($sale) {
                SaleResource::persistRecalculatedTotals($sale);
            }
        } catch (\Throwable $exception) {
            Log::error('CreateSale::afterCreate - failed to persist recalculated totals', [
                'sale_id' => $this->record?->id,
                'error' => $exception->getMessage(),
            ]);
        }

        // Additional notification about adding items
        Notification::make()
            ->info()
            ->title('Next Step')
            ->body('Add sale items using the "Sale Items" tab to complete this sale.')
            ->persistent()
            ->send();
    }
}
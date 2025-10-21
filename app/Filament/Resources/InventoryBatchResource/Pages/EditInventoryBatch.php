<?php

namespace App\Filament\Resources\InventoryBatchResource\Pages;

use App\Filament\Resources\InventoryBatchResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth as AuthFacade;

class EditInventoryBatch extends EditRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            
            Actions\Action::make('adjust_quantity')
                ->label('Quick Adjust')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\TextInput::make('adjustment')
                        ->label('Quantity Adjustment')
                        ->numeric()
                        ->required()
                        ->helperText('Use negative numbers to reduce quantity, positive to increase')
                        ->placeholder('e.g., -10 or +5'),
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason for Adjustment')
                        ->placeholder('e.g., Damaged goods, Found extra stock, etc.')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $record = $this->record;
                    $newQuantity = $record->current_quantity + $data['adjustment'];
                    
                    if ($newQuantity < 0) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid Adjustment')
                            ->body('Adjustment would result in negative quantity.')
                            ->send();
                        return;
                    }

                    $record->update([
                        'current_quantity' => $newQuantity,
                        'status' => $newQuantity == 0 ? 'depleted' : $record->status,
                    ]);

                    $this->dispatchInventoryNotification(
                        Notification::make()
                            ->success()
                            ->title('Quantity Adjusted')
                            ->body("Batch quantity adjusted by {$data['adjustment']}. Reason: {$data['reason']}")
                    );
                }),

            Actions\DeleteAction::make()
                ->before(function () {
                    // Check if this batch has been used in sales
                    if ($this->record->saleItems()->exists()) {
                        throw new \Exception('Cannot delete inventory batch that has been used in sales.');
                    }
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
            ->title('Inventory Batch Updated')
            ->body('The inventory batch has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalRecord = $this->record->getOriginal();
        
        // Auto-update status based on expiry date and quantity
        if ($data['expiry_date'] < now()) {
            $data['status'] = 'expired';
        } elseif ($data['current_quantity'] <= 0) {
            $data['status'] = 'depleted';
        } elseif ($data['status'] === 'expired' && $data['expiry_date'] >= now()) {
            // If expiry date is moved to future and status was expired, reactivate
            $data['status'] = 'active';
        } elseif ($data['status'] === 'depleted' && $data['current_quantity'] > 0) {
            // If quantity is increased and status was depleted, reactivate
            $data['status'] = 'active';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $batch = $this->record;
        $original = $this->record->getOriginal();
        
        // Check for significant changes and notify
        $notifications = [];
        
        // Check if quantity changed significantly
        if ($original['current_quantity'] != $batch->current_quantity) {
            $difference = $batch->current_quantity - $original['current_quantity'];
            $notifications[] = "Quantity changed by " . ($difference > 0 ? "+{$difference}" : $difference);
        }
        
        // Check if status changed
        if ($original['status'] != $batch->status) {
            $notifications[] = "Status changed from '{$original['status']}' to '{$batch->status}'";
        }
        
        // Check for low stock after update
        $product = $batch->product;
        $productName = $product?->name ?? 'This product';
        $expiryDate = $batch->expiry_date;
        $daysUntilExpiry = null;

        if ($expiryDate) {
            $daysUntilExpiry = now()->diffInDays($expiryDate, false);
        }

        if ($product && $batch->current_quantity <= $product->min_stock_level && $batch->status === 'active') {
            $this->dispatchInventoryNotification(
                Notification::make()
                    ->warning()
                    ->title('Low Stock Alert')
                    ->body("{$productName} has {$batch->current_quantity} in stock (minimum {$product->min_stock_level}).")
            );
        }

        if ($expiryDate && $daysUntilExpiry !== null && abs($daysUntilExpiry) <= 30 && in_array($batch->status, ['active', 'expired'], true)) {
            $absDays = abs($daysUntilExpiry);

            $expiryMessage = match (true) {
                $daysUntilExpiry < 0 => "Batch {$batch->batch_number} for {$productName} expired {$absDays} day" . ($absDays === 1 ? '' : 's') . ' ago.',
                $daysUntilExpiry === 0 => "Batch {$batch->batch_number} for {$productName} expires today.",
                default => "Batch {$batch->batch_number} for {$productName} expires in {$daysUntilExpiry} day" . ($daysUntilExpiry === 1 ? '' : 's') . '.',
            };

            $this->dispatchInventoryNotification(
                Notification::make()
                    ->warning()
                    ->title($daysUntilExpiry < 0 ? 'Expired Batch' : 'Expiry Warning')
                    ->body($expiryMessage)
            );
        }

        if (!empty($notifications)) {
            $this->dispatchInventoryNotification(
                Notification::make()
                    ->info()
                    ->title('Batch Updated')
                    ->body(implode(', ', $notifications))
            );
        }
    }

    private function dispatchInventoryNotification(Notification $notification): void
    {
        if ($user = AuthFacade::user()) {
            $databaseNotification = clone $notification;
            $databaseNotification->sendToDatabase($user);
        }

        $notification->send();
    }
}
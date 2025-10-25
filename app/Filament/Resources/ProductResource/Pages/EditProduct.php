<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\Product;
use Filament\Support\Exceptions\Halt;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Product $record) {
                    // 🚫 Check stock entries
                    if ($record->stockEntries()->exists()) {
                        
                        Notification::make()
                        ->title("Cannot delete product: {$record->name}")
                        ->body('This product has stock entries and cannot be deleted.')
                        ->warning()
                        ->send();
                        
                        $action->cancel();
                        throw new Halt();
                    }

                    // 🚫 Check sales history
                    if ($record->saleItems()->exists()) {
                        
                        Notification::make()
                        ->title("Cannot delete product: {$record->name}")
                        ->body('This product has sales history and cannot be deleted.')
                        ->warning()
                        ->send();
                        
                        $action->cancel(); 
                        throw new Halt();
                    }
                }),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
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
            ->title('Product updated')
            ->body('The product has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['sku'] = strtoupper($data['sku']);
        return $data;
    }
}

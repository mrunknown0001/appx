<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->products()->count() > 0) {
                        throw new \Exception('Cannot delete category that has products assigned to it.');
                    }
                    if ($this->record->children()->count() > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Cannot delete category')
                            ->body('The category has subcategories and cannot be deleted.');
                        throw new \Exception('Cannot delete category that has subcategories.');
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
            ->title('Category updated')
            ->body('The product category has been updated successfully.');
    }
}

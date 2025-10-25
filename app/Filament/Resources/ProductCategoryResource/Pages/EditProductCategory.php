<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->products()->count() > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Cannot delete category')
                            ->body('Cannot delete category that has products assigned to it. Go to Product Categories for more action.')
                            ->send();
                            $action->cancel();
                            throw new Halt();
                    }
                    if ($this->record->children()->count() > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Cannot delete category')
                            ->body('The category has subcategories and cannot be deleted. Go to Product Categories for more action.')
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
            ->title('Category updated')
            ->body('The product category has been updated successfully.');
    }
}

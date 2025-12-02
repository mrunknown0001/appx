<?php

namespace App\Filament\Resources\StockAuditResource\Pages;

use App\Filament\Resources\StockAuditResource;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\StockAuditEntry;
use App\Models\StockAudit;

class AuditProducts extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    // will hold the StockAudit model
    public ?StockAudit $record = null;

    protected static string $resource = StockAuditResource::class;

    protected static string $view = 'filament.resources.stock-audit-resource.pages.audit-products';

    /**
     * Mount will be called by Livewire. Filament typically passes either the model or the id.
     * Accept both and resolve the StockAudit model here.
     *
     * @param  mixed  $record  StockAudit model or id (or null)
     * @return void
     */
    public function mount($record = null): void
    {
        if ($record instanceof StockAudit) {
            $this->record = $record;
        } elseif (is_numeric($record) || is_string($record)) {
            $this->record = StockAudit::findOrFail($record);
        } else {
            // fallback: try route parameter 'record'
            $routeRecord = request()->route('record');
            if ($routeRecord instanceof StockAudit) {
                $this->record = $routeRecord;
            } elseif ($routeRecord) {
                $this->record = StockAudit::findOrFail($routeRecord);
            } else {
                // If nothing is available, throw a helpful exception
                abort(404, 'StockAudit record not found for AuditProducts page.');
            }
        }
    }

    public function table(Table $table): Table
    {
        // ensure record exists
        // if (! $this->record) {
        //     abort(404, 'StockAudit record not set.');
        // }

        return $table
            ->query(
                StockAuditEntry::query()
                    ->where('stock_audit_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('expected_quantity')
                    ->label('Expected Quantity'),
                Tables\Columns\TextColumn::make('actual_quantity')
                    ->label('Actual Quantity'),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Difference')
                    ->state(fn ($record) => $record->expected_quantity - $record->actual_quantity),
                Tables\Columns\TextColumn::make('is_audited')
                    ->label('Audited')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                Tables\Columns\TextColumn::make('matched')
                    ->label('Matched')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
            ])
            ->actions([
                // MATCHED action — update the existing StockAuditEntry as matched
                Tables\Actions\Action::make('matched')
                    ->label('Matched')
                    ->visible(fn ($record) => ! $record->is_audited)
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Quantity Matched?')
                    ->modalDescription('Confirm the quantity of the product?')
                    ->modalSubmitActionLabel('Yes')
                    ->modalCancelActionLabel('Cancel')
                    ->modalCloseButton()
                    ->action(function (StockAuditEntry $entry) {
                        // $entry is a StockAuditEntry instance
                        $entry->update([
                            'audited_by' => auth()->id(),
                            'matched' => 1,
                            'is_audited' => true,
                            'actual_quantity' => $entry->product->getCurrentStock(),
                        ]);

                        Notification::make()
                            ->title('Quantity Matched')
                            ->body('The quantity of the product has been successfully matched.')
                            ->success()
                            ->send();

                        // check if this is the last entry of the stock audit
                        if ($entry->stockAudit->entries()->where('is_audited', false)->count() === 0) {
                            $entry->stockAudit->update([
                                'status' => 'completed',
                                'completed_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Stock Audit Completed')
                                ->body('The stock audit has been successfully completed.')
                                ->success()
                                ->send();
                        }
                    }),

                // UNMATCHED action — shows form, then updates existing entry as unmatched
                Tables\Actions\Action::make('unmatched')
                    ->label('Unmatched')
                    ->visible(fn ($record) => ! $record->is_audited)
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Quantity Unmatched?')
                    ->modalDescription('Confirm the quantity of the product is incorrect?')
                    ->modalSubmitActionLabel('Yes')
                    ->modalCancelActionLabel('Cancel')
                    ->modalCloseButton()
                    ->action(function (array $data,StockAuditEntry $entry) {
                        // update the existing entry instead of creating a new one
                        $entry->update([
                            'audited_by' => auth()->id(),
                            'actual_quantity' => $data['quantity'],
                            'matched' => false,
                            'is_audited' => true,
                            'remarks' => $data['reason'],
                        ]);

                        Notification::make()
                            ->title('Quantity Unmatched')
                            ->body('The quantity of the product has been marked as unmatched.')
                            ->danger()
                            ->send();
                        
                        // check if this is the last entry of the stock audit
                        if ($entry->stockAudit->entries()->where('is_audited', false)->count() === 0) {
                            $entry->stockAudit->update([
                                'status' => 'completed',
                                'completed_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Stock Audit Completed')
                                ->body('The stock audit has been successfully completed.')
                                ->success()
                                ->send();
                        }
                    }),
            ]);
    }
}

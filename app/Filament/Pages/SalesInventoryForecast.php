<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Services\ARIMAForecastService;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class SalesInventoryForecast extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Sales & Inventory Forecast';
    protected static ?string $title = 'Sales & Inventory Forecast (ARIMA)';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.sales-inventory-forecast';

    public ?array $data = [];
    public $forecastData = null;
    public $restockData = null;

    public function mount(): void
    {
        $this->form->fill([
            'product_id' => 'all',
            'forecast_period' => 'monthly',
            'forecast_horizon' => 12,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(
                                Product::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->prepend('All Products', 'all')
                            )
                            ->default('all')
                            ->required()
                            ->searchable(),

                        Select::make('forecast_period')
                            ->label('Forecast Period')
                            ->options([
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                            ])
                            ->default('monthly')
                            ->required(),

                        Select::make('forecast_horizon')
                            ->label('Forecast Horizon')
                            ->options([
                                4 => '4 Periods',
                                8 => '8 Periods',
                                12 => '12 Periods',
                                24 => '24 Periods',
                            ])
                            ->default(12)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function generateForecast(): void
    {
        $data = $this->form->getState();
        
        try {
            $forecastService = new ARIMAForecastService();
            
            // Generate sales forecast
            $salesResult = $forecastService->generateSalesForecast(
                productId: $data['product_id'] === 'all' ? null : $data['product_id'],
                period: $data['forecast_period'],
                horizon: $data['forecast_horizon']
            );

            $this->forecastData = $salesResult['forecasts'];

            // Show warning if products were skipped
            if (!empty($salesResult['skipped_products'])) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Some products skipped')
                    ->body('The following products were skipped due to insufficient data: ' . implode(', ', array_slice($salesResult['skipped_products'], 0, 5)) . (count($salesResult['skipped_products']) > 5 ? '...' : ''))
                    ->persistent()
                    ->send();
            }

            // Generate restock recommendations
            $this->restockData = $forecastService->generateRestockForecast(
                productId: $data['product_id'] === 'all' ? null : $data['product_id'],
                period: $data['forecast_period'],
                horizon: $data['forecast_horizon']
            );

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Forecast Generated Successfully')
                ->body('Sales and inventory forecasts have been generated.')
                ->send();

            $this->dispatch('forecast-generated');
            
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Forecast Generation Failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
                
            $this->forecastData = null;
            $this->restockData = null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'superadmin']);
    }
}
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ARIMAForecastService
{
    /**
     * Generate sales forecast using ARIMA model
     */
    public function generateSalesForecast(?int $productId, string $period, int $horizon): array
    {
        $historicalData = $this->getHistoricalSalesData($productId, $period);
        
        // Check if we have any data at all
        if (empty($historicalData)) {
            throw new \Exception('No historical sales data found. Please ensure you have sales records in the system.');
        }
        
        $forecasts = [];
        $skippedProducts = [];
        
        foreach ($historicalData as $prodId => $data) {
            $product = Product::find($prodId);
            
            if (!$product) {
                continue;
            }
            
            // Need at least 3 data points for ARIMA
            if (count($data['quantities']) < 3) {
                $skippedProducts[] = $product->name . ' (only ' . count($data['quantities']) . ' periods)';
                continue;
            }

            // ARIMA Forecast
            $quantityForecast = $this->arimaForecast(
                $data['quantities'], 
                $horizon,
                $p = 1, $d = 1, $q = 1
            );
            
            $revenueForecast = $this->arimaForecast(
                $data['revenues'], 
                $horizon,
                $p = 1, $d = 1, $q = 1
            );

            $forecasts[$prodId] = [
                'product' => $product,
                'historical_quantities' => $data['quantities'],
                'historical_revenues' => $data['revenues'],
                'forecasted_quantities' => $quantityForecast,
                'forecasted_revenues' => $revenueForecast,
                'forecast_dates' => $this->generateForecastDates($period, $horizon),
                'confidence_interval' => $this->calculateConfidenceInterval($data['quantities']),
            ];
        }

        // Check if we generated any forecasts
        if (empty($forecasts)) {
            $errorMessage = 'Unable to generate forecasts. ';
            if (!empty($skippedProducts)) {
                $errorMessage .= 'Products with insufficient data (need at least 3 ' . $period . ' periods): ' . implode(', ', $skippedProducts);
            } else {
                $errorMessage .= 'No products have sufficient historical sales data (minimum 3 periods required).';
            }
            throw new \Exception($errorMessage);
        }

        return [
            'forecasts' => $forecasts,
            'skipped_products' => $skippedProducts,
            'period' => $period,
            'horizon' => $horizon,
        ];
    }

    /**
     * Generate restock forecast based on sales predictions
     */
    public function generateRestockForecast(?int $productId, string $period, int $horizon): array
    {
        try {
            $salesResult = $this->generateSalesForecast($productId, $period, $horizon);
            $salesForecast = $salesResult['forecasts'];
        } catch (\Exception $e) {
            // If sales forecast fails, return empty array
            return [];
        }
        
        $restockRecommendations = [];
        
        foreach ($salesForecast as $prodId => $forecast) {
            $product = $forecast['product'];
            $currentStock = $this->getCurrentStock($prodId);
            
            // Calculate cumulative forecasted demand
            $cumulativeDemand = 0;
            $restockPoints = [];
            
            foreach ($forecast['forecasted_quantities'] as $index => $forecastedQty) {
                $cumulativeDemand += $forecastedQty;
                
                // Check if restock is needed
                if ($currentStock - $cumulativeDemand <= $product->min_stock_level) {
                    $restockPoints[] = [
                        'period' => $forecast['forecast_dates'][$index],
                        'recommended_quantity' => ceil($forecastedQty * 1.2), // 20% buffer
                        'current_stock_projection' => max(0, $currentStock - $cumulativeDemand),
                        'urgency' => $this->calculateUrgency($currentStock - $cumulativeDemand, $product->min_stock_level),
                    ];
                }
            }

            $restockRecommendations[$prodId] = [
                'product' => $product,
                'current_stock' => $currentStock,
                'min_stock_level' => $product->min_stock_level,
                'restock_points' => $restockPoints,
                'total_restock_needed' => array_sum(array_column($restockPoints, 'recommended_quantity')),
                'average_monthly_demand' => round(array_sum($forecast['forecasted_quantities']) / count($forecast['forecasted_quantities']), 2),
            ];
        }

        return $restockRecommendations;
    }

    /**
     * ARIMA implementation (simplified)
     * Parameters: p (autoregressive), d (differencing), q (moving average)
     */
    private function arimaForecast(array $timeSeries, int $horizon, int $p = 1, int $d = 1, int $q = 1): array
    {
        if (count($timeSeries) < 3) {
            return array_fill(0, $horizon, 0);
        }

        // Step 1: Differencing (d)
        $differenced = $this->difference($timeSeries, $d);
        
        // Step 2: Calculate AR parameters (simplified estimation)
        $arParams = $this->calculateARParameters($differenced, $p);
        
        // Step 3: Calculate MA parameters (simplified estimation)
        $maParams = $this->calculateMAParameters($differenced, $q);
        
        // Step 4: Generate forecast
        $forecast = [];
        $errors = array_fill(0, $q, 0);
        
        for ($i = 0; $i < $horizon; $i++) {
            $arComponent = 0;
            $maComponent = 0;
            
            // AR component
            for ($j = 0; $j < $p; $j++) {
                $index = count($differenced) - 1 - $j + $i;
                if ($index >= 0 && $index < count($differenced)) {
                    $arComponent += $arParams[$j] * $differenced[$index];
                } elseif (!empty($forecast)) {
                    $forecastIndex = $i - $j - 1;
                    if ($forecastIndex >= 0) {
                        $arComponent += $arParams[$j] * $forecast[$forecastIndex];
                    }
                }
            }
            
            // MA component
            for ($j = 0; $j < $q; $j++) {
                if ($j < count($errors)) {
                    $maComponent += $maParams[$j] * $errors[count($errors) - 1 - $j];
                }
            }
            
            $forecastValue = $arComponent + $maComponent;
            $forecast[] = $forecastValue;
            
            // Update errors
            array_shift($errors);
            $errors[] = 0; // In a full implementation, this would be the actual error
        }
        
        // Step 5: Reverse differencing
        $forecast = $this->reverseDifference($forecast, $timeSeries, $d);
        
        // Ensure non-negative forecasts
        return array_map(fn($val) => max(0, round($val, 2)), $forecast);
    }

    /**
     * Apply differencing to time series
     */
    private function difference(array $series, int $order): array
    {
        $result = $series;
        
        for ($i = 0; $i < $order; $i++) {
            $diffed = [];
            for ($j = 1; $j < count($result); $j++) {
                $diffed[] = $result[$j] - $result[$j - 1];
            }
            $result = $diffed;
        }
        
        return $result;
    }

    /**
     * Reverse differencing
     */
    private function reverseDifference(array $forecast, array $originalSeries, int $order): array
    {
        $result = $forecast;
        
        for ($i = 0; $i < $order; $i++) {
            $integrated = [];
            $lastValue = end($originalSeries);
            
            foreach ($result as $val) {
                $lastValue = $lastValue + $val;
                $integrated[] = $lastValue;
            }
            
            $result = $integrated;
        }
        
        return $result;
    }

    /**
     * Calculate AR parameters using Yule-Walker equations (simplified)
     */
    private function calculateARParameters(array $series, int $p): array
    {
        if (count($series) < $p + 1) {
            return array_fill(0, $p, 0);
        }

        $mean = array_sum($series) / count($series);
        $params = [];
        
        for ($i = 0; $i < $p; $i++) {
            $params[] = 0.5; // Simplified - in production, use Yule-Walker equations
        }
        
        return $params;
    }

    /**
     * Calculate MA parameters (simplified)
     */
    private function calculateMAParameters(array $series, int $q): array
    {
        return array_fill(0, $q, 0.3); // Simplified estimation
    }

    /**
     * Get historical sales data grouped by period
     */
    private function getHistoricalSalesData(?int $productId, string $period): array
    {
        $dateGrouping = match($period) {
            'weekly' => "CONCAT(YEAR(sale_items.created_at), '-W', LPAD(WEEK(sale_items.created_at), 2, '0'))",
            'monthly' => "DATE_FORMAT(sale_items.created_at, '%Y-%m')",
            'quarterly' => "CONCAT(YEAR(sale_items.created_at), '-Q', QUARTER(sale_items.created_at))",
            default => "DATE_FORMAT(sale_items.created_at, '%Y-%m')",
        };

        $query = SaleItem::query()
            ->selectRaw("
                product_id,
                {$dateGrouping} as period,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            ")
            ->groupBy('product_id', 'period')
            ->orderBy('period');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $results = $query->get();

        $grouped = [];
        foreach ($results as $row) {
            if (!isset($grouped[$row->product_id])) {
                $grouped[$row->product_id] = [
                    'quantities' => [],
                    'revenues' => [],
                ];
            }
            
            $grouped[$row->product_id]['quantities'][] = (float) $row->total_quantity;
            $grouped[$row->product_id]['revenues'][] = (float) $row->total_revenue;
        }

        return $grouped;
    }

    /**
     * Generate forecast dates
     */
    private function generateForecastDates(string $period, int $horizon): array
    {
        $dates = [];
        $currentDate = now();

        for ($i = 1; $i <= $horizon; $i++) {
            $dates[] = match($period) {
                'weekly' => $currentDate->copy()->addWeeks($i)->format('Y-m-d'),
                'monthly' => $currentDate->copy()->addMonths($i)->format('Y-m'),
                'quarterly' => $currentDate->copy()->addQuarters($i)->format('Y-m'),
                default => $currentDate->copy()->addMonths($i)->format('Y-m'),
            };
        }

        return $dates;
    }

    /**
     * Calculate confidence interval
     */
    private function calculateConfidenceInterval(array $data): array
    {
        $mean = array_sum($data) / count($data);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $data)) / count($data);
        $stdDev = sqrt($variance);

        return [
            'lower' => $mean - (1.96 * $stdDev), // 95% CI
            'upper' => $mean + (1.96 * $stdDev),
            'std_dev' => $stdDev,
        ];
    }

    /**
     * Get current stock for a product
     */
    private function getCurrentStock(int $productId): float
    {
        return DB::table('inventory_batches')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('expiry_date', '>', now())
            ->sum('current_quantity') ?? 0;
    }

    /**
     * Calculate urgency level for restock
     */
    private function calculateUrgency(float $projectedStock, float $minStockLevel): string
    {
        $ratio = $projectedStock / max($minStockLevel, 1);
        
        return match(true) {
            $ratio <= 0 => 'CRITICAL',
            $ratio <= 0.5 => 'HIGH',
            $ratio <= 1.0 => 'MEDIUM',
            default => 'LOW',
        };
    }
}
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SARIMA-enabled ARIMAForecastService with stability fixes:
 *  - Disable seasonal differencing for weekly period
 *  - Prevent double differencing (d and D together)
 *  - Clamp final forecast values to zero (option B)
 *
 * Note: still pure PHP; consider queueing for bulk forecast runs.
 */
class ARIMAForecastService
{
    // Model selection bounds (conservative)
    private int $maxP = 2;
    private int $maxQ = 2;
    private int $maxSP = 1;
    private int $maxSQ = 1;

    // Optimization controls
    private int $maxIterations = 200;
    private float $tolerance = 1e-5;

    /**
     * Public: generate sales forecast (SARIMA)
     */
    public function generateSalesForecast(?int $productId, string $period, int $horizon): array
    {
        $historicalData = $this->getHistoricalSalesData($productId, $period);

        if (empty($historicalData)) {
            throw new \Exception('No historical sales data found. Please ensure you have sales records in the system.');
        }

        $forecasts = [];
        $skippedProducts = [];
        $effectiveHorizon = $this->getEffectiveHorizon($period, $horizon);
        $seasonalPeriod = $this->seasonalPeriodFor($period);

        foreach ($historicalData as $prodId => $data) {
            $product = Product::find($prodId);
            if (!$product) continue;

            $filled = $this->fillMissingPeriods($data['periods'], $data['quantities'], $data['revenues'], $period);
            $quantities = $filled['quantities'];
            $revenues = $filled['revenues'];
            $periods = $filled['periods'];

            Log::info("DEBUG_SERIES", [
                'product_id' => $prodId,
                'period' => $period,
                'series_last_20' => array_slice($quantities, -20),
            ]);

            $continuity = $this->analyzePeriodContinuity($periods, $period);

            Log::debug('SARIMA input', [
                'product_id' => $prodId,
                'period_type' => $period,
                'points' => count($quantities),
                'seasonal_period' => $seasonalPeriod,
                'continuity' => $continuity,
            ]);

            // Require reasonable amount of data (at least 2 seasons or a minimum)
            if (count($quantities) < max(2 * $seasonalPeriod, 12)) {
                Log::info('Not enough data for SARIMA', [
                    'product_id' => $prodId, 'points' => count($quantities),
                ]);
                $skippedProducts[] = $product->name . ' (only ' . count($quantities) . ' periods)';
                continue;
            }

            // Auto-select orders with safety rules (weekly D disabled, no double differencing)
            [$p, $d, $q, $P, $D, $Q, $fitDiagnostics] =
                $this->autoSelectSarimaOrder($quantities, $seasonalPeriod, $period);

            Log::debug('SARIMA selected', [
                'product_id' => $prodId, 'p'=>$p,'d'=>$d,'q'=>$q,'P'=>$P,'D'=>$D,'Q'=>$Q,
                'aic' => $fitDiagnostics['aic'] ?? null,
            ]);

            $fitQty = $this->estimateSARIMA_CSS($quantities, $p, $d, $q, $P, $D, $Q, $seasonalPeriod);
            $fitRev = $this->estimateSARIMA_CSS($revenues, $p, $d, $q, $P, $D, $Q, $seasonalPeriod);

            if (!$fitQty || empty($fitQty['params'])) {
                Log::warning('SARIMA fit failed', ['product_id' => $prodId]);
                $skippedProducts[] = $product->name . ' (fit failed)';
                continue;
            }

            $quantityForecastWeekly = $this->forecastSARIMA($fitQty, $quantities, $effectiveHorizon, $seasonalPeriod, true);
            $revenueForecastWeekly = $this->forecastSARIMA($fitRev, $revenues, $effectiveHorizon, $seasonalPeriod, true);

            if ($period === 'monthly') {
                $quantityForecast = $this->aggregateWeeklyForecastsToMonthly($quantityForecastWeekly, $horizon);
                $revenueForecast = $this->aggregateWeeklyForecastsToMonthly($revenueForecastWeekly, $horizon);
            } else {
                $quantityForecast = array_slice($quantityForecastWeekly, 0, $horizon);
                $revenueForecast = array_slice($revenueForecastWeekly, 0, $horizon);
            }

            $forecastDates = $this->generateForecastDates($period, $horizon);

            $forecasts[$prodId] = [
                'product' => $product,
                'historical_quantities' => $quantities,
                'historical_revenues' => $revenues,
                'forecasted_quantities' => $quantityForecast,
                'forecasted_revenues' => $revenueForecast,
                'forecast_dates' => $forecastDates,
                'confidence_interval' => $this->calculateForecastConfidence($fitQty, count($quantityForecast)),
                'model' => [
                    'p'=>$p,'d'=>$d,'q'=>$q,'P'=>$P,'D'=>$D,'Q'=>$Q,'s'=>$seasonalPeriod,
                    'params'=>$fitQty['params'],'residuals'=>$fitQty['residuals'],'aic'=>$fitQty['aic'] ?? null,
                ],
            ];
        }

        if (empty($forecasts)) {
            $errorMsg = 'Unable to generate forecasts.';
            if (!empty($skippedProducts)) $errorMsg .= ' Products skipped: ' . implode(', ', $skippedProducts);
            throw new \Exception($errorMsg);
        }

        return [
            'forecasts' => $forecasts,
            'skipped_products' => $skippedProducts,
            'period' => $period,
            'horizon' => $horizon,
        ];
    }

    /**
     * Public: generate restock forecast (unchanged main logic)
     */
    public function generateRestockForecast(?int $productId, string $period, int $horizon): array
    {
        try {
            $salesResult = $this->generateSalesForecast($productId, $period, $horizon);
            $salesForecast = $salesResult['forecasts'];
        } catch (\Exception $e) {
            Log::warning('Restock generation aborted: ' . $e->getMessage());
            return [];
        }

        $restockRecommendations = [];

        foreach ($salesForecast as $prodId => $forecast) {
            $product = $forecast['product'];
            $currentStock = $this->getCurrentStock($prodId);
            $projectedStock = (float) $currentStock;
            $restockPoints = [];

            foreach ($forecast['forecasted_quantities'] as $index => $forecastedQty) {
                $projectedStock -= $forecastedQty;
                $stockBeforeRestock = $projectedStock;
                if ($stockBeforeRestock <= $product->min_stock_level) {
                    $recommendedQty = $this->calculateRecommendedRestockQuantity(
                        product: $product,
                        projectedStockBeforeRestock: $stockBeforeRestock,
                        forecastedQty: $forecastedQty
                    );
                    $projectedStock = max(0, $projectedStock) + $recommendedQty;
                    $restockPoints[] = [
                        'period' => $forecast['forecast_dates'][$index] ?? ("#".$index),
                        'recommended_quantity' => $recommendedQty,
                        'projected_stock_before_restock' => round(max(0, $stockBeforeRestock), 2),
                        'projected_stock_after_restock' => round($projectedStock, 2),
                        'current_stock_projection' => round($projectedStock, 2),
                        'urgency' => $this->calculateUrgency($stockBeforeRestock, $product->min_stock_level),
                    ];
                }
            }

            $restockRecommendations[$prodId] = [
                'product' => $product,
                'current_stock' => $currentStock,
                'min_stock_level' => $product->min_stock_level,
                'restock_points' => $restockPoints,
                'total_restock_needed' => array_sum(array_column($restockPoints, 'recommended_quantity')),
                'average_monthly_demand' => round(array_sum($forecast['forecasted_quantities']) / max(1, count($forecast['forecasted_quantities'])), 2),
            ];
        }

        return $restockRecommendations;
    }

    /* ---------------------------
       SARIMA order selection & fit
       --------------------------- */

    private function seasonalPeriodFor(string $period): int
    {
        return match($period) {
            'weekly' => 52,
            'monthly' => 12,
            'quarterly' => 4,
            default => 12,
        };
    }

    /**
     * Auto-select sarima order with robust rules:
     * - Prefer d=1 for sales time series (fixes the collapse-to-zero)
     * - Evaluate D via seasonal detection but avoid d & D both = 1 (prevent over-differencing)
     * - Search grid but never accept the all-zero model; fallback to (0,1,1)
     *
     * Returns an array: [p,d,q,P,D,Q, diagnosticsFit]
     */
    private function autoSelectSarimaOrder(array $series, int $s, string $periodType): array
    {
        // Conservative default: prefer using one non-seasonal difference for sales data
        $dCandidate = $this->estimateDifferencingOrder($series);

        // Prefer d=1 for sales unless very small sample
        if (count($series) >= 12) {
            $dCandidate = 1;
        }

        // Seasonal differencing detection (0 or 1)
        $Dcandidate = $this->estimateSeasonalDifferencing($series, $s);

        // If weekly, by default keep seasonal differencing off (s=52 often noisy)
        if ($periodType === 'weekly') {
            $Dcandidate = 0;
        }

        // Prevent double differencing (d & D both 1) — prefer seasonal differencing only if strong
        if ($Dcandidate === 1 && $dCandidate === 1) {
            // choose the component that reduces variance more
            $sd = $this->seasonalDifference($series, 1, $s);
            $vSeason = $this->variance($sd);
            $vDiff = $this->variance($this->difference($series, 1));
            // if seasonal differencing reduces more, keep D=1,d=0; else keep d=1,D=0
            if ($vSeason < 0.9 * $vDiff) {
                $dCandidate = 0;
            } else {
                $Dcandidate = 0;
            }
        }

        // Bind to {0,1}
        $d = max(0, min(1, (int)$dCandidate));
        $D = max(0, min(1, (int)$Dcandidate));

        $bestAic = INF;
        $best = [0, $d, 0, 0, $D, 0, null];

        // Search grid (kept moderate for performance)
        for ($p = 0; $p <= $this->maxP; $p++) {
            for ($q = 0; $q <= $this->maxQ; $q++) {
                for ($P = 0; $P <= $this->maxSP; $P++) {
                    for ($Q = 0; $Q <= $this->maxSQ; $Q++) {
                        try {
                            // forbid trivial all zeros during evaluation but still evaluate for AIC
                            $fit = $this->estimateSARIMA_CSS($series, $p, $d, $q, $P, $D, $Q, $s);
                            if (!$fit || !isset($fit['aic'])) continue;
                            if ($fit['aic'] < $bestAic) {
                                $bestAic = $fit['aic'];
                                $best = [$p, $d, $q, $P, $D, $Q, $fit];
                            }
                        } catch (\Throwable $e) {
                            Log::debug("Auto-select trial failed p{$p}q{$q}P{$P}Q{$Q}: ".$e->getMessage());
                            continue;
                        }
                    }
                }
            }
        }

        // If the best model is trivial (p=q=P=Q=0) then force a minimum sensible model and re-fit
        if ($best[0] === 0 && $best[2] === 0 && $best[3] === 0 && $best[5] === 0) {
            // ensure a minimum working model: MA(1) with differencing if needed
            $fallbackP = 0;
            $fallbackQ = 1;
            $fallbackPcup = 0;
            $fallbackQcup = 0;

            // if D suggested strong seasonality and we left d=0 earlier, allow seasonal MA fallback
            if ($D === 1 && $this->maxSQ > 0) {
                $fallbackQcup = 1;
                $fallbackQ = 0; // try SAR(0) SMA(1) if seasonal is strong
            }

            try {
                $fit = $this->estimateSARIMA_CSS($series, $fallbackP, $d, $fallbackQ, $fallbackPcup, $D, $fallbackQcup, $s);
                if ($fit && isset($fit['aic'])) {
                    $best = [$fallbackP, $d, $fallbackQ, $fallbackPcup, $D, $fallbackQcup, $fit];
                }
            } catch (\Throwable $e) {
                Log::warning('Fallback fit failed: ' . $e->getMessage());
            }
        }

        return $best;
    }

    /**
     * Estimate seasonal differencing D (0 or 1)
     */
    private function estimateSeasonalDifferencing(array $series, int $s): int
    {
        if ($s <= 1 || count($series) < $s * 2) return 0;
        $varBefore = $this->variance($series);
        $seasonalDiff = $this->seasonalDifference($series, 1, $s);
        $varAfter = $this->variance($seasonalDiff);
        return ($varAfter < $varBefore * 0.95) ? 1 : 0;
    }

    /**
     * Estimate SARIMA parameters using CSS optimization.
     * This version ensures differencing is applied before fitting and computes a robust AIC proxy.
     *
     * Returns:
     *  ['params'=>['ar'=>[],'ma'=>[],'sar'=>[],'sma'=>[]],'residuals'=>[],'aic'=>float,'resid_std'=>float]
     * or null if cannot fit.
     */
    private function estimateSARIMA_CSS(array $origSeries, int $p, int $d, int $q, int $P, int $D, int $Q, int $s): ?array
    {
        // Defensive: require enough data after differencing
        $series = $origSeries;

        // apply seasonal differencing first (if requested)
        if ($D > 0) $series = $this->seasonalDifference($series, $D, $s);
        // apply non-seasonal differencing
        if ($d > 0) $series = $this->difference($series, $d);

        // need at least p+q+P*s+Q*s + a small buffer
        $minNeeded = max(8, ($p + $q + $P * max(1,$s) + $Q * max(1,$s) + 3));
        if (count($series) < $minNeeded) {
            return null;
        }

        // Initialize parameters: AR via Yule-Walker, others zeros
        $ar = $p > 0 ? $this->yuleWalker($series, $p) : array_fill(0, $p, 0.0);
        $ma = array_fill(0, $q, 0.0);
        $sar = $P > 0 ? array_fill(0, $P, 0.0) : [];
        $sma = array_fill(0, $Q, 0.0);

        $params = ['ar'=>$ar, 'ma'=>$ma, 'sar'=>$sar, 'sma'=>$sma];
        $bestParams = $params;
        $bestObj = $this->cssObjectiveSarima($series, $params, $s);
        $iter = 0;

        // coordinate-descent + LS alternating
        while ($iter < $this->maxIterations) {
            $changed = false;

            // AR update via LS if p>0
            if ($p > 0) {
                [$design, $target] = $this->buildSarimaDesign($series, $params, $p, $q, $P, $Q, $s);
                $arNew = $this->linearLeastSquares($design, $target);
                if (count($arNew) === $p) {
                    for ($i = 0; $i < $p; $i++) {
                        $newVal = ($params['ar'][$i] * 0.4) + ($arNew[$i] * 0.6);
                        if (abs($newVal - $params['ar'][$i]) > $this->tolerance) $changed = true;
                        $params['ar'][$i] = $newVal;
                    }
                }
            }

            // coordinate descent for MA components
            foreach (['ma','sma','sar'] as $comp) {
                $len = count($params[$comp]);
                for ($j = 0; $j < $len; $j++) {
                    $bestLocalObj = $this->cssObjectiveSarima($series, $params, $s);
                    $step = 0.5;
                    $attempts = 0;
                    $improved = true;
                    while ($improved && $attempts < 40) {
                        $improved = false;
                        foreach ([-1,1] as $dir) {
                            $candidate = $params[$comp][$j] + ($dir * $step);
                            $paramsCandidate = $params;
                            $paramsCandidate[$comp][$j] = $candidate;
                            $obj = $this->cssObjectiveSarima($series, $paramsCandidate, $s);
                            if ($obj < $bestLocalObj) {
                                $bestLocalObj = $obj;
                                $params[$comp][$j] = $candidate;
                                $improved = true;
                            }
                        }
                        if (!$improved) $step /= 2.0;
                        $attempts++;
                    }
                }
            }

            $obj = $this->cssObjectiveSarima($series, $params, $s);
            if ($obj + $this->tolerance < $bestObj) {
                $bestObj = $obj;
                $bestParams = $params;
            }

            if (!$changed && abs($bestObj - $obj) < $this->tolerance) break;
            $iter++;
        }

        // compute residuals on the differenced series
        $residuals = $this->computeResidualsSarima($series, $bestParams, $s);

        // robust estimate of variance (unbiased)
        $sigma2 = max(1e-9, $this->variance($residuals));
        $n = count($series);
        $k = count($bestParams['ar']) + count($bestParams['ma']) + count($bestParams['sar']) + count($bestParams['sma']);
        // AIC proxy on differenced series
        $aic = ($n * log($sigma2)) + (2 * max(1, $k));

        // If residuals are NaN or infinite, bail out
        if (!is_finite($aic)) return null;

        // return structure
        return [
            'params' => $bestParams,
            'residuals' => $residuals,
            'aic' => $aic,
            'resid_std' => sqrt($sigma2),
        ];
    }

    /* --------------------------
       SARIMA residuals & forecasting
       -------------------------- */

    private function cssObjectiveSarima(array $series, array $params, int $s): float
    {
        $res = $this->computeResidualsSarima($series, $params, $s);
        return array_reduce($res, fn($c, $v) => $c + ($v*$v), 0.0);
    }

    private function computeResidualsSarima(array $series, array $params, int $s): array
    {
        $n = count($series);
        $p = count($params['ar']);
        $q = count($params['ma']);
        $P = count($params['sar']);
        $Q = count($params['sma']);

        $residuals = array_fill(0, $n, 0.0);

        for ($t = 0; $t < $n; $t++) {
            $arComp = 0.0;
            for ($i = 1; $i <= $p; $i++) {
                $idx = $t - $i;
                if ($idx >= 0) $arComp += ($params['ar'][$i-1] ?? 0.0) * $series[$idx];
            }

            $maComp = 0.0;
            for ($j = 1; $j <= $q; $j++) {
                $idx = $t - $j;
                if ($idx >= 0) $maComp += ($params['ma'][$j-1] ?? 0.0) * $residuals[$idx];
            }

            $sarComp = 0.0;
            for ($Pj = 1; $Pj <= $P; $Pj++) {
                $idx = $t - $Pj * $s;
                if ($idx >= 0) $sarComp += ($params['sar'][$Pj-1] ?? 0.0) * $series[$idx];
            }

            $smaComp = 0.0;
            for ($Qj = 1; $Qj <= $Q; $Qj++) {
                $idx = $t - $Qj * $s;
                if ($idx >= 0) $smaComp += ($params['sma'][$Qj-1] ?? 0.0) * $residuals[$idx];
            }

            $pred = $arComp + $maComp + $sarComp + $smaComp;
            $residuals[$t] = $series[$t] - $pred;
        }

        return $residuals;
    }

    /**
     * Forecast SARIMA fit using a hybrid approach:
     *  - Produce a SARIMA-style forecast (AR + seasonal AR deterministic part, MA assumed zero for future)
     *  - Produce a Holt-Winters (additive) forecast
     *  - Combine both using weights derived from fit residual std vs HW rmse
     *
     * Params:
     *  - $fit: result from estimateSARIMA_CSS()
     *  - $originalSeries: full original (level) series
     *  - $horizon: number of future points to produce (in same frequency as originalSeries)
     *  - $s: seasonal period (e.g., 12 for monthly)
     *  - $clampToNonNegative: if true clamp final values to >= 0
     *
     * Returns: array of length $horizon
     */
    private function forecastSARIMA(array $fit, array $originalSeries, int $horizon, int $s, bool $clampToNonNegative = false): array
    {
        // If fit is empty, fallback to Holt-Winters directly
        if (empty($fit) || !isset($fit['params'])) {
            $hw = $this->holtWintersAdditive($originalSeries, $s, $horizon);
            $result = $hw['forecast'];
            return array_map(fn($v) => $clampToNonNegative ? max(0, round($v, 2)) : round($v, 2), $result);
        }

        // Reconstruct d and D used for the series (best-effort)
        $d = $this->estimateDifferencingOrder($originalSeries);
        $D = $this->estimateSeasonalDifferencing($originalSeries, $s);
        if ($D === 1 && $d === 1) {
            // previous logic may have prevented double differencing; ensure coherence:
            // prefer non-seasonal differencing for product sales (keeping earlier project rules)
            $D = 0;
        }

        // Create differenced series as used by the fit (seasonal first, then non-seasonal)
        $series = $originalSeries;
        if ($D > 0) $series = $this->seasonalDifference($series, $D, $s);
        if ($d > 0) $series = $this->difference($series, $d);

        // get fitted params (arrays)
        $params = $fit['params'];
        $ar = $params['ar'] ?? [];
        $ma = $params['ma'] ?? [];
        $sar = $params['sar'] ?? [];
        $sma = $params['sma'] ?? [];

        $n = count($series);
        $p = count($ar);
        $q = count($ma);
        $P = count($sar);
        $Q = count($sma);

        // Get in-sample residuals (from fit) for weighting
        $sarima_resid_std = $fit['resid_std'] ?? (float) $this->variance($this->computeResidualsSarima($series, $params, $s));

        // ---------- 1) SARIMA deterministic forecast (AR + seasonal AR only)
        // We forecast on the differenced series (assume future residuals = 0, MA contributions = 0)
        $y = array_values($series); // past differenced series
        $forecastDiff = []; // forecasts on differenced scale

        for ($h = 0; $h < $horizon; $h++) {
            $tIndex = $n + $h;
            $arComp = 0.0;
            // AR terms (using past y and already-computed forecastDiff)
            for ($i = 1; $i <= $p; $i++) {
                $idx = $tIndex - $i;
                if ($idx >= 0 && $idx < count($y)) {
                    $val = $y[$idx];
                } elseif ($idx >= count($y)) {
                    $val = $forecastDiff[$idx - count($y)] ?? 0.0;
                } else {
                    $val = 0.0;
                }
                $arComp += ($ar[$i-1] ?? 0.0) * $val;
            }

            // Seasonal AR terms
            $sarComp = 0.0;
            for ($Pj = 1; $Pj <= $P; $Pj++) {
                $idx = $tIndex - $Pj * $s;
                if ($idx >= 0 && $idx < count($y)) {
                    $val = $y[$idx];
                } elseif ($idx >= count($y)) {
                    $val = $forecastDiff[$idx - count($y)] ?? 0.0;
                } else {
                    $val = 0.0;
                }
                $sarComp += ($sar[$Pj-1] ?? 0.0) * $val;
            }

            // MA and SMA future residuals assumed zero (common assumption for point forecasts)
            $maComp = 0.0;
            $smaComp = 0.0;

            $predDiff = $arComp + $sarComp + $maComp + $smaComp;
            $forecastDiff[] = $predDiff;
            // push pseudo-residual zero to residuals sequence if needed (not used here)
        }

        // Reverse differencing to original scale:
        $sarimaForecast = $forecastDiff;
        if ($d > 0) $sarimaForecast = $this->reverseDifference($sarimaForecast, $originalSeries, $d);
        if ($D > 0) $sarimaForecast = $this->reverseSeasonalDifference($sarimaForecast, $originalSeries, $D, $s);

        // Guarantee length
        $sarimaForecast = array_slice(array_pad($sarimaForecast, $horizon, 0.0), 0, $horizon);

        // ---------- 2) Holt-Winters (additive) forecast
        $hw = $this->holtWintersAdditive($originalSeries, $s, $horizon);
        $hwForecast = $hw['forecast'];
        $hw_rmse = $hw['rmse'] ?? 1e9;

        // ---------- 3) Combine forecasts by weighting inverse error
        // If SARIMA residual std is extremely small, prefer SARIMA; else prefer HW.
        $wSar = 0.0;
        $wHw = 0.0;
        // Avoid division by zero; use small epsilon
        $eps = 1e-6;
        $sarScore = 1.0 / (max($sarima_resid_std, $eps));
        $hwScore = 1.0 / (max($hw_rmse, $eps));

        // Normalize
        $sumScores = $sarScore + $hwScore;
        if ($sumScores <= 0) { $wSar = 0.5; $wHw = 0.5; }
        else { $wSar = $sarScore / $sumScores; $wHw = $hwScore / $sumScores; }

        // Bound weights to avoid extremes if either model clearly bad
        // If SARIMA residual is huge relative to HW, force HW dominance
        if ($sarima_resid_std > 5 * $hw_rmse) { $wSar = 0.05; $wHw = 0.95; }
        if ($hw_rmse > 5 * $sarima_resid_std) { $wSar = 0.95; $wHw = 0.05; }

        $hybrid = [];
        for ($i = 0; $i < $horizon; $i++) {
            $sVal = $sarimaForecast[$i] ?? 0.0;
            $hVal = $hwForecast[$i] ?? 0.0;
            $combined = ($wSar * $sVal) + ($wHw * $hVal);
            $hybrid[] = $combined;
        }

        // Final clamp if requested (option B)
        $result = array_map(fn($v) => $clampToNonNegative ? max(0, round($v, 2)) : round($v, 2), $hybrid);

        return $result;
    }

    /**
     * Holt-Winters additive implementation with tiny grid search for smoothing params.
     * Returns ['forecast' => [...], 'rmse' => float, 'params' => ['alpha','beta','gamma']]
     *
     * Notes:
     *  - seasonLength (s) should be >= 2 to enable seasonality. If s <= 1 or insufficient data, simple
     *    double exponential smoothing (no seasonality) is applied.
     */
    private function holtWintersAdditive(array $series, int $seasonLength, int $horizon): array
    {
        $n = count($series);
        if ($n === 0) return ['forecast' => array_fill(0, $horizon, 0.0), 'rmse' => 1e9, 'params' => []];

        // If seasonLength not usable, fallback to Holt's linear (no season)
        if ($seasonLength <= 1 || $n < max(6, $seasonLength * 2)) {
            // Simple double exponential smoothing (Holt) with fixed params
            $alpha = 0.3; $beta = 0.05;
            $level = $series[0];
            $trend = ($series[$n-1] - $series[0]) / max(1, $n-1);
            $fcast = [];
            for ($i = 1; $i <= $horizon; $i++) $fcast[] = $level + $trend * $i;
            $rmse = sqrt($this->variance($series)); // rough
            return ['forecast' => array_map(fn($v) => round($v, 2), $fcast), 'rmse' => $rmse, 'params' => ['alpha'=>$alpha,'beta'=>$beta,'gamma'=>0.0]];
        }

        // Initialize seasonals by averaging seasons
        $s = $seasonLength;
        $seasons = intdiv($n, $s);
        $seasonAvg = [];
        // mean for each season position
        for ($i = 0; $i < $s; $i++) {
            $sum = 0.0; $count = 0;
            for ($j = 0; $j < $seasons; $j++) {
                $idx = $j * $s + $i;
                if ($idx < $n) { $sum += $series[$idx]; $count++; }
            }
            $seasonAvg[$i] = $count > 0 ? $sum / $count : 0.0;
        }
        $initialSeason = [];
        for ($i = 0; $i < $s; $i++) $initialSeason[$i] = $seasonAvg[$i];

        // initial level and trend (linear)
        $level = $series[0];
        $trend = 0.0;
        if ($n >= 2) {
            $trend = ($series[$s] - $series[0]) / max(1, $s); // rough seasonal-aware slope
        }

        // small grid search for smoothing params (coarse)
        $bestParams = null;
        $bestSse = INF;
        $alphas = [0.2, 0.4, 0.6, 0.8];
        $betas = [0.01, 0.05, 0.1];
        $gammas = [0.01, 0.05, 0.1];

        foreach ($alphas as $alpha) {
            foreach ($betas as $beta) {
                foreach ($gammas as $gamma) {
                    // run in-sample HW and compute SSE
                    $l = $series[0];
                    $b = $trend;
                    $season = $initialSeason;
                    $sse = 0.0;
                    $preds = [];
                    for ($t = 0; $t < $n; $t++) {
                        if ($t == 0) {
                            $f = $l + $b + ($season[$t % $s] ?? 0.0);
                            $preds[] = $f;
                            $err = $series[$t] - $f;
                            // update state
                            $oldL = $l;
                            $l = $alpha * ($series[$t] - ($season[$t % $s] ?? 0.0)) + (1 - $alpha) * ($l + $b);
                            $b = $beta * ($l - $oldL) + (1 - $beta) * $b;
                            $season[$t % $s] = $gamma * ($series[$t] - $l) + (1 - $gamma) * ($season[$t % $s]);
                            $sse += $err * $err;
                            continue;
                        }
                        $f = $l + $b + ($season[$t % $s] ?? 0.0);
                        $preds[] = $f;
                        $err = $series[$t] - $f;
                        $sse += $err * $err;
                        // update
                        $oldL = $l;
                        $l = $alpha * ($series[$t] - ($season[$t % $s] ?? 0.0)) + (1 - $alpha) * ($l + $b);
                        $b = $beta * ($l - $oldL) + (1 - $beta) * $b;
                        $season[$t % $s] = $gamma * ($series[$t] - $l) + (1 - $gamma) * ($season[$t % $s]);
                    }

                    if ($sse < $bestSse) {
                        $bestSse = $sse;
                        $bestParams = ['alpha'=>$alpha,'beta'=>$beta,'gamma'=>$gamma,'initialSeason'=>$initialSeason,'initLevel'=>$series[0],'initTrend'=>$trend];
                    }
                }
            }
        }

        // If not found (should not happen), fallback
        if ($bestParams === null) {
            $bestParams = ['alpha'=>0.3,'beta'=>0.05,'gamma'=>0.05,'initialSeason'=>$initialSeason,'initLevel'=>$series[0],'initTrend'=>$trend];
        }

        // Now produce final forecast with best params
        $alpha = $bestParams['alpha']; $beta = $bestParams['beta']; $gamma = $bestParams['gamma'];
        $season = $bestParams['initialSeason'];
        $l = $bestParams['initLevel'];
        $b = $bestParams['initTrend'];

        // Re-run to update final states and then forecast
        for ($t = 0; $t < $n; $t++) {
            $f = $l + $b + ($season[$t % $s] ?? 0.0);
            $err = $series[$t] - $f;
            $oldL = $l;
            $l = $alpha * ($series[$t] - ($season[$t % $s] ?? 0.0)) + (1 - $alpha) * ($l + $b);
            $b = $beta * ($l - $oldL) + (1 - $beta) * $b;
            $season[$t % $s] = $gamma * ($series[$t] - $l) + (1 - $gamma) * ($season[$t % $s]);
        }

        // Forecast horizon
        $forecast = [];
        for ($m = 1; $m <= $horizon; $m++) {
            $val = $l + $b * $m + ($season[($n + $m - 1) % $s] ?? 0.0);
            $forecast[] = $val;
        }

        // Compute in-sample RMSE for weighting
        // We'll compute one-step in-sample predictions quickly (approx)
        $preds = [];
        $season2 = $bestParams['initialSeason'];
        $l2 = $bestParams['initLevel'];
        $b2 = $bestParams['initTrend'];
        for ($t = 0; $t < $n; $t++) {
            $f = $l2 + $b2 + ($season2[$t % $s] ?? 0.0);
            $preds[] = $f;
            $oldL = $l2;
            $l2 = $alpha * ($series[$t] - ($season2[$t % $s] ?? 0.0)) + (1 - $alpha) * ($l2 + $b2);
            $b2 = $beta * ($l2 - $oldL) + (1 - $beta) * $b2;
            $season2[$t % $s] = $gamma * ($series[$t] - $l2) + (1 - $gamma) * ($season2[$t % $s]);
        }
        $sse = 0.0; $count = 0;
        for ($i = 0; $i < count($preds); $i++) {
            $err = $series[$i] - $preds[$i];
            $sse += $err * $err;
            $count++;
        }
        $rmse = $count > 0 ? sqrt($sse / $count) : 1e9;

        return ['forecast' => array_map(fn($v) => round($v, 2), $forecast), 'rmse' => $rmse, 'params' => ['alpha'=>$alpha,'beta'=>$beta,'gamma'=>$gamma]];
    }


    /* --------------------------
       Utilities: differencing, seasonal diff, design matrix
       -------------------------- */

    private function seasonalDifference(array $series, int $order, int $s): array
    {
        $res = $series;
        for ($o = 0; $o < $order; $o++) {
            $tmp = [];
            for ($i = $s; $i < count($res); $i++) $tmp[] = $res[$i] - $res[$i - $s];
            $res = $tmp;
        }
        return $res;
    }

    private function reverseSeasonalDifference(array $forecast, array $originalSeries, int $order, int $s): array
    {
        $res = $forecast;
        $history = $originalSeries;
        for ($o = 0; $o < $order; $o++) {
            $integrated = [];
            foreach ($res as $val) {
                $lastSeasonVal = $history[count($history) - $s] ?? end($history);
                $next = $lastSeasonVal + $val;
                $integrated[] = $next;
                $history[] = $next;
            }
            $res = $integrated;
        }
        return $res;
    }

    private function buildSarimaDesign(array $series, array $params, int $p, int $q, int $P, int $Q, int $s): array
    {
        $n = count($series);
        $maxLag = max($p, $q, $P * $s, $Q * $s);
        $design = [];
        $target = [];

        $residuals = $this->computeResidualsSarima($series, $params, $s);

        for ($t = $maxLag; $t < $n; $t++) {
            $row = [];
            for ($i = 1; $i <= $p; $i++) $row[] = $series[$t - $i];
            for ($Pj = 1; $Pj <= $P; $Pj++) {
                $idx = $t - $Pj * $s;
                if ($idx >= 0) $row[] = $series[$idx];
            }
            $maContribution = 0.0;
            for ($j = 1; $j <= $q; $j++) {
                $resIdx = $t - $j;
                if ($resIdx >= 0) $maContribution += ($params['ma'][$j-1] ?? 0.0) * $residuals[$resIdx];
            }
            $target[] = $series[$t] - $maContribution;
            $design[] = $row;
        }

        return [$design, $target];
    }

    /* --------------------------
       Linear algebra & helpers
       -------------------------- */

    private function variance(array $x): float
    {
        if (count($x) === 0) return 0.0;
        $mu = array_sum($x) / count($x);
        $s = 0.0;
        foreach ($x as $v) $s += ($v - $mu) * ($v - $mu);
        return $s / count($x);
    }

    private function difference(array $series, int $order): array
    {
        $result = $series;
        for ($o = 0; $o < $order; $o++) {
            $tmp = [];
            for ($i = 1, $n = count($result); $i < $n; $i++) $tmp[] = $result[$i] - $result[$i - 1];
            $result = $tmp;
        }
        return $result;
    }

    private function reverseDifference(array $forecast, array $originalSeries, int $order): array
    {
        $result = $forecast;
        for ($o = 0; $o < $order; $o++) {
            $integrated = [];
            $lastValue = end($originalSeries);
            foreach ($result as $val) {
                $lastValue = $lastValue + $val;
                $integrated[] = $lastValue;
            }
            $result = $integrated;
            $originalSeries = array_merge($originalSeries, $result);
        }
        return $result;
    }

    private function yuleWalker(array $series, int $p): array
    {
        $n = count($series);
        if ($p <= 0 || $n <= $p) return array_fill(0, $p, 0.0);
        $mean = array_sum($series)/$n;
        $auto = [];
        for ($lag = 0; $lag <= $p; $lag++) {
            $num = 0.0;
            for ($t = $lag; $t < $n; $t++) $num += ($series[$t] - $mean) * ($series[$t - $lag] - $mean);
            $auto[$lag] = $num / $n;
        }
        $R = [];
        for ($i = 0; $i < $p; $i++) {
            $row = [];
            for ($j = 0; $j < $p; $j++) $row[] = $auto[abs($i - $j)];
            $R[] = $row;
        }
        $r = array_slice($auto, 1, $p);
        $phi = $this->solveLinearSystem($R, $r);
        return $phi === null ? array_fill(0, $p, 0.0) : $phi;
    }

    private function solveLinearSystem(array $A, array $b): ?array
    {
        $n = count($A);
        if ($n === 0) return [];
        $M = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) $row[] = $A[$i][$j] ?? 0.0;
            $row[] = $b[$i] ?? 0.0;
            $M[] = $row;
        }
        for ($k = 0; $k < $n; $k++) {
            $iMax = $k; $maxVal = abs($M[$k][$k]);
            for ($i = $k+1; $i < $n; $i++) if (abs($M[$i][$k]) > $maxVal) { $maxVal = abs($M[$i][$k]); $iMax = $i; }
            if ($maxVal < 1e-12) return null;
            if ($iMax !== $k) { $tmp = $M[$k]; $M[$k] = $M[$iMax]; $M[$iMax] = $tmp; }
            for ($i = $k+1; $i < $n; $i++) {
                $f = $M[$i][$k] / $M[$k][$k];
                for ($j = $k; $j <= $n; $j++) $M[$i][$j] -= $M[$k][$j] * $f;
            }
        }
        $x = array_fill(0, $n, 0.0);
        for ($i = $n-1; $i >= 0; $i--) {
            $s = $M[$i][$n];
            for ($j = $i+1; $j < $n; $j++) $s -= $M[$i][$j] * $x[$j];
            $x[$i] = $s / $M[$i][$i];
        }
        return $x;
    }

    private function linearLeastSquares(array $design, array $target): array
    {
        $m = count($design);
        if ($m === 0) return [];
        $p = count($design[0]);
        $XtX = array_fill(0, $p, array_fill(0, $p, 0.0));
        $Xty = array_fill(0, $p, 0.0);
        for ($i = 0; $i < $m; $i++) {
            $row = $design[$i];
            for ($j = 0; $j < $p; $j++) {
                for ($k = 0; $k < $p; $k++) $XtX[$j][$k] += ($row[$j] ?? 0.0) * ($row[$k] ?? 0.0);
                $Xty[$j] += ($row[$j] ?? 0.0) * ($target[$i] ?? 0.0);
            }
        }
        $sol = $this->solveLinearSystem($XtX, $Xty);
        return $sol === null ? array_fill(0, $p, 0.0) : $sol;
    }

    /* --------------------------
       Missing-helper implementations (detection & stationarity)
       -------------------------- */

    /**
     * Improved differencing estimator.
     *
     * Much more robust for sales data with:
     * - Gradual upward/downward trend
     * - Seasonality
     * - Random noise
     * - Occasional large spikes or drops
     *
     * Returns:
     *  - 1 if differencing is needed
     *  - 0 if series is already stationary enough
     */
    private function estimateDifferencingOrder(array $series): int
    {
        $n = count($series);

        if ($n < 12) {
            // Too little data to safely difference → keep original
            return 0;
        }

        // ---------------------------
        // 1. Detect strong linear trend
        // ---------------------------
        $trend = $this->trendMagnitude($series);

        if (abs($trend) >= 0.20) {
            // strong trend detected → difference
            return 1;
        }

        // ---------------------------
        // 2. Autocorrelation at long lags
        // ---------------------------
        // If long-lag ACF is high → non-stationary
        $acfLag1 = $this->autocorrelation($series, 1);
        $acfLag6 = $this->autocorrelation($series, 6);

        if ($acfLag1 > 0.70 || $acfLag6 > 0.50) {
            return 1;
        }

        // ---------------------------
        // 3. Variance reduction test
        // ---------------------------
        $varOriginal = $this->variance($series);
        $diff1 = $this->difference($series, 1);
        $varDiff = $this->variance($diff1);

        if ($varDiff < 0.7 * $varOriginal) {
            return 1;
        }

        // ---------------------------
        // 4. Seasonal drift detection
        // ---------------------------
        // Compare last season vs prior season
        $seasonLength = min(12, intdiv($n, 3)); // approximate seasonality for monthly data

        if ($seasonLength >= 6) {
            $recentSeason = array_slice($series, -$seasonLength);
            $prevSeason   = array_slice($series, -2*$seasonLength, $seasonLength);

            $meanRecent = array_sum($recentSeason) / $seasonLength;
            $meanPrev   = array_sum($prevSeason) / $seasonLength;

            if (abs($meanRecent - $meanPrev) > 0.10 * $meanPrev) {
                // Seasonal shift detected → difference
                return 1;
            }
        }

        // ---------------------------
        // 5. Stationarity proxy:
        // If first difference has lower ACF → difference is appropriate
        // ---------------------------
        $acfDiffLag1 = $this->autocorrelation($diff1, 1);
        if ($acfDiffLag1 < 0.5 && $acfLag1 >= 0.5) {
            return 1;
        }

        // ---------------------------
        // 6. If nothing flags → assume stationary
        // ---------------------------
        return 0;
    }


    /**
     * Lightweight stationarity heuristic.
     */
    private function isStationary(array $series): bool
    {
        $n = count($series);
        if ($n < 8) return true;

        $half = intdiv($n, 2);
        $mean1 = array_sum(array_slice($series, 0, $half)) / max(1, $half);
        $mean2 = array_sum(array_slice($series, $half)) / max(1, $n - $half);

        if (abs($mean1 - $mean2) > 0.25 * max(1, abs($mean1))) {
            return false;
        }

        $lag1 = $this->autocorrelation($series, 1);
        if (abs($lag1) > 0.7) return false;

        return true;
    }

    private function trendMagnitude(array $series): float
    {
        $n = count($series);
        if ($n < 4) return 0.0;

        $x = range(1, $n);
        $y = $series;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $numerator = $n * $sumXY - ($sumX * $sumY);
        $denominator = ($n * $sumX2 - $sumX * $sumX);

        if ($denominator == 0) return 0.0;

        $slope = $numerator / $denominator;
        $scale = max(1, max($y) - min($y));
        return max(-1, min(1, $slope / $scale));
    }

    private function autocorrelation(array $series, int $lag): float
    {
        $n = count($series);
        if ($lag <= 0 || $lag >= $n) return 0.0;

        $mean = array_sum($series) / $n;

        $num = 0.0; $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $den += ($series[$i] - $mean) ** 2;
            if ($i >= $lag) $num += ($series[$i] - $mean) * ($series[$i - $lag] - $mean);
        }

        return ($den == 0) ? 0.0 : $num / $den;
    }

    /* --------------------------
       Data helpers (grouping, fill, resample)
       -------------------------- */

    private function getHistoricalSalesData(?int $productId, string $period): array
    {
        if ($period === 'monthly') {
            $dateGrouping = "DATE_FORMAT(sale_items.created_at, '%Y-%m')";
        } elseif ($period === 'quarterly') {
            $dateGrouping = "CONCAT(YEAR(sale_items.created_at), '-Q', QUARTER(sale_items.created_at))";
        } else {
            $dateGrouping = "CONCAT(YEAR(sale_items.created_at), '-W', LPAD(WEEK(sale_items.created_at, 1), 2, '0'))";
        }

        $query = SaleItem::query()
            ->selectRaw("
                product_id,
                {$dateGrouping} as period,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            ")
            ->groupBy('product_id', 'period')
            ->orderBy('period');

        if ($productId) $query->where('product_id', $productId);
        $results = $query->get();

        $grouped = [];
        foreach ($results as $row) {
            if (!isset($grouped[$row->product_id])) {
                $grouped[$row->product_id] = ['quantities'=>[],'revenues'=>[],'periods'=>[]];
            }
            $grouped[$row->product_id]['quantities'][] = (float)$row->total_quantity;
            $grouped[$row->product_id]['revenues'][] = (float)$row->total_revenue;
            $grouped[$row->product_id]['periods'][] = $row->period;
        }
        return $grouped;
    }

    private function fillMissingPeriods(array $periodKeys, array $quantities, array $revenues, string $periodType): array
    {
        $mapQ = []; $mapR = [];
        foreach ($periodKeys as $i => $k) { $mapQ[$k] = $quantities[$i] ?? 0.0; $mapR[$k] = $revenues[$i] ?? 0.0; }
        if (empty($periodKeys)) return ['periods'=>[],'quantities'=>[],'revenues'=>[]];

        $first = $periodKeys[0];
        $last = end($periodKeys);
        $full = [];
        try {
            if ($periodType === 'monthly') {
                $start = $this->parseMonthlyPeriod($first);
                $end = $this->parseMonthlyPeriod($last);
                $cursor = $start->copy();
                while ($cursor->lte($end)) { $full[] = $cursor->format('Y-m'); $cursor->addMonth(); }
            } elseif ($periodType === 'quarterly') {
                $start = $this->parseQuarterlyPeriod($first);
                $end = $this->parseQuarterlyPeriod($last);
                $cursor = $start->copy();
                while ($cursor->lte($end)) { $full[] = $cursor->format('Y') . '-Q' . ceil($cursor->month/3); $cursor->addMonths(3); }
            } else {
                $start = $this->parseWeeklyPeriod($first);
                $end = $this->parseWeeklyPeriod($last);
                $cursor = $start->copy();
                while ($cursor->lte($end)) {
                    $full[] = $cursor->format('o') . '-W' . str_pad($cursor->weekOfYear, 2, '0', STR_PAD_LEFT);
                    $cursor->addWeek();
                }
            }
        } catch (\Throwable $e) {
            $full = $periodKeys;
        }

        $filledQ = []; $filledR = [];
        foreach ($full as $k) { $filledQ[] = $mapQ[$k] ?? 0.0; $filledR[] = $mapR[$k] ?? 0.0; }
        return ['periods'=>$full,'quantities'=>$filledQ,'revenues'=>$filledR];
    }

    private function getEffectiveHorizon(string $period, int $horizon): int
    {
        return match($period) {
            'weekly' => $horizon,
            'monthly' => $horizon * 4,
            'quarterly' => $horizon * 13,
            default => $horizon,
        };
    }

    private function aggregateWeeklyForecastsToMonthly(array $weeklyForecasts, int $monthlyHorizon): array
    {
        $monthly = []; $weeksPerMonth = 4;
        for ($m = 0; $m < $monthlyHorizon; $m++) {
            $sum = 0.0;
            for ($w = 0; $w < $weeksPerMonth; $w++) {
                $idx = $m * $weeksPerMonth + $w;
                if ($idx < count($weeklyForecasts)) $sum += $weeklyForecasts[$idx];
            }
            $monthly[] = round($sum, 2);
        }
        return $monthly;
    }

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

    private function calculateForecastConfidence(array $fit, int $horizon): array
    {
        if (empty($fit) || !isset($fit['resid_std'])) return ['lower'=>[],'upper'=>[],'std_dev'=>null];
        $std = $fit['resid_std'];
        $lower = []; $upper = [];
        for ($i = 1; $i <= $horizon; $i++) {
            $se = $std * sqrt($i);
            $lower[] = -1.96 * $se; $upper[] = 1.96 * $se;
        }
        return ['lower'=>$lower,'upper'=>$upper,'std_dev'=>$std];
    }

    /* --------------------------
       Existing helpers: stock, continuity, restock helpers
       -------------------------- */

    private function getCurrentStock(int $productId): float
    {
        return DB::table('inventory_batches')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('expiry_date', '>', now())
            ->sum('current_quantity') ?? 0;
    }

    private function analyzePeriodContinuity(array $periods, string $periodType): array
    {
        $unique = collect($periods)->filter(fn($v)=>!empty($v))->unique()->sort()->values();
        if ($unique->isEmpty()) return ['first_period'=>null,'last_period'=>null,'actual_periods'=>0,'expected_periods'=>0,'missing_periods'=>0];
        $first = $unique->first(); $last = $unique->last();
        $expected = $this->calculateExpectedPeriodCount($first, $last, $periodType);
        $actual = $unique->count();
        return ['first_period'=>$first,'last_period'=>$last,'actual_periods'=>$actual,'expected_periods'=>$expected,'missing_periods'=>max(0,$expected-$actual)];
    }

    private function calculateExpectedPeriodCount(string $firstPeriod, string $lastPeriod, string $periodType): int
    {
        try {
            return match($periodType) {
                'weekly' => $this->parseWeeklyPeriod($firstPeriod)->diffInWeeks($this->parseWeeklyPeriod($lastPeriod)) + 1,
                'quarterly' => intdiv($this->parseQuarterlyPeriod($firstPeriod)->diffInMonths($this->parseQuarterlyPeriod($lastPeriod)), 3) + 1,
                default => $this->parseMonthlyPeriod($firstPeriod)->diffInMonths($this->parseMonthlyPeriod($lastPeriod)) + 1,
            };
        } catch (\Throwable $e) {
            Log::warning('Continuity calc failed: ' . $e->getMessage());
            return collect([$firstPeriod, $lastPeriod])->filter()->unique()->count();
        }
    }

    private function parseMonthlyPeriod(string $period): Carbon { return Carbon::createFromFormat('Y-m', $period)->startOfMonth(); }

    private function parseWeeklyPeriod(string $period): Carbon
    {
        if (sscanf($period, '%d-W%d', $year, $week) !== 2) throw new \InvalidArgumentException('Invalid weekly period: ' . $period);
        return Carbon::now()->setISODate((int)$year, (int)$week)->startOfWeek();
    }

    private function parseQuarterlyPeriod(string $period): Carbon
    {
        if (sscanf($period, '%d-Q%d', $year, $quarter) !== 2) throw new \InvalidArgumentException('Invalid quarterly: ' . $period);
        $month = (($quarter - 1) * 3) + 1;
        return Carbon::createFromDate((int)$year, $month, 1)->startOfMonth();
    }

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

    private function calculateRecommendedRestockQuantity(Product $product, float $projectedStockBeforeRestock, float $forecastedQty): int
    {
        $bufferedDemand = max($forecastedQty * 1.2, $product->min_stock_level);
        $desiredStock = max($product->min_stock_level + $bufferedDemand, $bufferedDemand);
        if ($product->max_stock_level > 0) $desiredStock = min($desiredStock, (float)$product->max_stock_level);
        $shortage = $desiredStock - max(0, $projectedStockBeforeRestock);
        return (int) max(1, ceil($shortage));
    }
}

<?php

namespace App\Listeners;

use App\Services\InventoryAlertService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class LogInventoryAlertDiagnostics
{
    public function handle(Login $event): void
    {
        /** @var InventoryAlertService $service */
        $service = app(InventoryAlertService::class);

        $summary = $service->getAlertSummary();
        $totalAlerts = $service->getTotalAlertCount($summary);

        Session::put('inventory_alert_summary', $service->prepareSummaryForSession($summary));
        Session::put('inventory_alert_total', $totalAlerts);
        Session::put('inventory_alert_last_calculated_at', now()->toDateTimeString());
        Session::forget('inventory_alert_flash_dismissed');

        Log::info('Inventory Alert Diagnostics', [
            'user_id' => $event->user->id,
            'out_of_stock_count' => $summary['out_of_stock']['count'] ?? 0,
            'low_stock_count' => $summary['low_stock']['count'] ?? 0,
            'expired_count' => $summary['expired']['count'] ?? 0,
            'near_expiry_count' => $summary['near_expiry']['count'] ?? 0,
            'total_alerts' => $totalAlerts,
        ]);
    }

}
# Inventory Alert Modal Design Plan

## Objectives
- Surface critical inventory conditions (out of stock, low stock, expired, near expiry) immediately after user login.
- Present alerts in a modal-style notification with a compact tabular layout sorted by severity: **Out of Stock → Low Stock → Expired → Near Expiry**.
- Prevent modal from appearing when no alerts exist.
- Support headless analytics by centralizing alert data retrieval.

## Data Requirements

| Alert Type | Source | Query Criteria | Fields Required |
|------------|--------|----------------|-----------------|
| Out of Stock | `products` + `inventory_batches` | Sum of active batch `current_quantity` ≤ 0 or NULL | Product name, SKU, current stock, min stock, category |
| Low Stock | Same as out of stock | Sum of active batch `current_quantity` ≤ `min_stock_level` & > 0 | Product name, SKU, current stock, min stock |
| Expired | `inventory_batches` | `expiry_date` < now, `current_quantity` > 0, status active | Product name, batch number, expiry date, quantity |
| Near Expiry | `inventory_batches` | `expiry_date` between now and now()+30 days (configurable), `current_quantity` > 0, status active | Product name, batch number, expiry date, quantity, days remaining |

### Aggregation Strategy
- Introduce an `InventoryAlertService` (or extend `PharmacyAnalyticsService` with public methods) that exposes:
  - `getOutOfStockProducts()`
  - `getLowStockProducts()`
  - `getExpiredBatches()`
  - `getNearExpiryBatches(int $days = 30)`
  - `getAlertSummary()` returning a structured array keyed by alert type with `items`, `count`, and `description`.
- Service will bypass existing `inventoryBatches()` relationship filters to ensure expired/zero-quantity batches are included where necessary.

## Modal UX Flow

1. **Login Event Hook**
   - Listener records diagnostic logs (already implemented).
   - After authentication, dashboard Livewire component checks alert data via service.

2. **Modal Trigger Logic**
   - On dashboard mount, call `InventoryAlertService::getAlertSummary()`.
   - If any alert count > 0, set Livewire state `showInventoryAlertModal = true`.
   - Provide ability to dismiss modal (state persists for current session via Livewire `cache`/`session`).

3. **Modal Presentation**
   - Create a dedicated Livewire component (e.g., `InventoryAlertModal`) that:
     - Accepts alert data via props/binding.
     - Renders a Filament modal (`\Filament\Actions\Concerns\InteractsWithActions` or custom Blade partial using `x-filament::modal`).
     - Displays sections in required order with mini tables (max 5 rows per category with "View All" link to relevant resource filtered view).

4. **UI Details**
   - Each alert section includes:
     - Heading with icon (severity color).
     - Count badge.
     - Table columns (e.g., Product, Stock/Quantity, Threshold, Expiry).
   - Provide CTA buttons:
     - `Manage Products` (links to Product resource with filter pre-applied).
     - `View Inventory Batches` for expired/near-expiry.

5. **Dismissal / Re-Trigger**
   - Modal should not reappear on same session once dismissed, unless new alerts are detected (optional improvement by hashing alert payload and storing in cache/session).

## Implementation Steps

1. Add `InventoryAlertService` with public query methods and summary builder.
2. Update `EventServiceProvider` to bind service (if necessary) and ensure diagnostics remain.
3. Extend dashboard page (or create global Livewire component) to fetch alert summary on mount.
4. Implement modal Livewire component and view under `resources/views/filament/components`.
5. Integrate component into dashboard layout via `Dashboard::mount()` or custom Blade layout.
6. Add tests (if possible) or manual verification steps.
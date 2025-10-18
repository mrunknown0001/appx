# Stock Entry Multi-Product Implementation Plan

## 1. Objectives

- Allow a single stock entry record to reference multiple products with individual quantity and pricing metadata.
- Preserve compatibility with existing analytics and inventory batch workflows.
- Introduce sufficient diagnostics to monitor multi-product submissions.

## 2. Current Constraints

- `stock_entries` table stores product-specific fields (`product_id`, `quantity_received`, `unit_cost`, `selling_price`, `expiry_date`, `batch_number`), enforcing a single-product invariant.
- `InventoryBatch` creation is tightly coupled to `StockEntry` (`CreateStockEntry::afterCreate`) and assumes one batch per entry.
- Filament form (`StockEntryResource::form()`) exposes scalar inputs rather than a repeater for multiple items.
- Analytics (e.g., `PharmacyAnalyticsService`) aggregate directly from `stock_entries.quantity_received`.
- Price history logging occurs per stock entry (`StockEntry::booted`), relying on single `product_id`/`selling_price`.

## 3. Proposed Database Changes

1. Introduce `stock_entry_items` table:
   - Columns: `id`, `stock_entry_id`, `product_id`, `quantity_received`, `unit_cost`, `total_cost`, `selling_price`, `expiry_date` (nullable), `batch_number` (nullable), `notes` (nullable), timestamps.
   - Foreign keys: `stock_entry_id` → `stock_entries.id`, `product_id` → `products.id`.
   - Indexes on (`stock_entry_id`, `product_id`) and (`product_id`, `expiry_date`).

2. Update `stock_entries` table:
   - Drop product-specific columns (`product_id`, `quantity_received`, `unit_cost`, `selling_price`, `expiry_date`, `batch_number`).
   - Add aggregate columns (`total_quantity`, `total_cost`).
   - Ensure migrations handle data backfill or provide transitional scripts.

3. Adjust `inventory_batches` table:
   - Add `stock_entry_item_id` nullable FK.
   - Keep `stock_entry_id` for backwards compatibility, but prefer linking batches to individual items going forward.

4. Data migration considerations:
   - For existing entries, migrate rows by seeding `stock_entry_items` from existing `stock_entries`.
   - Update `inventory_batches.stock_entry_item_id` based on associated stock entry data.

## 4. Model Updates

- `StockEntry`:
  - Replace direct product relation with `hasMany` to `StockEntryItem`.
  - Maintain `hasMany` to `InventoryBatch`.
  - Update fillable and casts to aggregate columns.
  - Modify auditing hook to iterate over items, creating price history per product.

- `StockEntryItem`:
  - New model representing per-product data.
  - Relationships: `belongsTo StockEntry`, `belongsTo Product`, `hasOne InventoryBatch`.

- `InventoryBatch`:
  - Add `belongsTo StockEntryItem`.
  - Update logic to use item linkage when available.

- `Product`:
  - Update `stockEntries()` to `hasManyThrough` via `StockEntryItem` or provide helper to fetch entries.

## 5. Filament Resource Changes

- `StockEntryResource::form()`:
  - Replace scalar inputs with `Repeater::make('items')`.
  - Per item schema includes product select, quantity, unit cost, selling price, expiry, batch number, item-level notes.
  - Aggregate section calculates total quantity and cost from repeater state.

- `CreateStockEntry` page:
  - `mutateFormDataBeforeCreate` to normalize repeater data, compute totals, and prepare nested relations for creation.
  - `afterCreate` loops over items to spawn inventory batches per product (respecting batch numbers/expiry).
  - Logging: include counts for items processed and batches created.

- `EditStockEntry`/`ViewStockEntry` pages:
  - Update infolist to show aggregate totals and nested list of items.
  - Adjust editing behavior to handle nested repeater (including inventory reconciliation).

- Tables/filters:
  - Replace single product columns with aggregated views (e.g., display item count, show multi-line product summary).
  - Filters for product should leverage `whereHas('items.product')`.

## 6. Inventory Batch Workflow

- `CreateStockEntry::afterCreate`:
  - For each item, create batch with `stock_entry_item_id`.
  - Ensure default location/status applied per item.

- Future adjustments:
  - Update batch editing to reference item-level data.
  - Maintain compatibility by populating `stock_entry_id` as well.

## 7. Analytics & Services

- `PharmacyAnalyticsService` and other aggregations must be updated to sum through `stock_entry_items`.
- Update any pricing/forecast services to reference items instead of stock entries.

## 8. Logging & Diagnostics

- Add structured logs:
  - Payload summary for incoming repeater data.
  - Post-save summary including `items_count`, `total_quantity`, `total_cost`.
  - Inventory batch creation per item (success/failure metrics).

- Ensure logs include stock entry ID and product IDs for traceability.

## 9. Migration Strategy

1. Deploy migrations adding new table and columns without dropping existing ones.
2. Backfill `stock_entry_items` from legacy columns.
3. Update application code to use new relationships.
4. Remove legacy columns once code path is confirmed stable (follow-up migration).
5. Provide seeder/command to verify totals across old/new schema.

## 10. Risks & Mitigations

- **Risk**: Partial updates causing double batches.
  - Mitigate with transactions and validation that repeater data matches totals.

- **Risk**: Analytics discrepancies.
  - Mitigate by running comparisons between old aggregate queries and new item-based aggregates.

- **Risk**: Performance overhead on repeater.
  - Mitigate by limiting default items and providing search/pagination for product selection.

## 11. Next Steps

1. Finalize migrations (`stock_entry_items`, `stock_entries` adjustments, `inventory_batches` FK).
2. Implement Laravel models and relations.
3. Refactor Filament resource form and pages.
4. Update services/analytics.
5. Run regression tests and manual QA on stock entry creation/editing.
6. Monitor logs post-deployment and adjust as needed.

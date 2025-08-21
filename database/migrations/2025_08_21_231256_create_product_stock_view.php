<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('
            CREATE VIEW product_stocks AS
            SELECT 
                p.id as product_id,
                p.name,
                p.sku,
                p.min_stock_level,
                p.max_stock_level,
                p.is_active,
                COALESCE(SUM(ib.current_quantity), 0) as current_stock,
                CASE 
                    WHEN COALESCE(SUM(ib.current_quantity), 0) = 0 THEN "out_of_stock"
                    WHEN COALESCE(SUM(ib.current_quantity), 0) <= p.min_stock_level THEN "low_stock"
                    WHEN COALESCE(SUM(ib.current_quantity), 0) >= p.max_stock_level THEN "overstock"
                    ELSE "in_stock"
                END as stock_status
            FROM products p
            LEFT JOIN inventory_batches ib ON p.id = ib.product_id 
                AND ib.expiry_date > NOW() 
                AND ib.status = "active"
                AND ib.current_quantity > 0
            WHERE p.deleted_at IS NULL
            GROUP BY p.id, p.name, p.sku, p.min_stock_level, p.max_stock_level, p.is_active
        ');
    }

    public function down()
    {
        DB::statement('DROP VIEW IF EXISTS product_stocks');
    }
};
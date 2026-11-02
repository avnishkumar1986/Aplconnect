<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM information_schema.VIEWS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vw_procurement_purchase_receipts'
        ");

        if ($exists->total > 0) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE ALGORITHM=UNDEFINED
VIEW `vw_procurement_purchase_receipts` AS

SELECT
    CAST(purchase.id AS SIGNED) AS id,
    purchase.id AS source_id,
    CONVERT('local' USING utf8mb4)
        COLLATE utf8mb4_unicode_ci AS source,
    0 AS is_api,
    purchase.company_id,
    company.company_code,
    purchase.plant_id,
    plant.company_code AS plant_code,
    purchase.consumption_id,
    NULL AS vendor_code,
    purchase.vendor_name,
    purchase.material,
    purchase.purchase_date,
    purchase.quantity_mt,
    purchase.expected_delivery_date,
    purchase.delivery_status,
    COALESCE(
        NULLIF(purchase.reference, ''),
        CONCAT('MANUAL-', purchase.id)
    ) AS reference,
    purchase.remarks,
    purchase.status,
    purchase.created_at,
    purchase.updated_at,
    purchase.quantity_mt AS total_quantity_mt,
    0 AS reduced_quantity_mt,
    purchase.quantity_mt AS balance_quantity_mt,
    NULL AS rate,
    NULL AS amount,
    NULL AS currency

FROM procurement_purchases AS purchase

LEFT JOIN tbl_company AS company
    ON company.id = purchase.company_id

LEFT JOIN tbl_company AS plant
    ON plant.id = purchase.plant_id

UNION ALL

SELECT
    -CAST(po.id AS SIGNED) AS id,
    po.id AS source_id,
    CONVERT('sap' USING utf8mb4)
        COLLATE utf8mb4_unicode_ci AS source,
    1 AS is_api,
    company.id AS company_id,
    po.company_code,
    plant.id AS plant_id,
    po.plant_code,
    NULL AS consumption_id,
    po.vendor_code,
    po.vendor_name,
    po.material,
    po.order_date AS purchase_date,

    CASE
        WHEN po.delivery_status = 'received'
        THEN po.received_quantity_mt
        ELSE po.quantity_mt
    END AS quantity_mt,

    po.expected_delivery_date,
    po.delivery_status,

    CONCAT(
        po.po_number,
        '/',
        po.item_number
    ) AS reference,

    CONCAT(
        'SAP PO ',
        po.po_number,
        '/',
        po.item_number
    ) AS remarks,

    po.status,
    po.created_at,
    po.updated_at,
    po.quantity_mt AS total_quantity_mt,
    po.received_quantity_mt AS reduced_quantity_mt,

    GREATEST(
        po.quantity_mt - po.received_quantity_mt,
        0
    ) AS balance_quantity_mt,

    po.rate,
    po.amount,
    po.currency

FROM tbl_vendor_purchase_orders AS po

LEFT JOIN tbl_company AS company
    ON company.company_code = po.company_code
    AND company.record_type = 1

LEFT JOIN tbl_company AS plant
    ON plant.company_code = po.plant_code
    AND plant.record_type IN (2, 3)
SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP VIEW IF EXISTS `vw_procurement_purchase_receipts`'
        );
    }
};

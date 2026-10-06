<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyMasterSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [1, '1000', 'Test 1', 1, null, 1, 1, 1, '2026-09-17 22:35:38', 1, '2026-09-18 04:57:37', 1],
            [2, '5000', 'Test 2', 1, null, 2, 2, 1, '2026-09-17 22:37:21', 1, '2026-09-18 04:57:37', 1],
            [3, '1200', 'Dadri Plant', 2, 1, 3, 3, 1, '2026-09-17 22:39:40', 1, '2026-09-18 04:57:37', 1],
            [4, '1300', 'Sikandrabad Plant', 2, 1, 4, 4, 1, '2026-09-17 22:43:59', 1, '2026-09-18 04:57:37', 1],
            [5, '1400', 'Ahmedabad plant', 2, 1, 5, 5, 1, '2026-09-17 22:50:43', 1, '2026-09-18 04:57:37', 1],
            [9, '1600', 'Tumkur Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [10, '1700', 'Raipur Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [11, '1500', 'Head Office', 3, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [12, '0', 'Other Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', 1],
            [13, '1900', 'Chunar Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [14, '3200', 'Boisar Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [15, '3300', 'Ludhiana Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [16, '6800', 'Ludhiana Plant-2', 2, 2, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-23 16:14:28', null],
            [17, '5300', 'Silvassa Plant', 2, 2, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [18, '5200', 'Tarapur Plant', 2, 2, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
            [19, '6600', 'KML-Warehouse Plant', 2, 1, null, null, 1, '2026-09-18 04:31:08', null, '2026-09-18 04:57:37', null],
        ];

        DB::transaction(function () use ($rows) {
            foreach ($rows as [$id, $code, $name, $type, $parentCode, $addressId, $contactId, $status, $createdAt, $createdBy, $updatedAt, $updatedBy]) {
                DB::table('tbl_company')->updateOrInsert(
                    ['company_code' => $code],
                    [
                        'id' => $id,
                        'company_name' => $name,
                        'record_type' => $type,
                        'parent_company_id' => $parentCode ? (['1' => '1000', '2' => '5000'][(string) $parentCode] ?? $parentCode) : null,
                        'address_id' => $addressId,
                        'contact_id' => $contactId,
                        'Status' => $status,
                        'Created_at' => $createdAt,
                        'Created_by' => $createdBy,
                        'Updated_at' => $updatedAt,
                        'Updated_by' => $updatedBy,
                    ]
                );

                $companyId = DB::table('tbl_company')->where('company_code', $code)->value('id');
                if ($contactId) DB::table('tbl_contacts')->where('id', $contactId)->update(['company_code' => $companyId]);
                if ($addressId) DB::table('tbl_addresses')->where('id', $addressId)->update(['company_code' => $companyId]);
            }
        });
    }
}

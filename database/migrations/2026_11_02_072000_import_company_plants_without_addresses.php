<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $records = [
            ['1100', 'Delhi', 2, '1000'],
            ['1200', 'Dadri Unit1', 2, '1000'],
            ['1300', 'Sikandrabad', 2, '1000'],
            ['1400', 'Ahmedabad', 2, '1000'],
            ['1500', 'Noida', 3, '1000'],
            ['1600', 'Tumkur', 2, '1000'],
            ['1700', 'Raipur', 2, '1000'],
            ['1800', 'Dadri Unit2', 2, '1000'],
            ['1900', 'Varanasi', 2, '1000'],
            ['2100', 'Dadri-2100', 2, '1000'],
            ['3100', 'PUNE', 2, '1000'],
            ['3200', 'PALGHAR', 2, '1000'],
            ['3300', 'Ludhiana Plant', 2, '1000'],
            ['5100', 'MUMBAI HEAD OFFICE', 3, '5000'],
            ['5200', 'MAHAGAON PLANT', 2, '5000'],
            ['5300', 'SILVASSA KHADLI PLANT', 2, '5000'],
            ['5400', 'DEWAS PLANT', 2, '5000'],
            ['6000', 'DEWAS-INDORE WAREHOUSE', 2, '5000'],
            ['6100', 'JAIPUR-GODOWN WAREHOUSE', 2, '5000'],
            ['6200', 'MOHALI OFFICE CUM WAREHOUSE', 2, '5000'],
            ['6300', 'NASHIK STOCK FOR BILLING', 2, '5000'],
            ['6400', 'C&F-GODOWN AMRAVATI', 2, '5000'],
            ['6500', 'C&F-GODOWN WASHIM', 2, '5000'],
            ['6600', 'DADRI-UP WAREHOUSE', 2, '5000'],
            ['6700', 'GODOWN-TUMKUR-KA', 2, '5000'],
            ['6800', 'GODOWN-LUDHIANA', 2, '5000'],
        ];

        DB::table('tbl_company')->insertOrIgnore(
            array_map(
                fn (array $record): array => [
                    'company_code' => $record[0],
                    'company_name' => $record[1],
                    'record_type' => $record[2],
                    'parent_company_id' => $record[3],
                    'address_id' => null,
                    'contact_id' => null,
                    'gstin' => null,
                    'pan' => null,
                    'status' => true,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $records,
            ),
        );
    }

    public function down(): void
    {
        // This reference-data import intentionally preserves company records on rollback.
    }
};

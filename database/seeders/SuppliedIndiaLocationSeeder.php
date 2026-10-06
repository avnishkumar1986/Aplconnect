<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuppliedIndiaLocationSeeder extends Seeder
{
    public function run(): void
    {
        $this->importDistricts(storage_path('app/imports/india-districts.tsv'));
        $this->importCities(storage_path('app/imports/india-city-pincodes.tsv'));
    }

    private function importDistricts(string $path): void
    {
        $handle = $this->open($path);
        fgetcsv($handle, 0, "\t");
        DB::table('tbl_india_districts')->truncate();
        $batch = [];
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) < 3 || ! is_numeric(trim($row[0]))) continue;
            $batch[] = ['id' => (int) trim($row[0]), 'state' => trim($row[1]), 'district_name' => trim($row[2])];
            if (count($batch) === 500) { DB::table('tbl_india_districts')->insertOrIgnore($batch); $batch = []; }
        }
        if ($batch) DB::table('tbl_india_districts')->insertOrIgnore($batch);
        fclose($handle);
    }

    private function importCities(string $path): void
    {
        $handle = $this->open($path);
        fgetcsv($handle, 0, "\t");
        DB::table('tbl_india_city_pincodes')->truncate();
        $batch = [];
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) < 3 || ! is_numeric(trim($row[0]))) continue;
            $batch[] = ['id' => (int) trim($row[0]), 'city' => trim($row[1]), 'pin_code' => trim($row[2])];
            if (count($batch) === 500) { DB::table('tbl_india_city_pincodes')->insertOrIgnore($batch); $batch = []; }
        }
        if ($batch) DB::table('tbl_india_city_pincodes')->insertOrIgnore($batch);
        fclose($handle);
    }

    private function open(string $path)
    {
        if (! is_file($path)) throw new RuntimeException("Import file not found: {$path}");
        return fopen($path, 'rb');
    }
}

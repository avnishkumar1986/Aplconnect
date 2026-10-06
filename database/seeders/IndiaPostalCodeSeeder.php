<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IndiaPostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/india-pincodes.csv');
        if (! is_file($path)) throw new RuntimeException("India postal CSV not found at {$path}");

        $handle = fopen($path, 'rb');
        $header = array_map(fn ($value) => strtolower(trim($value)), fgetcsv($handle));
        $columns = array_flip($header);
        $batch = [];
        DB::table('tbl_india_postal_codes')->truncate();

        while (($row = fgetcsv($handle)) !== false) {
            $state = trim($row[$columns['statename']] ?? '');
            $district = trim($row[$columns['district']] ?? '');
            $city = trim($row[$columns['officename']] ?? '');
            $postalCode = trim($row[$columns['pincode']] ?? '');
            if ($state === '' || $district === '' || $city === '' || ! preg_match('/^\d{6}$/', $postalCode)) continue;
            $batch[$state.'|'.$district.'|'.$city.'|'.$postalCode] = compact('state', 'district', 'city', 'postalCode');
            if (count($batch) >= 1000) {
                $this->insert($batch);
                $batch = [];
            }
        }
        if ($batch) $this->insert($batch);
        fclose($handle);
    }

    private function insert(array $batch): void
    {
        DB::table('tbl_india_postal_codes')->insertOrIgnore(array_map(fn ($row) => [
            'state' => $row['state'],
            'district' => $row['district'],
            'city' => $row['city'],
            'postal_code' => $row['postalCode'],
        ], array_values($batch)));
    }
}

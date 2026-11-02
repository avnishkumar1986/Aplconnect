<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE_DATABASE = 'apl_apollo';

    public function up(): void
    {
        if (! Schema::hasTable('tbl_india_districts')) {
            Schema::create('tbl_india_districts', function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->string('state', 100)->index();
                $table->string('district_name', 150);
                $table->unique(['state', 'district_name'], 'india_district_state_name_unique');
            });
        }

        if (! Schema::hasTable('tbl_india_city_pincodes')) {
            Schema::create('tbl_india_city_pincodes', function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->string('city', 180)->index();
                $table->string('pin_code', 12)->index();
                $table->unique(['city', 'pin_code'], 'india_city_pin_unique');
            });
        }

        $this->copyReferenceData('tbl_india_districts', ['id', 'state', 'district_name']);
        $this->copyReferenceData('tbl_india_city_pincodes', ['id', 'city', 'pin_code']);
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_india_city_pincodes');
        Schema::dropIfExists('tbl_india_districts');
    }

    /** @param array<int, string> $columns */
    private function copyReferenceData(string $table, array $columns): void
    {
        if (DB::table($table)->exists()) {
            return;
        }

        $sourceExists = DB::table('information_schema.tables')
            ->where('table_schema', self::SOURCE_DATABASE)
            ->where('table_name', $table)
            ->exists();

        if (! $sourceExists) {
            return;
        }

        DB::table(self::SOURCE_DATABASE.'.'.$table)
            ->select($columns)
            ->orderBy('id')
            ->chunk(1000, fn ($rows) => DB::table($table)->insert(
                $rows->map(fn ($row) => (array) $row)->all()
            ));
    }
};

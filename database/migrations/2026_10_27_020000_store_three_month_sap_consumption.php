<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_material_stocks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tbl_material_stocks', 'sap_current_month_consumption')) {
                $table->decimal('sap_current_month_consumption', 20, 3)->default(0)->after('stock_value');
            }
            if (! Schema::hasColumn('tbl_material_stocks', 'sap_previous_month_consumption')) {
                $table->decimal('sap_previous_month_consumption', 20, 3)->default(0)->after('sap_current_month_consumption');
            }
            if (! Schema::hasColumn('tbl_material_stocks', 'sap_two_months_ago_consumption')) {
                $table->decimal('sap_two_months_ago_consumption', 20, 3)->default(0)->after('sap_previous_month_consumption');
            }
            if (! Schema::hasColumn('tbl_material_stocks', 'auto_monthly_consumption')) {
                $table->decimal('auto_monthly_consumption', 20, 3)->default(0)->after('sap_two_months_ago_consumption');
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'sap_current_month_consumption',
            'sap_previous_month_consumption',
            'sap_two_months_ago_consumption',
            'auto_monthly_consumption',
        ])->filter(fn (string $column): bool => Schema::hasColumn('tbl_material_stocks', $column))->all();

        if ($columns !== []) {
            Schema::table('tbl_material_stocks', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};

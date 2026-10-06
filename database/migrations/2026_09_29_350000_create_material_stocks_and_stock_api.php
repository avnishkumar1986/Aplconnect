<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('matnr', 50);
            $table->string('plant_code', 20);
            $table->string('material_desc', 255)->nullable();
            $table->decimal('current_stock', 20, 3)->default(0);
            $table->decimal('stock_value', 20, 3)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['matnr', 'plant_code'], 'material_stocks_matnr_plant_unique');
            $table->index('plant_code');
        });

        DB::table('api_integrations')->updateOrInsert(
            ['code' => 'stock'],
            [
                'name' => 'Stock API',
                'endpoint_path' => '/sap/opu/odata/sap/ZMAT_STOCK_CDS/ZMAT_STOCK',
                'tbl_name' => 'tbl_material_stocks',
                'http_method' => 'GET',
                'auth_type' => 'basic',
                'credential_env_key' => 'PROCUREMENT_STOCK_API_TOKEN',
                'username_env_key' => 'API_AUTH_USERNAME',
                'password_env_key' => 'API_AUTH_PASSWORD',
                'timeout_seconds' => 1800,
                'retry_count' => 1,
                'retry_delay_seconds' => 5,
                'verify_ssl' => true,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('api_integrations')->where('code', 'stock')->delete();
        Schema::dropIfExists('tbl_material_stocks');
    }
};

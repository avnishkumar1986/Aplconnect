<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('base_url', 500)->nullable();
            $table->string('endpoint_path', 500)->nullable();
            $table->string('http_method', 10)->default('GET');
            $table->string('auth_type', 30)->default('none');
            $table->string('credential_env_key', 100)->nullable();
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedTinyInteger('retry_count')->default(3);
            $table->unsignedInteger('retry_delay_seconds')->default(10);
            $table->boolean('verify_ssl')->default(true);
            $table->boolean('status')->default(false);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('api_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_integration_id')->constrained('api_integrations')->cascadeOnDelete();
            $table->uuid('run_uuid')->unique();
            $table->string('status', 20)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_saved')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('tbl_login')->nullOnDelete();
            $table->timestamps();
            $table->index(['api_integration_id', 'started_at']);
        });

        Schema::create('api_sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_sync_run_id')->constrained('api_sync_runs')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('request_url', 1000);
            $table->string('request_method', 10);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 20);
            $table->string('error_type', 150)->nullable();
            $table->text('error_message')->nullable();
            $table->string('exception_class')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
            $table->index(['api_sync_run_id', 'attempt_number']);
            $table->index(['status', 'attempted_at']);
        });

        Schema::create('api_sync_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_sync_run_id')->constrained('api_sync_runs')->cascadeOnDelete();
            $table->string('external_key', 191)->nullable();
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['api_sync_run_id', 'payload_hash']);
            $table->index('external_key');
        });

        $now = now();
        DB::table('api_integrations')->insert([
            [
                'code' => 'quality',
                'name' => 'Quality API',
                'base_url' => 'https://103.186.48.158:44321',
                'endpoint_path' => null,
                'http_method' => 'GET',
                'auth_type' => 'bearer',
                'credential_env_key' => 'QUALITY_API_TOKEN',
                'timeout_seconds' => 30,
                'verify_ssl' => true,
                'status' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'production',
                'name' => 'Production API',
                'base_url' => 'https://fiori.apollopipes.com:44301',
                'endpoint_path' => null,
                'http_method' => 'GET',
                'auth_type' => 'bearer',
                'credential_env_key' => 'PRODUCTION_API_TOKEN',
                'timeout_seconds' => 30,
                'verify_ssl' => true,
                'status' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'stock',
                'name' => 'Procurement Stock API',
                'base_url' => 'https://103.186.48.158:44321',
                'endpoint_path' => '/sap/opu/odata/sap/ZMAT_STOCK_CDS/ZMAT_STOCK',
                'http_method' => 'GET',
                'auth_type' => 'bearer',
                'credential_env_key' => 'PROCUREMENT_STOCK_API_TOKEN',
                'timeout_seconds' => 30,
                'verify_ssl' => true,
                'status' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        foreach (['api-integrations.view', 'api-integrations.create', 'api-integrations.edit', 'api-integrations.delete'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $superAdminRoleId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($superAdminRoleId) {
            DB::table('permissions')->where('name', 'like', 'api-integrations.%')->pluck('id')->each(
                fn ($permissionId) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $superAdminRoleId])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_payloads');
        Schema::dropIfExists('api_sync_attempts');
        Schema::dropIfExists('api_sync_runs');
        Schema::dropIfExists('api_integrations');
    }
};

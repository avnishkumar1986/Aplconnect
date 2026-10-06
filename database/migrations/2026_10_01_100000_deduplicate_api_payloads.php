<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('api_sync_payloads', 'api_integration_id')) {
            Schema::table('api_sync_payloads', function (Blueprint $table) {
                $table->unsignedBigInteger('api_integration_id')->nullable()->after('api_sync_run_id');
            });
        }

        DB::statement('UPDATE api_sync_payloads payload JOIN api_sync_runs run ON run.id = payload.api_sync_run_id SET payload.api_integration_id = run.api_integration_id');
        DB::statement('DELETE duplicate FROM api_sync_payloads duplicate JOIN api_sync_payloads keeper ON keeper.api_integration_id = duplicate.api_integration_id AND keeper.payload_hash = duplicate.payload_hash AND keeper.id < duplicate.id');

        $indexNames = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'api_sync_payloads')->pluck('INDEX_NAME')->unique();

        if (! $indexNames->contains('api_sync_payloads_run_id_index')) {
            DB::statement('ALTER TABLE api_sync_payloads ADD INDEX api_sync_payloads_run_id_index (api_sync_run_id)');
        }
        if ($indexNames->contains('api_sync_payloads_api_sync_run_id_payload_hash_unique')) {
            DB::statement('ALTER TABLE api_sync_payloads DROP INDEX api_sync_payloads_api_sync_run_id_payload_hash_unique');
        }
        if (! $indexNames->contains('api_payload_integration_hash_unique')) {
            DB::statement('ALTER TABLE api_sync_payloads ADD UNIQUE INDEX api_payload_integration_hash_unique (api_integration_id, payload_hash)');
        }

        $foreignExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'api_sync_payloads')
            ->where('CONSTRAINT_NAME', 'api_sync_payloads_api_integration_id_foreign')->exists();
        if (! $foreignExists) {
            DB::statement('ALTER TABLE api_sync_payloads ADD CONSTRAINT api_sync_payloads_api_integration_id_foreign FOREIGN KEY (api_integration_id) REFERENCES api_integrations(id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        Schema::table('api_sync_payloads', function (Blueprint $table) {
            $table->dropForeign(['api_integration_id']);
            $table->dropUnique('api_payload_integration_hash_unique');
            $table->unique(['api_sync_run_id', 'payload_hash']);
            $table->dropIndex('api_sync_payloads_run_id_index');
            $table->dropColumn('api_integration_id');
        });
    }
};

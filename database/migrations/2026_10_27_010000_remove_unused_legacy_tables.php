<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['jobs', 'failed_jobs', 'procurement_plan_histories'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rowCount = DB::table($table)->count();
            if ($rowCount > 0) {
                throw new RuntimeException(
                    "Refusing to drop {$table} because it contains {$rowCount} row(s)."
                );
            }

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('procurement_plan_histories')) {
            Schema::create('procurement_plan_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consumption_id')->index();
                $table->unsignedInteger('version');
                $table->json('snapshot');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at');
                $table->unique(['consumption_id', 'version']);
            });
        }
    }
};

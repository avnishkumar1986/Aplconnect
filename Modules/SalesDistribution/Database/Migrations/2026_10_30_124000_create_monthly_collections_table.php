<?php

use App\Services\MonthlyCollectionSummaryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_monthly_collections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('collection_year');
            $table->unsignedTinyInteger('collection_month');
            $table->string('month_name', 9);
            $table->string('company_code', 20)->default('ALL');
            $table->decimal('collection_amount', 20, 2)->default(0);
            $table->decimal('collection_crore', 20, 2)->default(0);
            $table->unsignedBigInteger('source_customers')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['collection_year', 'collection_month', 'company_code'], 'monthly_collections_period_company_unique');
            $table->index(['collection_year', 'collection_month'], 'monthly_collections_period_index');
        });

        app(MonthlyCollectionSummaryService::class)->refresh();
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_monthly_collections');
    }
};

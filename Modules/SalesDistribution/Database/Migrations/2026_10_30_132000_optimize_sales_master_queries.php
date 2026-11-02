<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('tbl_pending_sales',fn(Blueprint $t)=>$t->index(['document_date','id'],'pending_sales_page_sort_index'));
  Schema::table('tbl_dailysale',fn(Blueprint $t)=>$t->index(['billing_date','id'],'daily_sales_page_sort_index'));
  Schema::table('tbl_customer_sales_summary',fn(Blueprint $t)=>$t->index(['synced_at','id'],'customer_summary_page_sort_index'));
  Schema::table('tbl_customer_collection_transactions',fn(Blueprint $t)=>$t->index(['posting_date','id'],'collections_page_sort_index'));
 }
 public function down(): void {
  Schema::table('tbl_pending_sales',fn(Blueprint $t)=>$t->dropIndex('pending_sales_page_sort_index'));
  Schema::table('tbl_dailysale',fn(Blueprint $t)=>$t->dropIndex('daily_sales_page_sort_index'));
  Schema::table('tbl_customer_sales_summary',fn(Blueprint $t)=>$t->dropIndex('customer_summary_page_sort_index'));
  Schema::table('tbl_customer_collection_transactions',fn(Blueprint $t)=>$t->dropIndex('collections_page_sort_index'));
 }
};

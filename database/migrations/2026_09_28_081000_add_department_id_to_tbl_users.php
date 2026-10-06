<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { if (! Schema::hasColumn('tbl_users', 'department_id')) { Schema::table('tbl_users', function (Blueprint $table) { $table->foreignId('department_id')->nullable()->after('designation_id')->constrained('tbl_departments')->nullOnDelete(); }); } }
    public function down(): void { if (Schema::hasColumn('tbl_users', 'department_id')) { Schema::table('tbl_users', function (Blueprint $table) { $table->dropConstrainedForeignId('department_id'); }); } }
};

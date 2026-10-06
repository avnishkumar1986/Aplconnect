<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_users')) return;
        if (Schema::hasTable('tbl_contacts')) DB::statement('UPDATE tbl_contacts c JOIN tbl_users u ON c.employee_id = u.reference_id SET c.employee_id = CAST(u.id AS CHAR)');
        if (Schema::hasTable('tbl_addresses')) DB::statement('UPDATE tbl_addresses a JOIN tbl_users u ON a.employee_id = u.reference_id SET a.employee_id = CAST(u.id AS CHAR)');
        if (Schema::hasColumn('tbl_users', 'user_type_code')) {
            foreach (['fk_users_user_type', 'tbl_users_user_type_code_foreign'] as $constraint) {
                $exists = DB::selectOne("SELECT COUNT(*) count FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND CONSTRAINT_NAME = ?", [$constraint]);
                if ((int) $exists->count > 0) DB::statement("ALTER TABLE tbl_users DROP FOREIGN KEY `$constraint`");
            }
        }
        foreach (['uq_user_type_reference', 'tbl_users_emp_id_unique'] as $index) {
            $exists = DB::selectOne("SELECT COUNT(*) count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND INDEX_NAME = ?", [$index]);
            if ((int) $exists->count > 0) DB::statement("ALTER TABLE tbl_users DROP INDEX `$index`");
        }
        $columns = array_values(array_filter(['reference_id', 'user_type_code'], fn ($column) => Schema::hasColumn('tbl_users', $column)));
        if ($columns) Schema::table('tbl_users', fn ($table) => $table->dropColumn($columns));
    }

    public function down(): void
    {
        Schema::table('tbl_users', function ($table) {
            $table->string('reference_id', 50)->nullable()->after('id');
            $table->unsignedBigInteger('user_type_code')->nullable()->after('reference_id');
        });
    }
};

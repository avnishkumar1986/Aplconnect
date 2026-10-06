<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('tbl_users', 'type_code')) {
                $table->string('type_code', 30)->nullable()->after('id');
            }
            if (! Schema::hasColumn('tbl_users', 'code')) {
                $table->unsignedBigInteger('code')->nullable()->after('type_code');
            }
        });

        if (Schema::hasTable('tbl_user_type_map')) {
            DB::statement(<<<'SQL'
                UPDATE tbl_users AS users
                INNER JOIN tbl_user_type_map AS mapping ON mapping.user_id = users.id
                SET users.type_code = mapping.type_code
                WHERE users.type_code IS NULL OR users.type_code = ''
            SQL);
        }

        $fallbackType = DB::table('tbl_usertypes')
            ->where('status', '1')
            ->orderBy('id')
            ->value('type_code');

        if ($fallbackType) {
            DB::table('tbl_users')
                ->where(fn ($query) => $query->whereNull('type_code')->orWhere('type_code', ''))
                ->update(['type_code' => $fallbackType]);
        }

        DB::statement('UPDATE tbl_users SET code = id WHERE code IS NULL OR code = 0');
        DB::statement('ALTER TABLE tbl_users MODIFY type_code VARCHAR(30) NOT NULL');
        DB::statement('ALTER TABLE tbl_users MODIFY code BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        // The columns contain live user classification data and are retained.
    }
};

<?php

use App\Models\Login;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_empdep', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('designation_id')
                ->constrained('roles')
                ->nullOnDelete();
        });

        DB::table('tbl_empdep as employment')
            ->join('tbl_users as users', 'users.id', '=', 'employment.user_id')
            ->join('model_has_roles as assigned', function ($join) {
                $join->on('assigned.model_id', '=', 'users.login_id')
                    ->where('assigned.model_type', Login::class);
            })
            ->whereNull('employment.role_id')
            ->update(['employment.role_id' => DB::raw('assigned.role_id')]);
    }

    public function down(): void
    {
        Schema::table('tbl_empdep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};

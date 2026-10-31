<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EMAIL = 'superadmin@aplconnect.local';

    private const UNIQUE_ID = 'USR0000002';

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $password = Hash::make('aplconnect@1234');

            $userAttributes = [
                'user_type' => 'USR5',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'status' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            // Some upgraded installations no longer store the derived display name.
            if (Schema::hasColumn('tbl_users', 'name')) {
                $userAttributes['name'] = 'Super Admin';
            }

            DB::table('tbl_users')->updateOrInsert(
                ['unique_id' => self::UNIQUE_ID],
                $userAttributes,
            );

            $userId = DB::table('tbl_users')->where('unique_id', self::UNIQUE_ID)->value('id');

            DB::table('tbl_usrlogin')->updateOrInsert(
                ['username' => self::EMAIL],
                [
                    'usr_type' => 'USR5',
                    'user_id' => self::UNIQUE_ID,
                    'password' => $password,
                    'status' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            DB::table('tbl_usrcontact')->updateOrInsert(
                ['user_id' => self::UNIQUE_ID, 'email' => self::EMAIL],
                [
                    'usr_type' => 'USR5',
                    'contact_type' => 'primary',
                    'is_primary' => true,
                    'is_verified' => true,
                    'email_verified_at' => $now,
                    'status' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            DB::table('tbl_roles')->updateOrInsert(
                ['name' => 'Owner', 'guard_name' => 'web'],
                ['status' => true, 'updated_at' => $now, 'created_at' => $now],
            );

            $roleId = DB::table('tbl_roles')
                ->where('name', 'Owner')
                ->where('guard_name', 'web')
                ->value('id');

            DB::table('tbl_model_has_roles')->updateOrInsert(
                ['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId],
                ['status' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $userId = DB::table('tbl_users')->where('unique_id', self::UNIQUE_ID)->value('id');

            if ($userId !== null) {
                DB::table('tbl_model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)
                    ->delete();
            }

            DB::table('tbl_usrcontact')->where('user_id', self::UNIQUE_ID)->where('email', self::EMAIL)->delete();
            DB::table('tbl_usrlogin')->where('user_id', self::UNIQUE_ID)->where('username', self::EMAIL)->delete();
            DB::table('tbl_users')->where('unique_id', self::UNIQUE_ID)->delete();
        });
    }
};

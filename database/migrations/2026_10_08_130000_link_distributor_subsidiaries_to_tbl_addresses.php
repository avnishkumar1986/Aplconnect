<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_distributor_subsidiaries', function (Blueprint $table): void {
            $table->foreignId('address_id')
                ->nullable()
                ->after('distributor_user_id')
                ->constrained('tbl_addresses')
                ->nullOnDelete();
            $table->foreignId('phone_contact_id')
                ->nullable()
                ->after('address_id')
                ->constrained('tbl_contacts')
                ->nullOnDelete();
            $table->foreignId('email_contact_id')
                ->nullable()
                ->after('phone_contact_id')
                ->constrained('tbl_contacts')
                ->nullOnDelete();
        });

        DB::table('tbl_distributor_subsidiaries')
            ->whereNotNull('address_line_1')
            ->orderBy('id')
            ->eachById(function ($subsidiary): void {
                $addressId = DB::table('tbl_addresses')->insertGetId([
                    'employee_id' => (string) $subsidiary->distributor_user_id,
                    'company_code' => 0,
                    'address_type' => '5',
                    'is_current_permanent_same' => false,
                    'address_line_1' => $subsidiary->address_line_1,
                    'address_line_2' => $subsidiary->address_line_2,
                    'city' => $subsidiary->city ?: '',
                    'district' => $subsidiary->district,
                    'state' => $subsidiary->state ?: '',
                    'postal_code' => $subsidiary->postal_code ?: '',
                    'country' => $subsidiary->country ?: 'India',
                    'latitude' => $subsidiary->latitude,
                    'longitude' => $subsidiary->longitude,
                    'status' => '1',
                    'created_by' => $subsidiary->created_by,
                    'updated_by' => $subsidiary->updated_by,
                    'created_at' => $subsidiary->created_at ?: now(),
                    'updated_at' => $subsidiary->updated_at ?: now(),
                ]);

                $phoneContactId = DB::table('tbl_contacts')->insertGetId([
                    'employee_id' => (string) $subsidiary->distributor_user_id,
                    'company_code' => 0,
                    'contact_type' => '1',
                    'contact_value' => $subsidiary->phone_number,
                    'is_primary' => false,
                    'status' => '1',
                    'created_by' => $subsidiary->created_by,
                    'updated_by' => $subsidiary->updated_by,
                    'created_at' => $subsidiary->created_at ?: now(),
                    'updated_at' => $subsidiary->updated_at ?: now(),
                ]);
                $emailContactId = DB::table('tbl_contacts')->insertGetId([
                    'employee_id' => (string) $subsidiary->distributor_user_id,
                    'company_code' => 0,
                    'contact_type' => '3',
                    'contact_value' => $subsidiary->email,
                    'is_primary' => false,
                    'status' => '1',
                    'created_by' => $subsidiary->created_by,
                    'updated_by' => $subsidiary->updated_by,
                    'created_at' => $subsidiary->created_at ?: now(),
                    'updated_at' => $subsidiary->updated_at ?: now(),
                ]);

                DB::table('tbl_distributor_subsidiaries')
                    ->where('id', $subsidiary->id)
                    ->update([
                        'address_id' => $addressId,
                        'phone_contact_id' => $phoneContactId,
                        'email_contact_id' => $emailContactId,
                    ]);
            });
    }

    public function down(): void
    {
        $addressIds = DB::table('tbl_distributor_subsidiaries')->whereNotNull('address_id')->pluck('address_id');
        $contactIds = DB::table('tbl_distributor_subsidiaries')
            ->select(['phone_contact_id', 'email_contact_id'])
            ->get()
            ->flatMap(fn ($row) => [$row->phone_contact_id, $row->email_contact_id])
            ->filter();
        Schema::table('tbl_distributor_subsidiaries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('email_contact_id');
            $table->dropConstrainedForeignId('phone_contact_id');
            $table->dropConstrainedForeignId('address_id');
        });
        DB::table('tbl_addresses')->whereIn('id', $addressIds)->delete();
        DB::table('tbl_contacts')->whereIn('id', $contactIds)->delete();
    }
};

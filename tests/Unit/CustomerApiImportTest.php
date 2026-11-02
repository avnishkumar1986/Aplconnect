<?php

use App\Jobs\ImportMaterialsFromApi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::create('tbl_usertype', function (Blueprint $table): void {
        $table->string('uid')->primary();
        $table->boolean('status');
    });
    Schema::create('tbl_users', function (Blueprint $table): void {
        $table->id();
        $table->string('user_type');
        $table->string('unique_id')->unique();
        $table->string('name');
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->boolean('status');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });
    Schema::create('tbl_usrcontact', function (Blueprint $table): void {
        $table->id();
        $table->string('usr_type')->nullable();
        $table->string('user_id');
        $table->string('contact_type');
        $table->string('email')->nullable();
        $table->string('contact_number')->nullable();
        $table->boolean('is_primary');
        $table->boolean('is_verified');
        $table->boolean('status');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });
    Schema::create('tbl_usraddress', function (Blueprint $table): void {
        $table->id();
        $table->string('user_type')->nullable();
        $table->string('user_id');
        $table->string('address_type');
        $table->string('address_line_1');
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('district')->nullable();
        $table->string('postal_code')->nullable();
        $table->string('country')->nullable();
        $table->boolean('is_primary');
        $table->boolean('status');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });
    Schema::create('tbl_customer_sales', function (Blueprint $table): void {
        $table->id();
        $table->string('user_id')->unique();
        $table->string('user_type');
        $table->date('record_creation_date')->nullable();
        $table->string('plant_code')->nullable();
        $table->boolean('deletion_indicator');
        foreach (['gstin', 'ecc_no', 'cst_no', 'lst_no', 'pan', 'sales_organization', 'sales_district', 'sales_group', 'sales_office', 'payment_terms'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['credit_limit', 'outstanding_amount', 'credit_utilization_percentage'] as $column) {
            $table->decimal($column, 18, 3)->default(0);
        }
        foreach (range(1, 6) as $level) {
            $table->string("employee_level_{$level}")->nullable();
        }
        foreach (range(1, 12) as $month) {
            $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $table->decimal("month_{$suffix}_target", 18, 2)->default(0);
            $table->decimal("month_{$suffix}_actual", 18, 2)->default(0);
        }
        foreach (range(1, 4) as $quarter) {
            $table->decimal("quarter_{$quarter}_target", 18, 2)->default(0);
            $table->decimal("quarter_{$quarter}_actual", 18, 2)->default(0);
        }
        $table->json('api_payload')->nullable();
        $table->timestamp('synced_at')->nullable();
        $table->boolean('status');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });

    DB::table('tbl_usertype')->insert(['uid' => 'USR4', 'status' => true]);
});

it('synchronizes a customer into user, contact, address, and sales tables', function (): void {
    $job = new ImportMaterialsFromApi(apiId: 1);
    $method = new ReflectionMethod($job, 'importCustomers');
    $result = $method->invoke($job, [[
        'Kunnr' => '2005329',
        'Name1' => 'M/S B. K.TRADERS',
        'Adrnr' => 'GROUND FLOOR, GANJ ROAD, BIJNOR',
        'Ort01' => 'BIJNOR',
        'Pstlz' => '246701',
        'State' => 'Uttar Pradesh',
        'MobileNo' => '9719437575',
        'MailId' => 'customer@example.com',
        'Gstin' => '09ABEPF4913E1Z0',
        'Amount' => '1616024.900',
        'Z07a' => '4258466.97',
        'Q2a' => '15577920.74',
        'Loevm' => false,
    ]]);

    expect($result)->toBe([1, 0, 0]);
    $this->assertDatabaseHas('tbl_users', [
        'unique_id' => '2005329',
        'name' => 'M/S B. K.TRADERS',
        'first_name' => 'M/S',
        'middle_name' => 'B.',
        'last_name' => 'K.TRADERS',
        'user_type' => 'USR4',
    ]);
    $this->assertDatabaseHas('tbl_usrcontact', ['user_id' => '2005329', 'contact_number' => '9719437575']);
    $this->assertDatabaseHas('tbl_usraddress', ['user_id' => '2005329', 'city' => 'BIJNOR']);
    $this->assertDatabaseHas('tbl_customer_sales', [
        'user_id' => '2005329',
        'gstin' => '09ABEPF4913E1Z0',
        'outstanding_amount' => 1616024.900,
        'month_07_actual' => 4258466.97,
        'quarter_2_actual' => 15577920.74,
    ]);
});

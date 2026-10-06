<?php

namespace Database\Seeders;

use App\Models\{UserProfile, UserType};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $userType = UserType::where('status', '1')->first()
            ?? UserType::first()
            ?? UserType::create([
                'type_code' => 'EMP',
                'type_name' => 'Employee',
                'description' => 'Standard employee profile',
                'status' => '1',
            ]);

        $firstNames = ['Aarav','Aditi','Akash','Ananya','Arjun','Diya','Ishaan','Kavya','Kunal','Meera','Neha','Nikhil','Pooja','Pranav','Priya','Rahul','Riya','Rohan','Saanvi','Varun'];
        $lastNames = ['Agarwal','Bansal','Chauhan','Desai','Gupta','Iyer','Jain','Joshi','Kapoor','Kumar','Malhotra','Mehta','Mishra','Patel','Rao','Sharma','Singh','Verma','Yadav','Nair'];

        DB::transaction(function () use ($userType, $firstNames, $lastNames): void {
            foreach (range(1, 100) as $number) {
                UserProfile::firstOrCreate(
                    ['reference_id' => sprintf('DEMO-%04d', $number)],
                    [
                        'user_type_code' => $userType->id,
                        'first_name' => $firstNames[($number - 1) % count($firstNames)],
                        'last_name' => $lastNames[(intdiv($number - 1, count($firstNames)) + $number - 1) % count($lastNames)],
                        'gender' => (string) (($number % 3) + 1),
                        'is_verified' => $number % 5 === 0 ? 0 : 1,
                        'date_of_birth' => now()->subYears(22 + ($number % 28))->subDays($number * 3)->toDateString(),
                        'marital_status' => (string) (($number % 4) + 1),
                        'anniversary_date' => $number % 4 === 1 ? null : now()->subYears(1 + ($number % 12))->subDays($number)->toDateString(),
                        'status' => $number % 10 === 0 ? '0' : '1',
                    ]
                );
            }
        });
    }
}

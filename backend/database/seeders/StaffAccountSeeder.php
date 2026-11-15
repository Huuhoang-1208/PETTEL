<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StaffAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('StaffAccountSeeder is only available in local and testing.');
        }

        $accounts = [
            [
                'name' => 'PETTEL Admin',
                'email' => config('staff.admin.email'),
                'password' => config('staff.admin.password'),
                'role' => 'ADMIN',
            ],
            [
                'name' => 'PETTEL Support',
                'email' => config('staff.support.email'),
                'password' => config('staff.support.password'),
                'role' => 'SUPPORT',
            ],
        ];

        if (collect($accounts)->every(fn (array $account): bool => blank($account['email']) && blank($account['password']))) {
            return;
        }

        foreach ($accounts as $account) {
            if (blank($account['email']) || blank($account['password'])) {
                throw new RuntimeException('Set both the email and password environment variables for every staff account.');
            }
        }

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                $user = User::query()->firstOrCreate(
                    ['email' => $account['email']],
                    ['name' => $account['name'], 'password' => $account['password']],
                );

                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role' => $account['role'],
                    'assigned_at' => now(),
                ]);
            }
        });
    }
}

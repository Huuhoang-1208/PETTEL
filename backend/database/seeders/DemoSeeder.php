<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoSeeder is only available in local and testing.'
            );
        }

        DB::transaction(function (): void {
            $accounts = [
                'owner@example.test' => [
                    'name' => 'Demo Owner',
                    'roles' => ['OWNER'],
                ],
                'caregiver@example.test' => [
                    'name' => 'Demo Caregiver',
                    'roles' => ['OWNER', 'CAREGIVER'],
                ],
                'admin@example.test' => [
                    'name' => 'Demo Admin',
                    'roles' => ['ADMIN'],
                ],
            ];

            foreach ($accounts as $email => $account) {
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $account['name'],
                        'password' => 'DemoOnly123!',
                    ],
                );

                foreach ($account['roles'] as $role) {
                    DB::table('user_roles')->insertOrIgnore([
                        'user_id' => $user->id,
                        'role' => $role,
                        'assigned_at' => now(),
                    ]);
                }
            }

            $owner = User::query()
                ->where('email', 'owner@example.test')
                ->firstOrFail();

            $caregiver = User::query()
                ->where('email', 'caregiver@example.test')
                ->firstOrFail();

            $timestamp = now();

            if (
                ! DB::table('pets')
                    ->where('user_id', $owner->id)
                    ->where('name', 'Demo Mochi')
                    ->exists()
            ) {
                DB::table('pets')->insert([
                    'user_id' => $owner->id,
                    'name' => 'Demo Mochi',
                    'species' => 'DOG',
                    'size' => 'SMALL',
                    'sex' => 'UNKNOWN',
                    'age_note' => 'Du lieu minh hoa',
                    'status' => 'ACTIVE',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            if (
                ! DB::table('caregiver_profiles')
                    ->where('user_id', $caregiver->id)
                    ->exists()
            ) {
                DB::table('caregiver_profiles')->insert([
                    'user_id' => $caregiver->id,
                    'type' => 'INDIVIDUAL',
                    'display_name' => 'Demo Pet Care',
                    'bio' => 'Ho so minh hoa, khong phai ca nhan that.',
                    'area_code' => 'DN_CENTER',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });
    }
}

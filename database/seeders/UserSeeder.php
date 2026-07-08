<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {

        /*User::factory()->count(14)
               ->has(Project::factory()
                   ->state(['stage_id' => 1])
                   ->count(6))
               ->create();*/

        $now = now();
        $password = Hash::make('Berry@999');

        foreach (range(1, 10) as $chunk) {
            $rows = [];

            for ($i = 1; $i <= 1000; $i++) {
                $index = (($chunk - 1) * 1000) + $i;
                $name = fake()->name();
                $base = Str::slug($name, '.');
                $base = Str::limit($base, 40, '');
                $username = $base.$index;
                $email = Str::limit($base, 50, '').$index.'@daywright.test';

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'username' => $username,
                    'avatar_path' => 'https://eu.ui-avatars.com/api/?name='.urlencode($name),
                    'email' => $email,
                    'email_verified_at' => $now,
                    'password' => $password,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($rows);
        }
    }
}

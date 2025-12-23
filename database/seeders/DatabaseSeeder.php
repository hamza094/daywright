<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        //$this->call(TaskStatusSeeder::class);
        //$this->call(StageSeeder::class);
        //$this->call(UserSeeder::class);
        //$this->call(InfoSeeder::class);
        //$this->call(MembersSeeder::class);
        //$this->call(TaskSeeder::class);
        //$this->call(ConversationSeeder::class);
        //$this->call(NotificationSeeder::class);
        $this->call(MeetingSeeder::class);

    }
}

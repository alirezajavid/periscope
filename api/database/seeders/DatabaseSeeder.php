<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** `php artisan db:seed` fills a LOCAL database with demo data; it refuses to run in production. */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}

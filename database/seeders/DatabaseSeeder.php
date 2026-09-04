<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            DemoSeeder::class,
            // CompletePlateMapSeeder lo invoca DemoSeeder (idempotente) para mapear todas las patentes.
        ]);
    }
}

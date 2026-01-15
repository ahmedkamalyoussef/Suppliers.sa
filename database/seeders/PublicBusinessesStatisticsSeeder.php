<?php

namespace Database\Seeders;

use App\Models\PublicBusinessesStatistics;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PublicBusinessesStatisticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PublicBusinessesStatistics::updateOrCreate(
            ['id' => 1],
            [
                'verified_businesses' => 150,
                'successful_connections' => 1250,
                'average_rating' => 4.3,
            ]
        );
    }
}

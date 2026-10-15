<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityCategory;
use App\Models\PrayerProgram;
use App\Models\Daora;
use Carbon\Carbon;

class InternalActivitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Categories
        ActivityCategory::firstOrCreate(
            ['type' => 'prayer_program'],
            ['name' => 'برامج الصلاة', 'is_active' => true]
        );

        ActivityCategory::firstOrCreate(
            ['type' => 'chess_tournament'],
            ['name' => 'مسابقات الشطرنج', 'is_active' => true]
        );


    }
}
